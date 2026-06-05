<?php
declare(strict_types=1);

namespace App\Services;

final class IpGeoService
{
    public static function lookup(string $ip): array
    {
        $ip = trim($ip);
        if ($ip === '' || !filter_var($ip, FILTER_VALIDATE_IP) || self::isPrivateIp($ip)) {
            return [];
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
        return [
            'geo_country_code' => preg_match('/^[A-Z]{2}$/', $code) ? $code : '',
            'geo_country' => trim((string)($data['country'] ?? '')),
            'geo_region' => trim((string)($data['province'] ?? $data['region'] ?? '')),
            'geo_city' => trim((string)($data['city'] ?? '')),
            'geo_data' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];
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
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_TIMEOUT => 5,
                CURLOPT_USERAGENT => 'LiteNote GeoIP/1.0',
            ]);
            $body = curl_exec($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            unset($ch);
            return is_string($body) && $status >= 200 && $status < 400 ? $body : '';
        }

        $context = stream_context_create([
            'http' => [
                'timeout' => 5,
                'header' => "User-Agent: LiteNote GeoIP/1.0\r\n",
            ],
        ]);
        $body = @file_get_contents($url, false, $context);
        return is_string($body) ? $body : '';
    }

    private static function isPrivateIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }
}
