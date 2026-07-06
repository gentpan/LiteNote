<?php
declare(strict_types=1);

namespace App\Core;

/**
 * HTTP 请求对象
 */
final class Request
{
    public string $method;
    public string $path;
    public string $query;
    public array $get;
    public array $post;
    public array $server;
    public array $cookies;
    public array $files;
    public string $body;
    public ?array $json = null;
    public string $ip;
    public string $ua;

    public function __construct()
    {
        $this->method  = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $this->server  = $_SERVER;
        $this->get     = $_GET;
        $this->post    = $_POST;
        $this->cookies = $_COOKIE;
        $this->files   = $_FILES;
        $this->body    = (string) file_get_contents('php://input');
        $this->ua      = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $this->ip      = $this->resolveIp();

        // 解析 path（去除 query string）
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $parts = explode('?', $uri, 2);
        $this->path  = $parts[0] ?: '/';
        $this->query = $parts[1] ?? '';

        // 如果是 JSON 请求
        $contentType = $_SERVER['CONTENT_TYPE'] ?? ($_SERVER['HTTP_CONTENT_TYPE'] ?? '');
        if (str_contains($contentType, 'application/json') && $this->body !== '') {
            $decoded = json_decode($this->body, true);
            if (is_array($decoded)) {
                $this->json = $decoded;
            }
        }
    }

    private function resolveIp(): string
    {
        $remote = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
        $trusted = Config::get('app.trusted_proxies', []);
        if ($remote !== '' && is_array($trusted) && self::isTrustedProxy($remote, $trusted)) {
            foreach (['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP'] as $key) {
                if (empty($_SERVER[$key])) {
                    continue;
                }
                $ip = trim(explode(',', (string) $_SERVER[$key])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        if ($remote !== '' && filter_var($remote, FILTER_VALIDATE_IP)) {
            return $remote;
        }

        return '0.0.0.0';
    }

    /**
     * @param list<string> $trusted
     */
    private static function isTrustedProxy(string $remote, array $trusted): bool
    {
        if ($trusted === []) {
            return false;
        }
        foreach ($trusted as $entry) {
            if ($entry === '*' || $entry === $remote) {
                return true;
            }
        }
        return false;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->post)) return $this->post[$key];
        if (array_key_exists($key, $this->get))  return $this->get[$key];
        if (is_array($this->json) && array_key_exists($key, $this->json)) {
            return $this->json[$key];
        }
        return $default;
    }

    public function isPost(): bool  { return $this->method === 'POST'; }
    public function isGet(): bool   { return $this->method === 'GET'; }
    public function isAjax(): bool  { return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest'; }

    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return $_SERVER[$key] ?? null;
    }

    public function bearerToken(): ?string
    {
        $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/Bearer\s+(\S+)/', $auth, $m)) {
            return $m[1];
        }
        return null;
    }
}
