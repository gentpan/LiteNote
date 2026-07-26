<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Helper;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\User;
use App\Services\PasskeyService;
use App\Services\WebAuthn\WebAuthnVerifier;

class PasskeyController
{
    private PasskeyService $service;

    public function __construct()
    {
        $this->service = new PasskeyService();
    }

    public function registerOptions(Request $request): never
    {
        $adminId = (int) Session::get('admin_user.id', 1);
        $user = User::find($adminId) ?: User::find(1);
        if (!$user) {
            Response::json(['success' => false, 'message' => '未找到管理员账号'], 404);
        }

        $challenge = $this->base64UrlEncode(random_bytes(32));
        Session::set('passkey_challenge', $challenge);

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
        $data = json_decode((string) $request->input('credential'), true);
        if (!is_array($data) || empty($data['id'])) {
            Response::json(['success' => false, 'message' => 'Passkey 凭证数据无效'], 422);
        }

        $challenge = (string) Session::get('passkey_challenge', '');
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

        $adminId = (int) Session::get('admin_user.id', 1);
        $saved = $this->service->saveCredential([
            'user_id' => $adminId,
            'credential_id' => $verified['credential_id'],
            'public_key' => $verified['public_key'],
            'counter' => $verified['counter'],
            'device_name' => $deviceName,
        ]);

        Session::forget('passkey_challenge');
        Response::json([
            'success' => $saved,
            'message' => $saved ? 'Passkey 已绑定' : 'Passkey 绑定失败',
            'device_name' => $deviceName,
        ]);
    }

    public function delete(Request $request): never
    {
        $id = (int) $request->input('id', 0);
        if ($id <= 0) {
            Response::json(['success' => false, 'message' => 'Passkey 标识无效'], 422);
        }

        $adminId = (int) Session::get('admin_user.id', 1);
        $deleted = $this->service->deleteCredential($id, $adminId);

        Response::json([
            'success' => $deleted,
            'message' => $deleted ? 'Passkey 已删除' : '未找到可删除的 Passkey',
        ], $deleted ? 200 : 404);
    }

    public function loginOptions(Request $request): never
    {
        $credentials = $this->service->allCredentials();
        if (!$credentials) {
            Response::json([
                'success' => false,
                'message' => '尚未绑定 Passkey,请先用密码登录后在个人资料中绑定',
            ]);
        }

        $challenge = $this->base64UrlEncode(random_bytes(32));
        Session::set('passkey_login_challenge', $challenge);

        Response::json([
            'success' => true,
            'challenge' => $challenge,
            'timeout' => 60000,
            'rpId' => $this->rpId(),
            'allowCredentials' => array_map(static fn(array $credential): array => [
                'type' => 'public-key',
                'id' => $credential['credential_id'],
            ], $credentials),
            'userVerification' => 'preferred',
        ]);
    }

    public function login(Request $request): never
    {
        $data = json_decode((string) $request->input('credential'), true);
        if (!is_array($data) || empty($data['id'])) {
            Response::json(['success' => false, 'message' => 'Passkey 凭证数据无效'], 422);
        }

        $challenge = (string) Session::get('passkey_login_challenge', '');
        if ($challenge === '') {
            Response::json(['success' => false, 'message' => 'Passkey 挑战已过期,请重新登录'], 422);
        }

        $credential = $this->service->getCredentialById((string) $data['id']);
        if (!$credential) {
            Response::json(['success' => false, 'message' => '未找到匹配的 Passkey'], 404);
        }

        try {
            $result = WebAuthnVerifier::verifyAssertion(
                $data,
                $challenge,
                $this->rpId(),
                $this->origin(),
                (string) ($credential['public_key'] ?? ''),
                (int) ($credential['counter'] ?? 0)
            );
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => Helper::publicErrorMessage($e, 'Passkey 登录验证失败')], 422);
        }

        $user = User::find((int) ($credential['user_id'] ?? 1));
        if (!$user) {
            Response::json(['success' => false, 'message' => 'Passkey 对应的账号不存在'], 404);
        }
        if (!$user->isActive()) {
            Response::json(['success' => false, 'message' => '账号已停用'], 403);
        }
        if (!$user->isEmailVerified()) {
            Response::json(['success' => false, 'need_verify' => true, 'message' => '请先完成邮箱验证后再登录'], 403);
        }

        $role = $user->isAdmin() ? 'admin' : 'reader';
        if (!$user->isAdmin() && (string) ($user->role ?? '') !== 'reader') {
            User::db()->update('users', ['role' => 'reader'], 'id = :id', ['id' => $user->id]);
        }

        $this->service->updateCounter((string) $credential['credential_id'], (int) $result['counter']);
        User::db()->update('users', [
            'last_login_at' => date('Y-m-d H:i:s'),
            'last_login_ip' => $request->ip,
        ], 'id = :id', ['id' => $user->id]);

        Session::set('admin_user', [
            'id' => $user->id,
            'username' => $user->username,
            'nickname' => $user->nickname,
            'role' => $role,
            'status' => isset($user->status) ? (int) $user->status : 1,
        ]);
        Session::forget('passkey_login_challenge');
        Session::regenerate();

        Response::json([
            'success' => true,
            'message' => 'Passkey 登录成功',
            'redirect' => $role === 'admin' ? '/admin' : '/',
            'role' => $role,
            'identity' => \App\Controllers\Front\AuthController::identityPayload($user),
        ]);
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
