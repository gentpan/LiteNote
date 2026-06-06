<?php
declare(strict_types=1);

namespace App\Services\ActivityAdapters;

use App\Models\ActivityIntegration;

final class NeoDbAdapter extends BaseAdapter
{
    public function provider(): string
    {
        return 'neodb';
    }

    public function sync(ActivityIntegration $integration): array
    {
        $token = trim((string)$integration->access_token);
        if ($token === '') {
            throw new \RuntimeException('NeoDB Access Token 不能为空');
        }
        $baseUrl = rtrim($this->meta($integration, 'base_url', 'https://neodb.social'), '/');
        $category = $this->meta($integration, 'category', 'movie') ?: 'movie';
        $shelf = $this->meta($integration, 'shelf_type', 'complete') ?: 'complete';
        $limit = max(1, min(100, (int)$this->meta($integration, 'limit', '30')));
        $url = $baseUrl . '/api/me/shelf/' . rawurlencode($shelf) . '?category=' . rawurlencode($category) . '&page=1';
        $data = $this->http->getJson($url, ['Authorization: Bearer ' . $token], 20);
        $items = $this->extractItems($data);

        $created = $updated = $skipped = 0;
        foreach (array_slice($items, 0, $limit) as $item) {
            if (!is_array($item)) {
                $skipped++;
                continue;
            }
            $mapped = $this->mapItem($item, $category, $shelf, $baseUrl);
            if (!$mapped) {
                $skipped++;
                continue;
            }
            $this->ingest($mapped) ? $created++ : $updated++;
        }

        return $this->result($created, $updated, $skipped, 'NeoDB 同步完成');
    }

    private function extractItems(array $data): array
    {
        foreach (['data', 'results', 'items'] as $key) {
            if (is_array($data[$key] ?? null)) {
                return $data[$key];
            }
        }
        return array_is_list($data) ? $data : [];
    }

    private function mapItem(array $row, string $category, string $shelf, string $baseUrl): ?array
    {
        $item = is_array($row['item'] ?? null) ? $row['item'] : $row;
        $uuid = (string)($row['uuid'] ?? $row['id'] ?? $item['uuid'] ?? $item['id'] ?? '');
        $title = (string)($item['title'] ?? $item['display_title'] ?? $item['name'] ?? '');
        if ($uuid === '' || $title === '') {
            return null;
        }
        $year = (string)($item['year'] ?? '');
        $rating = $row['rating'] ?? $row['rating_grade'] ?? null;
        $comment = trim((string)($row['comment_text'] ?? $row['comment'] ?? $row['review'] ?? ''));
        $happenedAt = $this->isoToSql((string)($row['created_time'] ?? $row['updated_time'] ?? $row['created_at'] ?? ''));
        $url = (string)($item['url'] ?? $item['external_url'] ?? '');
        if ($url === '' && !empty($item['uuid'])) {
            $url = $baseUrl . '/book/' . rawurlencode((string)$item['uuid']);
        }
        $type = in_array($category, ['movie', 'tv', 'podcast'], true) ? 'movie' : ($category === 'music' ? 'music' : 'manual');
        $action = $shelf === 'complete' ? 'watched' : 'saved';
        return [
            'type' => $type,
            'action' => $action,
            'source' => 'neodb',
            'external_id' => 'neodb:' . $shelf . ':' . $uuid,
            'title' => ($type === 'movie' ? '看过 ' : '记录 ') . '《' . $title . ($year !== '' ? ' (' . $year . ')' : '') . '》',
            'content' => $comment,
            'url' => $url,
            'happened_at' => $happenedAt,
            'metadata' => [
                'category' => $category,
                'shelf_type' => $shelf,
                'item_title' => $title,
                'year' => $year,
                'rating' => is_numeric($rating) ? (float)$rating : null,
                'rating_max' => 5,
                'cover' => (string)($item['cover_image_url'] ?? $item['cover'] ?? ''),
                'raw' => $row,
            ],
        ];
    }

}
