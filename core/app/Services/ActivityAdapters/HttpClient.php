<?php
declare(strict_types=1);

namespace App\Services\ActivityAdapters;

use App\Core\Http;

/**
 * Adapter 专用 HTTP 客户端。
 *
 * 现已成为通用 App\Core\Http 的薄封装,保留实例方法签名以便各 Adapter 注入复用,
 * 实际请求逻辑统一收敛到 Core\Http(curl 优先、stream 兜底)。
 */
final class HttpClient
{
    public function getJson(string $url, array $headers = [], int $timeout = 15): array
    {
        return Http::getJson($url, $headers, $timeout);
    }

    public function postForm(string $url, array $fields, array $headers = [], int $timeout = 15): array
    {
        return Http::postForm($url, $fields, $headers, $timeout);
    }

    public function getText(string $url, array $headers = [], int $timeout = 15): string
    {
        return Http::getText($url, $headers, $timeout);
    }
}
