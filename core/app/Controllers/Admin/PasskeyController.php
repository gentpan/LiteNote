<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\User;
use App\Services\PasskeyService;

class PasskeyController
{
    private $service;

    public function __construct()
    {
        $this->service = new PasskeyService();
    }

    public function registerOptions(Request $request)
    {
        $adminId = (int) Session::get('admin_user.id', 1);
        $user = User::find($adminId) ?: User::find(1);
        if (!$user) {
            Response::json(['success' => false, 'message' => '未找到管理员账号'], 404);
        }

        $challenge = $this->base64UrlEncode(random_bytes(32));
        Session::set('passkey_challenge', $challenge);

        $options = [
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
        ];

        Response::json($options);
    }

    public function register(Request $request)
    {
        $data = json_decode($request->input('credential'), true);
        if (!is_array($data) || empty($data['id'])) {
            Response::json(['success' => false, 'message' => 'Passkey 凭证数据无效'], 422);
        }

        if (!$this->challengeMatches($data, 'passkey_challenge')) {
            Response::json(['success' => false, 'message' => 'Passkey 挑战校验失败,请刷新后重试'], 422);
        }

        $adminId = (int) Session::get('admin_user.id', 1);

        $saved = $this->service->saveCredential([
            'user_id'       => $adminId,
            'credential_id' => $data['id'],
            'public_key'    => json_encode($data['response']),
            'counter'       => 0,
            'device_name'   => $request->input('device_name', '未知设备')
        ]);

        Session::forget('passkey_challenge');
        Response::json([
            'success' => $saved,
            'message' => $saved ? 'Passkey 已绑定' : 'Passkey 绑定失败',
        ]);
    }

    public function loginOptions(Request $request)
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

        $options = [
            'success' => true,
            'challenge' => $challenge,
            'timeout' => 60000,
            'rpId' => $this->rpId(),
            'allowCredentials' => array_map(static fn(array $credential): array => [
                'type' => 'public-key',
                'id' => $credential['credential_id'],
            ], $credentials),
            'userVerification' => 'preferred',
        ];

        Response::json($options);
    }

    public function login(Request $request)
    {
        $data = json_decode($request->input('credential'), true);
        if (!is_array($data) || empty($data['id'])) {
            Response::json(['success' => false, 'message' => 'Passkey 凭证数据无效'], 422);
        }

        if (!$this->challengeMatches($data, 'passkey_login_challenge')) {
            Response::json(['success' => false, 'message' => 'Passkey 挑战校验失败,请重新登录'], 422);
        }

        $credential = $this->service->getCredentialById($data['id']);

        if (!$credential) {
            Response::json(['success' => false, 'message' => '未找到匹配的 Passkey'], 404);
        }

        $user = User::find((int) ($credential['user_id'] ?? 1));
        if (!$user) {
            Response::json(['success' => false, 'message' => 'Passkey 对应的管理员账号不存在'], 404);
        }

        $this->service->updateCounter($credential['credential_id'], (int) $credential['counter'] + 1);
        User::db()->update('users', [
            'last_login_at' => date('Y-m-d H:i:s'),
            'last_login_ip' => $request->ip,
        ], 'id = :id', ['id' => $user->id]);

        Session::set('admin_user', [
            'id'       => $user->id,
            'username' => $user->username,
            'nickname' => $user->nickname,
            'role'     => $user->role,
        ]);
        Session::forget('passkey_login_challenge');
        Session::regenerate();

        Response::json(['success' => true, 'message' => 'Passkey 登录成功']);
    }

    private function rpId(): string
    {
        $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
        $parsed = parse_url('http://' . $host, PHP_URL_HOST);
        return $parsed ?: (string) preg_replace('/:\d+$/', '', $host);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string
    {
        $value = strtr($value, '-_', '+/');
        $padding = strlen($value) % 4;
        if ($padding > 0) {
            $value .= str_repeat('=', 4 - $padding);
        }
        return (string) base64_decode($value, true);
    }

    private function challengeMatches(array $data, string $sessionKey): bool
    {
        $expected = (string) Session::get($sessionKey, '');
        $clientData = (string) ($data['response']['clientDataJSON'] ?? '');
        if ($expected === '' || $clientData === '') {
            return false;
        }

        $decoded = json_decode($this->base64UrlDecode($clientData), true);
        if (!is_array($decoded)) {
            $decoded = json_decode((string) base64_decode($clientData, true), true);
        }

        return is_array($decoded)
            && hash_equals($expected, (string) ($decoded['challenge'] ?? ''));
    }
}
