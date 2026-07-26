<?php
declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\Helper;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\User;
use App\Services\PasskeyService;
use App\Services\WebAuthn\WebAuthnVerifier;

/**
 * 前台读者 Passkey 绑定（登录后）。
 * 登录仍走 /admin/passkey/login*（已支持按 credential 解析任意用户）。
 */
final class PasskeyController
{
    private PasskeyService $service;

    public function __construct()
    {
        $this->service = new PasskeyService();
    }

    public function registerOptions(): never
    {
        $user = $this->requireVerifiedSessionUser();
        $challenge = $this->base64UrlEncode(random_bytes(32));
        Session::set('front_passkey_challenge', $challenge);

        Response::json([
            'challenge' => $challenge,
            'rp' => ['name' => 'LiteNote', 'id' => $this->rpId()],
            'user' => [
                'id' => $this->base64UrlEncode((string) $user->id),
                'name' => (string) $user->username,
                'displayName' => (string) ($user->nickname ?: $user->username),
            ],
            'pubKeyCredParams' => [['type' => 'public-key', 'alg' => -7]],
            'timeout' => 60000,
            'attestation' => 'none',
            'authenticatorSelection' => [
                'residentKey' => 'preferred',
                'userVerification' => 'preferred',
            ],
        ]);
    }

    public function register(Request $request): never
    {
        $user = $this->requireVerifiedSessionUser();
        $data = json_decode((string) $request->input('credential'), true);
        if (!is_array($data) || empty($data['id'])) {
            Response::json(['success' => false, 'message' => 'Passkey 凭证数据无效'], 422);
        }

        $challenge = (string) Session::get('front_passkey_challenge', '');
        if ($challenge === '') {
            Response::json(['success' => false, 'message' => 'Passkey 挑战已过期,请刷新后重试'], 422);
        }

        try {
            $verified = WebAuthnVerifier::verifyRegistration(
                $data,
                $challenge,
                $this->rpId(),
                $this->origin()
            );
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => Helper::publicErrorMessage($e, 'Passkey 绑定验证失败')], 422);
        }

        $deviceName = trim((string) $request->input('device_name', ''));
        if (mb_strlen($deviceName) > 100) {
            Response::json(['success' => false, 'message' => 'Passkey 名称不能超过 100 个字符'], 422);
        }
        if ($deviceName === '') {
            $deviceName = 'Passkey';
        }

        $saved = $this->service->saveCredential([
            'user_id' => (int) $user->id,
            'credential_id' => $verified['credential_id'],
            'public_key' => $verified['public_key'],
            'counter' => $verified['counter'],
            'device_name' => $deviceName,
        ]);

        Session::forget('front_passkey_challenge');
        Response::json([
            'success' => $saved,
            'message' => $saved ? 'Passkey 已绑定，之后可用 Passkey 登录' : 'Passkey 绑定失败',
            'device_name' => $deviceName,
        ]);
    }

    private function requireVerifiedSessionUser(): User
    {
        $session = Session::get('admin_user');
        $id = is_array($session) ? (int) ($session['id'] ?? 0) : 0;
        if ($id <= 0) {
            Response::json(['success' => false, 'message' => '请先登录'], 401);
        }
        $user = User::find($id);
        if (!$user || !$user->isActive()) {
            Response::json(['success' => false, 'message' => '账号无效'], 403);
        }
        if (!$user->isEmailVerified()) {
            Response::json(['success' => false, 'message' => '请先完成邮箱验证'], 403);
        }
        // 管理员请走后台绑定；此处主要服务读者，也允许 admin 在前台绑（无害）
        return $user;
    }

    private function rpId(): string
    {
        $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
        $parsed = parse_url('http://' . $host, PHP_URL_HOST);
        return $parsed ?: (string) preg_replace('/:\d+$/', '', (string) $host);
    }

    private function origin(): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $this->rpId();
        $port = (int) ($_SERVER['SERVER_PORT'] ?? 80);
        if (($scheme === 'https' && $port === 443) || ($scheme === 'http' && $port === 80)) {
            return $scheme . '://' . $host;
        }
        return $scheme . '://' . $host . ':' . $port;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
