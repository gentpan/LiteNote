<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Session 封装
 */
final class Session
{
    private const DEFAULT_LIFETIME = 2592000;

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            $lifetime = self::lifetime();
            $secure = self::isSecureRequest();

            // 安全设置
            ini_set('session.use_strict_mode', '1');
            ini_set('session.cookie_httponly', '1');
            ini_set('session.cookie_samesite', 'Lax');
            ini_set('session.gc_maxlifetime', (string)$lifetime);
            session_set_cookie_params([
                'lifetime' => $lifetime,
                'path' => '/',
                'domain' => '',
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_name('BLOGSID');
            session_start();
        }
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        // 支持点号路径,如 'admin_user.id' → $_SESSION['admin_user']['id']
        if (str_contains($key, '.')) {
            return self::dotGet($_SESSION, $key, $default);
        }
        return $_SESSION[$key] ?? $default;
    }

    public static function has(string $key): bool
    {
        if (str_contains($key, '.')) {
            return self::dotGet($_SESSION, $key, '__MISSING__') !== '__MISSING__';
        }
        return isset($_SESSION[$key]);
    }

    public static function forget(string $key): void
    {
        if (str_contains($key, '.')) {
            [$head, $tail] = explode('.', $key, 2);
            if (isset($_SESSION[$head]) && is_array($_SESSION[$head])) {
                self::dotUnset($_SESSION[$head], $tail);
            }
            return;
        }
        unset($_SESSION[$key]);
    }

    /**
     * 内部:点号路径取值,如 dotGet($arr, 'a.b.c', $default)
     */
    private static function dotGet(array $arr, string $path, mixed $default = null): mixed
    {
        $segments = explode('.', $path);
        foreach ($segments as $seg) {
            if (!is_array($arr) || !array_key_exists($seg, $arr)) {
                return $default;
            }
            $arr = $arr[$seg];
        }
        return $arr;
    }

    /**
     * 内部:点号路径删除
     */
    private static function dotUnset(array &$arr, string $path): void
    {
        $segments = explode('.', $path);
        $last = array_pop($segments);
        foreach ($segments as $seg) {
            if (!isset($arr[$seg]) || !is_array($arr[$seg])) {
                return;
            }
            $arr = &$arr[$seg];
        }
        unset($arr[$last]);
    }

    public static function flash(string $key, mixed $value): void
    {
        $_SESSION['_flash'][$key] = $value;
    }

    public static function getFlash(string $key, mixed $default = null): mixed
    {
        $v = $_SESSION['_flash'][$key] ?? $default;
        unset($_SESSION['_flash'][$key]);
        return $v;
    }

    /**
     * 检查 flash 是否存在（只读不删）。
     */
    public static function hasFlash(string $key): bool
    {
        return isset($_SESSION['_flash'][$key]);
    }

    /**
     * 读取 flash 但不删除（用于先判断再输出场景）。
     */
    public static function peekFlash(string $key, mixed $default = null): mixed
    {
        return $_SESSION['_flash'][$key] ?? $default;
    }

    public static function regenerate(): void
    {
        session_regenerate_id(true);
        self::refreshCookie();
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    public static function refreshCookie(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE || headers_sent()) {
            return;
        }

        $p = session_get_cookie_params();
        setcookie(session_name(), session_id(), [
            'expires' => time() + self::lifetime(),
            'path' => $p['path'] ?: '/',
            'domain' => $p['domain'] ?: '',
            'secure' => (bool)$p['secure'],
            'httponly' => true,
            'samesite' => $p['samesite'] ?? 'Lax',
        ]);
    }

    public static function csrfToken(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public static function verifyCsrf(?string $token): bool
    {
        return is_string($token) && hash_equals($_SESSION['_csrf'] ?? '', $token);
    }

    private static function lifetime(): int
    {
        $value = (int)(getenv('BLOG_SESSION_LIFETIME') ?: self::DEFAULT_LIFETIME);
        return $value > 0 ? $value : self::DEFAULT_LIFETIME;
    }

    private static function isSecureRequest(): bool
    {
        $proto = strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
        return $proto === 'https' || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    }
}
