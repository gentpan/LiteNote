<?php
declare(strict_types=1);

namespace App\Core;

/**
 * 统一 HTTP 客户端。
 *
 * 取代项目里各 Service 手写的零散 curl / file_get_contents 实现:
 * curl 优先、stream 兜底,统一超时(含 connect 超时)与状态码处理。
 */
final class Http
{
    /**
     * 低层请求。返回 ['status'=>int, 'body'=>string, 'ok'=>bool, 'error'=>string]。
     *
     * options:
     *   headers          string[]  额外请求头
     *   body             ?string   请求体(已编码)
     *   timeout          int       总超时秒,默认 15
     *   connect_timeout  int       连接超时秒,默认同 timeout
     *   follow           bool      是否跟随 3xx,默认 true
     *   default_headers  bool      是否附带默认 Accept/UA,默认 true
     */
    public static function request(string $method, string $url, array $options = []): array
    {
        $headers = (array)($options['headers'] ?? []);
        $body = $options['body'] ?? null;
        $timeout = (int)($options['timeout'] ?? 15);
        $connectTimeout = (int)($options['connect_timeout'] ?? $timeout);
        $follow = (bool)($options['follow'] ?? true);

        if (($options['default_headers'] ?? true) === true) {
            $headers = array_merge([
                'Accept: application/json,text/plain,*/*',
                'User-Agent: LiteNote/1.0',
            ], $headers);
        }

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => $follow,
                CURLOPT_CONNECTTIMEOUT => $connectTimeout,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_HTTPHEADER => $headers,
            ]);
            if ($body !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            }
            $response = curl_exec($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $error = curl_error($ch);
            $bodyStr = is_string($response) ? $response : '';
            return [
                'status' => $status,
                'body' => $bodyStr,
                'ok' => $status >= 200 && $status < 300,
                'error' => is_string($response) ? '' : ($error !== '' ? $error : '请求失败'),
            ];
        }

        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'timeout' => $timeout,
                'ignore_errors' => true,
                'header' => implode("\r\n", $headers) . "\r\n",
                'content' => $body ?? '',
            ],
        ]);
        $response = @file_get_contents($url, false, $context);
        $status = 0;
        foreach (($http_response_header ?? []) as $h) {
            if (preg_match('~^HTTP/\S+\s+(\d+)~', $h, $m)) {
                $status = (int)$m[1];
            }
        }
        $bodyStr = is_string($response) ? $response : '';
        if ($status === 0 && $bodyStr !== '') {
            $status = 200;
        }
        return [
            'status' => $status,
            'body' => $bodyStr,
            'ok' => $status >= 200 && $status < 300,
            'error' => $response === false ? '请求失败' : '',
        ];
    }

    /** GET 返回正文字符串;非 2xx 返回 ''。 */
    public static function getText(string $url, array $headers = [], int $timeout = 15): string
    {
        $r = self::request('GET', $url, ['headers' => $headers, 'timeout' => $timeout]);
        return $r['ok'] ? $r['body'] : '';
    }

    /** GET 返回解码后的 JSON 数组;失败返回 []。 */
    public static function getJson(string $url, array $headers = [], int $timeout = 15): array
    {
        $body = self::getText($url, $headers, $timeout);
        if ($body === '') {
            return [];
        }
        $data = json_decode($body, true);
        return is_array($data) ? $data : [];
    }

    /** POST 表单(application/x-www-form-urlencoded),返回解码后的 JSON 数组。 */
    public static function postForm(string $url, array $fields, array $headers = [], int $timeout = 15): array
    {
        $r = self::request('POST', $url, [
            'headers' => array_merge(['Content-Type: application/x-www-form-urlencoded'], $headers),
            'body' => http_build_query($fields),
            'timeout' => $timeout,
        ]);
        if (!$r['ok'] || $r['body'] === '') {
            return [];
        }
        $data = json_decode($r['body'], true);
        return is_array($data) ? $data : [];
    }

    /**
     * POST JSON。返回完整结构 ['status','body','ok','error'],便于调用方做错误诊断。
     * 默认附带 Content-Type: application/json。
     */
    public static function postJson(string $url, array $payload, array $headers = [], int $timeout = 15): array
    {
        return self::request('POST', $url, [
            'headers' => array_merge(['Content-Type: application/json; charset=utf-8'], $headers),
            'body' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'timeout' => $timeout,
        ]);
    }

    /**
     * 下载二进制资源(封面/附件等),返回原始字节。
     * 非 2xx、空响应或超过 $maxBytes(>0 时)抛 RuntimeException。
     * 不附带默认 Accept/UA,完全使用调用方传入的 $headers。
     */
    public static function download(string $url, int $maxBytes = 0, array $headers = [], int $timeout = 15): string
    {
        $r = self::request('GET', $url, [
            'headers' => $headers,
            'timeout' => $timeout,
            'default_headers' => false,
        ]);
        if (!$r['ok'] || $r['body'] === '') {
            throw new \RuntimeException('远程资源下载失败');
        }
        if ($maxBytes > 0 && strlen($r['body']) > $maxBytes) {
            throw new \RuntimeException('远程资源太大');
        }
        return $r['body'];
    }
}
