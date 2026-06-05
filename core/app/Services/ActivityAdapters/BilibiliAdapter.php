<?php
declare(strict_types=1);

namespace App\Services\ActivityAdapters;

use App\Models\ActivityIntegration;

final class BilibiliAdapter extends BaseAdapter
{
    public function provider(): string
    {
        return 'bilibili';
    }

    public function sync(ActivityIntegration $integration): array
    {
        $feedUrl = $this->meta($integration, 'feed_url');
        if ($feedUrl === '') {
            throw new \RuntimeException('请填写 Bilibili 公开 RSS/JSON Feed 地址');
        }
        $body = $this->http->getText($feedUrl, ['Accept: application/rss+xml,application/json,text/xml,*/*'], 20);
        if ($body === '') {
            return $this->result(0, 0, 0, 'Bilibili Feed 没有返回数据');
        }

        $items = str_starts_with(ltrim($body), '{') || str_starts_with(ltrim($body), '[')
            ? $this->jsonItems($body)
            : $this->rssItems($body);

        $created = $updated = $skipped = 0;
        foreach ($items as $item) {
            $title = trim((string)($item['title'] ?? ''));
            $url = trim((string)($item['url'] ?? ''));
            if ($title === '' || $url === '') {
                $skipped++;
                continue;
            }
            $externalId = 'bilibili:' . sha1($url);
            $before = $this->exists('bilibili', $externalId);
            $this->activities->record([
                'type' => 'video',
                'action' => 'watched',
                'source' => 'bilibili',
                'external_id' => $externalId,
                'title' => 'B 站动态：' . $title,
                'content' => (string)($item['content'] ?? ''),
                'url' => $url,
                'happened_at' => $this->isoToSql((string)($item['date'] ?? '')),
                'metadata' => ['feed_url' => $feedUrl],
            ]);
            $before ? $updated++ : $created++;
        }

        return $this->result($created, $updated, $skipped, 'Bilibili Feed 同步完成');
    }

    private function jsonItems(string $body): array
    {
        $data = json_decode($body, true);
        if (!is_array($data)) {
            return [];
        }
        $rows = is_array($data['items'] ?? null) ? $data['items'] : (array_is_list($data) ? $data : []);
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $out[] = [
                'title' => $row['title'] ?? '',
                'url' => $row['url'] ?? $row['link'] ?? '',
                'content' => $row['content_html'] ?? $row['content_text'] ?? $row['summary'] ?? '',
                'date' => $row['date_published'] ?? $row['published'] ?? '',
            ];
        }
        return $out;
    }

    private function rssItems(string $body): array
    {
        $xml = @simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NOCDATA);
        if (!$xml) {
            return [];
        }
        $nodes = $xml->channel->item ?? $xml->entry ?? [];
        $out = [];
        foreach ($nodes as $node) {
            $link = (string)($node->link['href'] ?? $node->link ?? '');
            $out[] = [
                'title' => (string)($node->title ?? ''),
                'url' => $link,
                'content' => (string)($node->description ?? $node->summary ?? $node->content ?? ''),
                'date' => (string)($node->pubDate ?? $node->published ?? $node->updated ?? ''),
            ];
        }
        return $out;
    }

    private function exists(string $source, string $externalId): bool
    {
        return (bool)\App\Models\Activity::db()->fetchOne(
            'SELECT id FROM activities WHERE source = ? AND external_id = ? LIMIT 1',
            [$source, $externalId]
        );
    }
}
