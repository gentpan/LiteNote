<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\FileCache;

/**
 * 通用 IP 级操作频率限制（评论、邮箱探测等）。
 */
final class ActionRateLimiter
{
    public static function tooMany(string $scope, string $ip, int $maxAttempts, int $windowSeconds): bool
    {
        $data = self::read($scope, $ip);
        if ($data === null) {
            return false;
        }
        return (int)($data['count'] ?? 0) >= $maxAttempts;
    }

    public static function hit(string $scope, string $ip, int $maxAttempts, int $windowSeconds): void
    {
        $key = self::key($scope, $ip);
        $cache = new FileCache();
        $data = $cache->get($key);
        $now = time();
        if (!is_array($data) || $now > (int)($data['expires'] ?? 0)) {
            $data = ['count' => 0, 'expires' => $now + $windowSeconds];
        }
        $data['count'] = ((int)($data['count'] ?? 0)) + 1;
        $cache->set($key, $data);
    }

    /**
     * @return array{count:int,expires:int}|null
     */
    private static function read(string $scope, string $ip): ?array
    {
        $data = (new FileCache())->get(self::key($scope, $ip));
        if (!is_array($data)) {
            return null;
        }
        if (time() > (int)($data['expires'] ?? 0)) {
            return null;
        }
        return $data;
    }

    private static function key(string $scope, string $ip): string
    {
        return 'rate_' . $scope . '_' . md5($ip);
    }
}
