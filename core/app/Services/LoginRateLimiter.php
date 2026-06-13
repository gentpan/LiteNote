<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\FileCache;

/**
 * 基于文件缓存的后台登录频率限制。
 */
final class LoginRateLimiter
{
    private const MAX_ATTEMPTS = 5;
    private const DECAY_MINUTES = 15;

    public static function decayMinutes(): int
    {
        return self::DECAY_MINUTES;
    }

    public static function tooManyAttempts(string $ip, string $username): bool
    {
        $key = self::key($ip, $username);
        $cache = new FileCache();
        $data = $cache->get($key);
        if (!is_array($data)) {
            return false;
        }
        $attempts = (int)($data['attempts'] ?? 0);
        $expires = (int)($data['expires'] ?? 0);
        if (time() > $expires) {
            return false;
        }
        return $attempts >= self::MAX_ATTEMPTS;
    }

    public static function recordFailure(string $ip, string $username): void
    {
        $key = self::key($ip, $username);
        $cache = new FileCache();
        $data = $cache->get($key);
        if (!is_array($data) || time() > (int)($data['expires'] ?? 0)) {
            $data = ['attempts' => 0, 'expires' => time() + self::DECAY_MINUTES * 60];
        }
        $data['attempts'] = ($data['attempts'] ?? 0) + 1;
        $cache->set($key, $data);
    }

    public static function clear(string $ip, string $username): void
    {
        (new FileCache())->forget(self::key($ip, $username));
    }

    private static function key(string $ip, string $username): string
    {
        return 'login_attempts_' . md5($ip . '|' . strtolower($username));
    }
}
