<?php
declare(strict_types=1);

namespace LiteNotePlugin\X\Controllers;

use App\Core\Http;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\ActivityIntegration;
use App\Services\ActivityInstaller;

final class XOAuthController
{
    private const SCOPES = ['tweet.read', 'users.read', 'bookmark.read', 'offline.access'];

    public function start(): never
    {
        $clientId = $this->env('X_OAUTH_CLIENT_ID');
        if ($clientId === '') {
            Session::flash('error', '请先在 .env 配置 X_OAUTH_CLIENT_ID');
            Response::redirect('/admin/activities/integrations/x_bookmarks/edit');
        }

        $state = bin2hex(random_bytes(24));
        $verifier = $this->base64Url(random_bytes(48));
        $challenge = $this->base64Url(hash('sha256', $verifier, true));

        Session::set('x_oauth_state', $state);
        Session::set('x_oauth_code_verifier', $verifier);

        $params = [
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => $this->redirectUri(),
            'scope' => implode(' ', self::SCOPES),
            'state' => $state,
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
        ];

        Response::redirect('https://x.com/i/oauth2/authorize?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986));
    }

    public function callback(Request $request): never
    {
        $error = trim((string)$request->input('error', ''));
        if ($error !== '') {
            Session::flash('error', 'X 授权失败：' . $error);
            Response::redirect('/admin/activities/integrations/x_bookmarks/edit');
        }

        $state = trim((string)$request->input('state', ''));
        $code = trim((string)$request->input('code', ''));
        $savedState = (string)Session::get('x_oauth_state', '');
        $verifier = (string)Session::get('x_oauth_code_verifier', '');
        Session::forget('x_oauth_state');
        Session::forget('x_oauth_code_verifier');

        if ($state === '' || $code === '' || $savedState === '' || !hash_equals($savedState, $state) || $verifier === '') {
            Session::flash('error', 'X 授权状态无效，请重新授权');
            Response::redirect('/admin/activities/integrations/x_bookmarks/edit');
        }

        try {
            $tokens = $this->exchangeCode($code, $verifier);
            $accessToken = (string)($tokens['access_token'] ?? '');
            if ($accessToken === '') {
                throw new \RuntimeException('X 未返回 access_token');
            }

            ActivityInstaller::install();
            $existing = ActivityIntegration::findByProvider('x_bookmarks');
            $metadata = $existing ? $existing->metadata() : [];
            $metadata['sync_interval_minutes'] = $metadata['sync_interval_minutes'] ?? '360';
            $metadata['limit'] = $metadata['limit'] ?? '50';
            $metadata['pages'] = $metadata['pages'] ?? '1';

            $profile = $this->fetchMe($accessToken);
            if (!empty($profile['id'])) {
                $metadata['user_id'] = (string)$profile['id'];
            }
            if (!empty($profile['username'])) {
                $metadata['username'] = (string)$profile['username'];
            }

            ActivityIntegration::saveProvider('x_bookmarks', [
                'status' => 'active',
                'access_token' => $accessToken,
                'refresh_token' => (string)($tokens['refresh_token'] ?? ''),
                'expires_at' => $this->expiresAt((int)($tokens['expires_in'] ?? 0)),
                'metadata' => $metadata,
            ]);

            Session::flash('success', 'X 账号授权成功，X Bookmarks 已启用');
        } catch (\Throwable $e) {
            Session::flash('error', 'X 授权失败：' . $e->getMessage());
        }

        Response::redirect('/admin/activities/integrations/x_bookmarks/edit');
    }

    private function exchangeCode(string $code, string $verifier): array
    {
        return $this->postForm('https://api.x.com/2/oauth2/token', [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->redirectUri(),
            'code_verifier' => $verifier,
            'client_id' => $this->env('X_OAUTH_CLIENT_ID'),
        ]);
    }

    private function fetchMe(string $accessToken): array
    {
        $json = $this->httpGet('https://api.x.com/2/users/me?user.fields=username', [
            'Authorization: Bearer ' . $accessToken,
        ]);
        $data = json_decode($json, true);
        return is_array($data['data'] ?? null) ? $data['data'] : [];
    }

    private function postForm(string $url, array $fields): array
    {
        $headers = ['Accept: application/json'];
        $clientId = $this->env('X_OAUTH_CLIENT_ID');
        $clientSecret = $this->env('X_OAUTH_CLIENT_SECRET');
        if ($clientId !== '' && $clientSecret !== '') {
            $headers[] = 'Authorization: Basic ' . base64_encode($clientId . ':' . $clientSecret);
        }

        $res = Http::request('POST', $url, [
            'headers' => array_merge(['Content-Type: application/x-www-form-urlencoded'], $headers),
            'body' => http_build_query($fields),
            'connect_timeout' => 15,
            'timeout' => 20,
            'default_headers' => false,
        ]);

        $data = $res['body'] !== '' ? json_decode($res['body'], true) : null;
        if (!$res['ok'] || !is_array($data)) {
            $message = is_array($data) ? (string)($data['error_description'] ?? $data['error'] ?? '') : '';
            throw new \RuntimeException($message !== '' ? $message : 'Token exchange failed');
        }

        return $data;
    }

    private function httpGet(string $url, array $headers): string
    {
        $res = Http::request('GET', $url, [
            'headers' => array_merge(['Accept: application/json'], $headers),
            'connect_timeout' => 10,
            'timeout' => 15,
            'default_headers' => false,
        ]);
        return $res['ok'] ? $res['body'] : '';
    }

    private function redirectUri(): string
    {
        $configured = $this->env('X_OAUTH_REDIRECT_URI');
        if ($configured !== '') {
            return $configured;
        }

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = (string)($_SERVER['HTTP_HOST'] ?? '127.0.0.1:5555');
        return $scheme . '://' . $host . '/admin/oauth/x/callback';
    }

    private function expiresAt(int $seconds): ?string
    {
        return $seconds > 0 ? date('Y-m-d H:i:s', time() + $seconds) : null;
    }

    private function env(string $key): string
    {
        $value = getenv($key);
        if ($value !== false && $value !== '') {
            return (string)$value;
        }
        return (string)($_ENV[$key] ?? $_SERVER[$key] ?? '');
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
