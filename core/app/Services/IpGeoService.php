<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\FileCache;
use App\Core\Http;

final class IpGeoService
{
    public static function lookup(string $ip): array
    {
        $ip = trim($ip);
        if ($ip === '' || !filter_var($ip, FILTER_VALIDATE_IP) || self::isPrivateIp($ip)) {
            return [];
        }

        // 同一 IP 的归属地 30 天内只查一次外部 API,其余命中文件缓存。
        $cache = new FileCache();
        $cacheKey = 'geoip/' . md5($ip);
        $cached = $cache->get($cacheKey, null, 86400 * 30);
        if (is_array($cached)) {
            return $cached;
        }

        $json = self::httpGet('https://api.cnip.io/geoip/' . rawurlencode($ip));
        if ($json === '') {
            return [];
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
            return [];
        }

        $code = strtoupper(trim((string)($data['country_code'] ?? '')));
        $result = [
            'geo_country_code' => preg_match('/^[A-Z]{2}$/', $code) ? $code : '',
            'geo_country' => trim((string)($data['country'] ?? '')),
            'geo_region' => trim((string)($data['province'] ?? $data['region'] ?? '')),
            'geo_city' => trim((string)($data['city'] ?? '')),
            'geo_data' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];
        $cache->set($cacheKey, $result);
        return $result;
    }

    public static function flagUrl(?string $countryCode): string
    {
        $code = strtolower(trim((string)$countryCode));
        return preg_match('/^[a-z]{2}$/', $code) ? 'https://flagcdn.io/' . $code . '.svg' : '';
    }

    public static function locationLabel(array|object $comment): string
    {
        $get = static function (string $key) use ($comment): string {
            return trim((string)(is_array($comment) ? ($comment[$key] ?? '') : ($comment->{$key} ?? '')));
        };
        $city = $get('geo_city');
        if ($city !== '') {
            return $city;
        }
        $region = $get('geo_region');
        if ($region !== '') {
            return $region;
        }
        return $get('geo_country');
    }

    public static function frontLocationLabel(array|object $comment): string
    {
        $get = static function (string $key) use ($comment): string {
            return trim((string)(is_array($comment) ? ($comment[$key] ?? '') : ($comment->{$key} ?? '')));
        };
        $countryCode = strtoupper($get('geo_country_code'));
        if ($countryCode === 'CN') {
            $city = $get('geo_city');
            if ($city !== '') {
                return $city;
            }
            return $get('geo_region');
        }
        return $get('geo_country');
    }

    private static function httpGet(string $url): string
    {
        $r = Http::request('GET', $url, [
            'headers' => ['User-Agent: LiteNote GeoIP/1.0'],
            'connect_timeout' => 3,
            'timeout' => 5,
        ]);
        return ($r['status'] >= 200 && $r['status'] < 400) ? $r['body'] : '';
    }

    private static function isPrivateIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }
}
