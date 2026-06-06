<?php
declare(strict_types=1);

namespace App\Services;

/**
 * 友链 RSS 抓取服务
 * - 抓取友链的 RSS feed
 * - 解析最新文章
 * - 用于 /links 页显示
 */
final class FriendRssService
{
    private const AGGREGATE_RETENTION_DAYS = 30;

    /**
     * 抓取单个友链的最新文章
     * @return array<int, array{title:string,link:string,pubDate:string,description:string}>
     */
    public static function fetch(string $rssUrl, int $limit = 5, int $cacheTtl = 21600, bool $force = false): array
    {
        return self::fetchResult($rssUrl, $limit, $cacheTtl, $force)['items'];
    }

    /**
     * 抓取并返回诊断信息,用于后台显示失败原因。
     * @return array{ok:bool, items:array<int, array{title:string,link:string,pubDate:string,description:string}>, error:string, from_cache:bool, http_code:int|null}
     */
    public static function fetchResult(string $rssUrl, int $limit = 5, int $cacheTtl = 21600, bool $force = false): array
    {
        $rssUrl = trim($rssUrl);
        if ($rssUrl === '') {
            return self::result(false, [], 'RSS 地址为空');
        }
        if (!filter_var($rssUrl, FILTER_VALIDATE_URL) || !preg_match('~^https?://~i', $rssUrl)) {
            return self::result(false, [], 'RSS 地址格式无效');
        }

        $key = md5($rssUrl);
        $cacheFile = self::cacheDir() . '/friend_' . $key . '.json';
        self::ensureCacheDir();

        // 缓存命中
        if (!$force && is_file($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTtl) {
            $data = json_decode((string)file_get_contents($cacheFile), true);
            if (is_array($data)) {
                return self::result(count($data) > 0, $data, count($data) > 0 ? '' : '缓存中没有解析到文章', true);
            }
        }

        $items = [];
        try {
            $ctx = stream_context_create([
                'http' => [
                    'timeout' => 8,
                    'user_agent' => 'LiteNote-FriendRss/1.0',
                    'ignore_errors' => true,
                ],
                'https' => [
                    'timeout' => 8,
                    'user_agent' => 'LiteNote-FriendRss/1.0',
                    'ignore_errors' => true,
                ],
            ]);
            $content = @file_get_contents($rssUrl, false, $ctx);
            $httpCode = self::httpStatusCode($http_response_header ?? []);
            if ($content === false) {
                return self::result(false, [], '无法连接或请求超时', false, $httpCode);
            }
            if ($httpCode !== null && ($httpCode < 200 || $httpCode >= 400)) {
                return self::result(false, [], 'HTTP 状态码 ' . $httpCode, false, $httpCode);
            }
            if (trim($content) === '') {
                return self::result(false, [], 'RSS 响应为空', false, $httpCode);
            }
            $items = self::parseRss($content, $limit);
            if (!$items) {
                $errors = libxml_get_errors();
                libxml_clear_errors();
                $reason = $errors ? trim($errors[0]->message) : '没有解析到文章条目';
                return self::result(false, [], $reason, false, $httpCode);
            }
        } catch (\Throwable $e) {
            return self::result(false, [], $e->getMessage() ?: 'RSS 抓取失败');
        }

        @file_put_contents($cacheFile, json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return self::result(true, $items, '', false, $httpCode ?? null);
    }

    /**
     * 只读取已有 RSS 缓存,不发起任何外部请求。
     * 后台列表页使用它避免进入页面时被慢 RSS 拖住。
     */
    public static function cachedResult(string $rssUrl): array
    {
        $rssUrl = trim($rssUrl);
        if ($rssUrl === '') {
            return self::result(false, [], 'RSS 地址为空', true);
        }
        if (!filter_var($rssUrl, FILTER_VALIDATE_URL) || !preg_match('~^https?://~i', $rssUrl)) {
            return self::result(false, [], 'RSS 地址格式无效', true);
        }

        $cacheFile = self::cacheDir() . '/friend_' . md5($rssUrl) . '.json';
        if (!is_file($cacheFile)) {
            return self::result(false, [], '还没有刷新缓存，请点击刷新 RSS', true);
        }

        $data = json_decode((string)file_get_contents($cacheFile), true);
        if (!is_array($data)) {
            return self::result(false, [], 'RSS 缓存文件无效，请重新刷新', true);
        }

        return self::result(count($data) > 0, $data, count($data) > 0 ? '' : '缓存中没有解析到文章', true);
    }

    /**
     * 简易 RSS 解析（不引第三方库）
     */
    public static function parseRss(string $xml, int $limit = 5): array
    {
        // 去除 BOM
        $xml = preg_replace('/^\xEF\xBB\xBF/', '', $xml);
        libxml_use_internal_errors(true);
        libxml_clear_errors();
        $doc = new \SimpleXMLElement($xml);
        $items = [];

        // RSS 2.0
        if (isset($doc->channel)) {
            foreach ($doc->channel->item as $item) {
                if (count($items) >= $limit) break;
                $items[] = [
                    'title'       => trim((string)$item->title),
                    'link'        => trim((string)$item->link),
                    'pubDate'     => trim((string)$item->pubDate),
                    'description' => trim(strip_tags((string)$item->description)),
                ];
            }
        }
        // Atom
        elseif (isset($doc->entry)) {
            foreach ($doc->entry as $entry) {
                if (count($items) >= $limit) break;
                $link = '';
                if (isset($entry->link['href'])) {
                    $link = (string)$entry->link['href'];
                } elseif (isset($entry->link)) {
                    $link = (string)$entry->link;
                }
                $items[] = [
                    'title'       => trim((string)$entry->title),
                    'link'        => $link,
                    'pubDate'     => trim((string)($entry->updated ?? $entry->published ?? '')),
                    'description' => trim(strip_tags((string)$entry->summary)),
                ];
            }
        }

        return $items;
    }

    private static function result(bool $ok, array $items, string $error = '', bool $fromCache = false, ?int $httpCode = null): array
    {
        return [
            'ok' => $ok,
            'items' => $items,
            'error' => $error,
            'from_cache' => $fromCache,
            'http_code' => $httpCode,
        ];
    }

    private static function httpStatusCode(array $headers): ?int
    {
        foreach ($headers as $header) {
            if (preg_match('~^HTTP/\S+\s+(\d{3})~i', (string)$header, $match)) {
                return (int)$match[1];
            }
        }
        return null;
    }

    public static function aggregate(int $perFriend = 3, int $totalLimit = 30): array
    {
        $payload = self::readAggregateCache();
        $items = self::filterRetainedItems($payload['items'] ?? []);
        if (!is_array($items)) {
            return [];
        }
        return array_slice($items, 0, $totalLimit);
    }

    public static function refreshAggregate(int $perFriend = 5, int $totalLimit = 50, bool $force = true): array
    {
        $payload = self::readAggregateCache();
        $merged = [];
        foreach (self::filterRetainedItems($payload['items'] ?? []) as $item) {
            $merged[self::itemKey($item)] = $item;
        }

        $links = \App\Models\Link::withRss();
        foreach ($links as $link) {
            $items = self::fetch((string)$link->rss_url, $perFriend, 21600, $force);
            foreach ($items as $item) {
                $item['friend_name'] = $link->name;
                $item['friend_url']  = $link->url;
                $item['fetched_at'] = time();
                $item['published_ts'] = self::publishedTimestamp($item);
                $merged[self::itemKey($item)] = $item;
            }
        }

        $all = self::filterRetainedItems(array_values($merged));

        // 按发布时间倒序；没有发布时间时按抓取时间倒序
        usort($all, function ($a, $b) {
            $ta = self::sortTimestamp($a);
            $tb = self::sortTimestamp($b);
            return $tb <=> $ta;
        });

        self::writeAggregateCache($all);
        return array_slice($all, 0, $totalLimit);
    }

    public static function lastUpdated(): ?int
    {
        $payload = self::readAggregateCache();
        $time = (int)($payload['updated_at'] ?? 0);
        return $time > 0 ? $time : null;
    }

    private static function readAggregateCache(): array
    {
        $file = self::aggregateFile();
        if (!is_file($file)) {
            return [];
        }
        $payload = json_decode((string)file_get_contents($file), true);
        return is_array($payload) ? $payload : [];
    }

    private static function writeAggregateCache(array $items): void
    {
        self::ensureCacheDir();
        @file_put_contents(self::aggregateFile(), json_encode([
            'updated_at' => time(),
            'updated_at_iso' => date(DATE_ATOM),
            'retention_days' => self::AGGREGATE_RETENTION_DAYS,
            'item_count' => count($items),
            'items' => array_values($items),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private static function filterRetainedItems(mixed $items): array
    {
        if (!is_array($items)) {
            return [];
        }

        $cutoff = time() - self::AGGREGATE_RETENTION_DAYS * 86400;
        $retained = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            if (!isset($item['published_ts'])) {
                $item['published_ts'] = self::publishedTimestamp($item);
            }
            $timestamp = self::sortTimestamp($item);
            if ($timestamp === 0 || $timestamp >= $cutoff) {
                $retained[] = $item;
            }
        }
        return $retained;
    }

    private static function itemKey(array $item): string
    {
        $link = trim((string)($item['link'] ?? ''));
        if ($link !== '') {
            return 'link:' . $link;
        }
        return 'hash:' . md5(
            (string)($item['friend_name'] ?? '') . '|' .
            (string)($item['title'] ?? '') . '|' .
            (string)($item['pubDate'] ?? '')
        );
    }

    private static function publishedTimestamp(array $item): int
    {
        $time = strtotime((string)($item['pubDate'] ?? ''));
        return $time === false ? 0 : $time;
    }

    private static function sortTimestamp(array $item): int
    {
        $published = (int)($item['published_ts'] ?? 0);
        if ($published > 0) {
            return $published;
        }
        return (int)($item['fetched_at'] ?? 0);
    }

    private static function ensureCacheDir(): void
    {
        $dir = self::cacheDir();
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
    }

    private static function cacheDir(): string
    {
        return dirname(__DIR__, 3) . '/runtime/storage/cache';
    }

    private static function aggregateFile(): string
    {
        return self::cacheDir() . '/friend_rss_aggregate.json';
    }

}
