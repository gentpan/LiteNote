<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;

final class UmamiService
{
    public static function config(): array
    {
        return [
            'enabled' => (bool) Config::get('analytics.umami.enabled', false),
            'baseUrl' => rtrim(trim((string) Config::get('analytics.umami.base_url', '')), '/'),
            'websiteId' => trim((string) Config::get('analytics.umami.website_id', '')),
            'token' => trim((string) Config::get('analytics.umami.token', '')),
            'apiKey' => trim((string) Config::get('analytics.umami.api_key', '')),
            'timezone' => trim((string) Config::get('analytics.umami.timezone', 'Asia/Shanghai')) ?: 'Asia/Shanghai',
            'scriptUrl' => trim((string) Config::get('analytics.umami.script_url', '')),
        ];
    }

    public static function isConfigured(): bool
    {
        $c = self::config();
        return $c['enabled'] && $c['baseUrl'] !== '' && $c['websiteId'] !== '' && ($c['token'] !== '' || $c['apiKey'] !== '');
    }

    public static function trackingScript(): ?array
    {
        $c = self::config();
        if (!$c['enabled'] || $c['baseUrl'] === '' || $c['websiteId'] === '') {
            return null;
        }

        $src = $c['scriptUrl'] !== '' ? $c['scriptUrl'] : $c['baseUrl'] . '/script.js';
        return ['src' => $src, 'websiteId' => $c['websiteId']];
    }

    public static function dashboardStats(): array
    {
        if (!self::isConfigured()) {
            return [
                'today' => ['pv' => 0, 'uv' => 0],
                'total' => ['pv' => 0, 'uv' => 0],
                'enabled' => false,
            ];
        }

        $now = time();
        $todayStart = strtotime(date('Y-m-d 00:00:00', $now)) ?: $now;
        $monthStart = strtotime('-29 days', $todayStart) ?: $todayStart;

        try {
            $today = self::stats($todayStart * 1000, $now * 1000);
            $month = self::stats($monthStart * 1000, $now * 1000);
            return [
                'today' => [
                    'pv' => (int)($today['pageviews'] ?? 0),
                    'uv' => (int)($today['visitors'] ?? 0),
                ],
                'total' => [
                    'pv' => (int)($month['pageviews'] ?? 0),
                    'uv' => (int)($month['visitors'] ?? 0),
                ],
                'enabled' => true,
            ];
        } catch (\Throwable) {
            return [
                'today' => ['pv' => 0, 'uv' => 0],
                'total' => ['pv' => 0, 'uv' => 0],
                'enabled' => true,
            ];
        }
    }

    public static function report(int $days = 7): array
    {
        $c = self::config();
        $days = max(1, min(180, $days));
        $end = time() * 1000;
        $start = (strtotime('-' . ($days - 1) . ' days 00:00:00') ?: time()) * 1000;

        if (!self::isConfigured()) {
            return [
                'configured' => false,
                'config' => $c,
                'days' => $days,
                'error' => null,
            ];
        }

        try {
            return [
                'configured' => true,
                'config' => $c,
                'days' => $days,
                'active' => self::get('/websites/' . rawurlencode($c['websiteId']) . '/active'),
                'summary' => self::stats($start, $end),
                'pageviews' => self::get('/websites/' . rawurlencode($c['websiteId']) . '/pageviews', [
                    'startAt' => $start,
                    'endAt' => $end,
                    'unit' => $days > 31 ? 'month' : 'day',
                    'timezone' => $c['timezone'],
                ]),
                'pages' => self::metrics('path', $start, $end, 10),
                'referrers' => self::metrics('referrer', $start, $end, 10),
                'browsers' => self::metrics('browser', $start, $end, 8),
                'devices' => self::metrics('device', $start, $end, 8),
                'countries' => self::metrics('country', $start, $end, 8),
                'error' => null,
            ];
        } catch (\Throwable $e) {
            return [
                'configured' => true,
                'config' => $c,
                'days' => $days,
                'error' => $e->getMessage(),
            ];
        }
    }

    public static function stats(int $startAt, int $endAt): array
    {
        $c = self::config();
        return self::get('/websites/' . rawurlencode($c['websiteId']) . '/stats', [
            'startAt' => $startAt,
            'endAt' => $endAt,
        ]);
    }

    private static function metrics(string $type, int $startAt, int $endAt, int $limit): array
    {
        $c = self::config();
        $rows = self::get('/websites/' . rawurlencode($c['websiteId']) . '/metrics', [
            'startAt' => $startAt,
            'endAt' => $endAt,
            'type' => $type,
            'limit' => $limit,
        ]);
        return is_array($rows) ? $rows : [];
    }

    private static function get(string $path, array $params = []): array
    {
        $c = self::config();
        $apiBase = self::apiBase($c['baseUrl']);
        $url = $apiBase . $path;
        if ($params !== []) {
            $url .= '?' . http_build_query($params);
        }

        $headers = ['Accept: application/json'];
        if ($c['token'] !== '') {
            $headers[] = 'Authorization: Bearer ' . $c['token'];
        } elseif ($c['apiKey'] !== '') {
            $headers[] = 'x-umami-api-key: ' . $c['apiKey'];
        }

        $body = self::httpGet($url, $headers);
        $json = json_decode($body, true);
        if (!is_array($json)) {
            throw new \RuntimeException('Umami API 返回了无法解析的 JSON');
        }
        return $json;
    }

    private static function httpGet(string $url, array $headers): string
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_TIMEOUT => 8,
                CURLOPT_CONNECTTIMEOUT => 4,
                CURLOPT_FOLLOWLOCATION => true,
            ]);
            $body = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            unset($ch);
            if ($body === false || $status >= 400 || $status === 0) {
                throw new \RuntimeException('Umami API 请求失败: HTTP ' . $status . ($error ? ' ' . $error : ''));
            }
            return (string)$body;
        }

        $ctx = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", $headers),
                'timeout' => 8,
                'ignore_errors' => true,
            ],
        ]);
        $body = @file_get_contents($url, false, $ctx);
        if ($body === false) {
            throw new \RuntimeException('Umami API 请求失败');
        }
        return (string)$body;
    }

    private static function apiBase(string $baseUrl): string
    {
        $baseUrl = rtrim($baseUrl, '/');
        $path = parse_url($baseUrl, PHP_URL_PATH) ?: '';
        if (str_contains($baseUrl, 'api.umami.is') || preg_match('#/api(?:/|$)#', $path)) {
            return $baseUrl;
        }
        return $baseUrl . '/api';
    }
}
