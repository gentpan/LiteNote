<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\FileCache;
use App\Core\Http;

final class WeatherService
{
    public function currentByCoordinates(float $lat, float $lng, string $place = ''): array
    {
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            return [];
        }

        $cacheKey = 'weather/' . md5(round($lat, 3) . ',' . round($lng, 3));
        $cache = new FileCache();
        $cached = $cache->get($cacheKey, null, 600);
        if (is_array($cached)) {
            if ($place !== '') {
                $cached['place'] = $place;
            }
            return $cached;
        }

        $query = http_build_query([
            'latitude' => $lat,
            'longitude' => $lng,
            'current' => 'temperature_2m,weather_code,is_day',
            'timezone' => 'auto',
        ]);
        $payload = Http::getJson('https://api.open-meteo.com/v1/forecast?' . $query, [
            'User-Agent: LiteNote Weather/1.0',
        ], 8);
        if (!is_array($payload['current'] ?? null)) {
            return [];
        }

        $current = $payload['current'];
        $code = (int)($current['weather_code'] ?? -1);
        $isDay = (int)($current['is_day'] ?? 1) === 1;
        $mapped = self::weatherCode($code, $isDay);
        $temp = (float)($current['temperature_2m'] ?? 0);
        $result = [
            'label' => $mapped['label'],
            'icon' => $mapped['icon'],
            'temperature' => round($temp, 1),
            'code' => $code,
            'is_day' => $isDay ? 1 : 0,
            'place' => $place,
            'source' => 'open-meteo',
            'fetched_at' => date('c'),
        ];
        $cache->set($cacheKey, $result);
        return $result;
    }

    public function currentByIp(string $ip): array
    {
        $geo = IpGeoService::lookup($ip);
        $data = json_decode((string)($geo['geo_data'] ?? ''), true);
        if (!is_array($data)) {
            return [];
        }

        $lat = $data['latitude'] ?? $data['lat'] ?? null;
        $lng = $data['longitude'] ?? $data['lng'] ?? $data['lon'] ?? null;
        if (!is_numeric($lat) || !is_numeric($lng)) {
            return [];
        }

        $place = trim((string)($geo['geo_city'] ?? $geo['geo_region'] ?? ''));
        return $this->currentByCoordinates((float)$lat, (float)$lng, $place);
    }

    public static function weatherCode(int $code, bool $isDay = true): array
    {
        return match (true) {
            $code === 0 => ['label' => '晴', 'icon' => $isDay ? 'fa-solid fa-sun' : 'fa-solid fa-moon'],
            in_array($code, [1, 2], true) => ['label' => '少云', 'icon' => $isDay ? 'fa-solid fa-cloud-sun' : 'fa-solid fa-cloud-moon'],
            $code === 3 => ['label' => '多云', 'icon' => 'fa-solid fa-cloud'],
            in_array($code, [45, 48], true) => ['label' => '雾', 'icon' => 'fa-solid fa-smog'],
            in_array($code, [51, 53, 55, 56, 57], true) => ['label' => '毛毛雨', 'icon' => 'fa-solid fa-cloud-rain'],
            in_array($code, [61, 63, 65, 66, 67, 80, 81, 82], true) => ['label' => '雨', 'icon' => 'fa-solid fa-cloud-showers-heavy'],
            in_array($code, [71, 73, 75, 77, 85, 86], true) => ['label' => '雪', 'icon' => 'fa-solid fa-snowflake'],
            in_array($code, [95, 96, 99], true) => ['label' => '雷雨', 'icon' => 'fa-solid fa-cloud-bolt'],
            default => ['label' => '天气', 'icon' => 'fa-solid fa-cloud'],
        };
    }
}
