<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Helper;
use App\Core\Http;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\ActivityIntegration;
use App\Services\ActivityInstaller;

final class SpotifyOAuthController
{
    private const SCOPES = ['user-read-recently-played', 'user-library-read'];

    public function start(): never
    {
        [$clientId, $clientSecret] = $this->credentials();
        if ($clientId === '' || $clientSecret === '') {
            Session::flash('error', '请先保存 Spotify Client ID 和 Client Secret');
            Response::redirect('/admin/activities/integrations/spotify/edit');
        }

        $state = bin2hex(random_bytes(24));
        Session::set('spotify_oauth_state', $state);
        $redirectUri = $this->redirectUri();
        Session::set('spotify_oauth_redirect_uri', $redirectUri);
        $this->rememberRedirectUri($redirectUri);

        $params = [
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'scope' => implode(' ', self::SCOPES),
            'state' => $state,
            'show_dialog' => 'true',
        ];

        Response::redirect('https://accounts.spotify.com/authorize?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986));
    }

    public function callback(Request $request): never
    {
        $error = trim((string)$request->input('error', ''));
        if ($error !== '') {
            $description = trim((string)$request->input('error_description', ''));
            Session::flash('error', 'Spotify 授权失败：' . $error . ($description !== '' ? '，' . $description : ''));
            Response::redirect('/admin/activities/integrations/spotify/edit');
        }

        $state = trim((string)$request->input('state', ''));
        $code = trim((string)$request->input('code', ''));
        $savedState = (string)Session::get('spotify_oauth_state', '');
        $redirectUri = trim((string)Session::get('spotify_oauth_redirect_uri', ''));
        Session::forget('spotify_oauth_state');
        Session::forget('spotify_oauth_redirect_uri');

        if ($state === '' || $code === '' || $savedState === '' || !hash_equals($savedState, $state)) {
            Session::flash('error', 'Spotify 授权状态无效，请重新授权');
            Response::redirect('/admin/activities/integrations/spotify/edit');
        }
        if ($redirectUri === '') {
            $redirectUri = $this->redirectUri();
        }

        try {
            $tokens = $this->exchangeCode($code, $redirectUri);
            $accessToken = trim((string)($tokens['access_token'] ?? ''));
            $refreshToken = trim((string)($tokens['refresh_token'] ?? ''));
            if ($accessToken === '') {
                throw new \RuntimeException('Spotify 未返回 access_token');
            }

            ActivityInstaller::install();
            $existing = ActivityIntegration::findByProvider('spotify');
            $metadata = $existing ? $existing->metadata() : [];
            $metadata['sync_interval_minutes'] = $metadata['sync_interval_minutes'] ?? '60';
            $metadata['limit'] = $metadata['limit'] ?? '20';
            $metadata['sync_saved'] = $metadata['sync_saved'] ?? '0';
            $metadata['redirect_uri'] = $redirectUri;

            $profile = $this->fetchMe($accessToken);
            if (!empty($profile['id'])) {
                $metadata['user_id'] = (string)$profile['id'];
            }
            if (!empty($profile['display_name'])) {
                $metadata['display_name'] = (string)$profile['display_name'];
            }

            ActivityIntegration::saveProvider('spotify', [
                'status' => 'active',
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken !== '' ? $refreshToken : (string)($existing->refresh_token ?? ''),
                'expires_at' => $this->expiresAt((int)($tokens['expires_in'] ?? 0)),
                'metadata' => $metadata,
            ]);

            Session::flash('success', 'Spotify 授权成功，动态同步已启用');
        } catch (\Throwable $e) {
            Session::flash('error', 'Spotify 授权失败：' . Helper::publicErrorMessage($e));
        }

        Response::redirect('/admin/activities/integrations/spotify/edit');
    }

    private function exchangeCode(string $code, string $redirectUri): array
    {
        [$clientId, $clientSecret] = $this->credentials();
        if ($clientId === '' || $clientSecret === '') {
            throw new \RuntimeException('缺少 Spotify Client ID 或 Client Secret');
        }

        $res = Http::request('POST', 'https://accounts.spotify.com/api/token', [
            'headers' => [
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/json',
                'Authorization: Basic ' . base64_encode($clientId . ':' . $clientSecret),
            ],
            'body' => http_build_query([
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $redirectUri,
            ]),
            'timeout' => 20,
            'default_headers' => false,
        ]);

        $data = $res['body'] !== '' ? json_decode($res['body'], true) : null;
        if (!$res['ok'] || !is_array($data)) {
            $message = is_array($data) ? (string)($data['error_description'] ?? $data['error'] ?? '') : '';
            throw new \RuntimeException($message !== '' ? $message : 'Token exchange failed, HTTP ' . (string)$res['status']);
        }

        return $data;
    }

    private function fetchMe(string $accessToken): array
    {
        $json = Http::getJson('https://api.spotify.com/v1/me', [
            'Authorization: Bearer ' . $accessToken,
        ], 15);
        return is_array($json) ? $json : [];
    }

    /**
     * @return array{0:string,1:string}
     */
    private function credentials(): array
    {
        $integration = ActivityIntegration::findByProvider('spotify');
        $metadata = $integration ? $integration->metadata() : [];
        $clientId = trim((string)($metadata['client_id'] ?? $this->env('SPOTIFY_CLIENT_ID')));
        $clientSecret = trim((string)($metadata['client_secret'] ?? $this->env('SPOTIFY_CLIENT_SECRET')));
        return [$clientId, $clientSecret];
    }

    private function rememberRedirectUri(string $redirectUri): void
    {
        $integration = ActivityIntegration::findByProvider('spotify');
        $metadata = $integration ? $integration->metadata() : [];
        if (($metadata['redirect_uri'] ?? '') === $redirectUri) {
            return;
        }

        $metadata['redirect_uri'] = $redirectUri;
        ActivityIntegration::saveProvider('spotify', [
            'status' => (string)($integration->status ?? 'inactive'),
            'access_token' => '',
            'refresh_token' => '',
            'expires_at' => (string)($integration->expires_at ?? ''),
            'metadata' => $metadata,
        ]);
    }

    private function redirectUri(): string
    {
        $integration = ActivityIntegration::findByProvider('spotify');
        $metadata = $integration ? $integration->metadata() : [];
        $saved = trim((string)($metadata['redirect_uri'] ?? ''));
        if ($saved !== '') {
            return $saved;
        }

        $configured = $this->env('SPOTIFY_REDIRECT_URI');
        if ($configured !== '') {
            return $configured;
        }

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = (string)($_SERVER['HTTP_HOST'] ?? '127.0.0.1:5555');
        return $scheme . '://' . $host . '/admin/oauth/spotify/callback';
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
}
