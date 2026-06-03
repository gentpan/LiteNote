<?php
declare(strict_types=1);

namespace App\Services;

/**
 * 友链 RSS 抓取服务
 * - 抓取友链的 RSS feed
 * - 解析最新文章
 * - 用于 /friends 页显示
 */
final class FriendRssService
{
    private const CACHE_DIR = __DIR__ . '/../../storage/cache';
    private const AGGREGATE_FILE = self::CACHE_DIR . '/friend_rss_aggregate.json';

    /**
     * 抓取单个友链的最新文章
     * @return array<int, array{title:string,link:string,pubDate:string,description:string}>
     */
    public static function fetch(string $rssUrl, int $limit = 5, int $cacheTtl = 21600, bool $force = false): array
    {
        $key = md5($rssUrl);
        $cacheFile = self::CACHE_DIR . '/friend_' . $key . '.json';
        self::ensureCacheDir();

        // 缓存命中
        if (!$force && is_file($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTtl) {
            $data = json_decode((string)file_get_contents($cacheFile), true);
            if (is_array($data)) return $data;
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
            if ($content !== false && $content !== '') {
                $items = self::parseRss($content, $limit);
            }
        } catch (\Throwable) {
            // 网络错误
        }

        @file_put_contents($cacheFile, json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return $items;
    }

    /**
     * 简易 RSS 解析（不引第三方库）
     */
    public static function parseRss(string $xml, int $limit = 5): array
    {
        // 去除 BOM
        $xml = preg_replace('/^\xEF\xBB\xBF/', '', $xml);
        libxml_use_internal_errors(true);
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

    public static function aggregate(int $perFriend = 3, int $totalLimit = 30): array
    {
        $payload = self::readAggregateCache();
        $items = $payload['items'] ?? [];
        if (!is_array($items)) {
            return [];
        }
        return array_slice($items, 0, $totalLimit);
    }

    public static function refreshAggregate(int $perFriend = 5, int $totalLimit = 50): array
    {
        $all = [];
        $links = \App\Models\Link::withRss();
        foreach ($links as $link) {
            $items = self::fetch((string)$link->rss_url, $perFriend, 21600, true);
            foreach ($items as $item) {
                $item['friend_name'] = $link->name;
                $item['friend_url']  = $link->url;
                $all[] = $item;
            }
        }
        // 按 pubDate 倒序
        usort($all, function ($a, $b) {
            $ta = strtotime($a['pubDate'] ?? '') ?: 0;
            $tb = strtotime($b['pubDate'] ?? '') ?: 0;
            return $tb <=> $ta;
        });
        $all = array_slice($all, 0, $totalLimit);
        self::writeAggregateCache($all);
        return $all;
    }

    public static function lastUpdated(): ?int
    {
        $payload = self::readAggregateCache();
        $time = (int)($payload['updated_at'] ?? 0);
        return $time > 0 ? $time : null;
    }

    private static function readAggregateCache(): array
    {
        if (!is_file(self::AGGREGATE_FILE)) {
            return [];
        }
        $payload = json_decode((string)file_get_contents(self::AGGREGATE_FILE), true);
        return is_array($payload) ? $payload : [];
    }

    private static function writeAggregateCache(array $items): void
    {
        self::ensureCacheDir();
        @file_put_contents(self::AGGREGATE_FILE, json_encode([
            'updated_at' => time(),
            'items' => $items,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private static function ensureCacheDir(): void
    {
        if (!is_dir(self::CACHE_DIR)) {
            @mkdir(self::CACHE_DIR, 0775, true);
        }
    }
}
