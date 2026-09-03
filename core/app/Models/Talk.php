<?php
declare(strict_types=1);

namespace App\Models;

use App\Enums\Toggle;

/**
 * Talk 模型（改进版）
 * 变更点：
 * 1. 默认查询条件改用 Toggle enum
 * 2. 保留旧 music 字段解析方法，便于历史数据迁移和兼容
 */
final class Talk extends Model
{
    protected static string $table = 'talk';
    protected static array $sortable = ['id', 'created_at', 'published_at', 'likes_count', 'comments_count'];
    private static bool $locationSchemaChecked = false;

    public static function ensureLocationSchema(): void
    {
        if (self::$locationSchemaChecked) {
            return;
        }
        self::$locationSchemaChecked = true;

        foreach ([
            ['location_name', 'VARCHAR(160)'],
            ['location_city', 'VARCHAR(80)'],
            ['location_lat', 'VARCHAR(40)'],
            ['location_lng', 'VARCHAR(40)'],
            ['location_provider', 'VARCHAR(20)'],
            ['location_data', 'TEXT'],
            ['weather_label', 'VARCHAR(40)'],
            ['weather_icon', 'VARCHAR(80)'],
            ['weather_temp', 'VARCHAR(20)'],
            ['weather_code', 'INTEGER DEFAULT 0'],
            ['weather_data', 'TEXT'],
        ] as [$column, $type]) {
            try {
                self::db()->query("ALTER TABLE talk ADD COLUMN {$column} {$type}");
            } catch (\Throwable) {
                // Column already exists.
            }
        }
    }

    public static function paginate(int $page = 1, int $perPage = 20, string $orderBy = 'published_at DESC, created_at DESC, id DESC', ?string $whereSql = null, array $params = []): array
    {
        self::ensureLocationSchema();
        // 如果未指定 where，默认只查公开的
        if ($whereSql === null) {
            $whereSql = 'is_public = ' . Toggle::On->value;
        }
        $result = parent::paginate($page, $perPage, $orderBy, $whereSql, $params);
        $result['items'] = self::withRealCommentCounts($result['items']);
        return $result;
    }

    public function publishedAt(): string
    {
        return (string)($this->published_at ?: $this->created_at ?: date('Y-m-d H:i:s'));
    }

    public function getImages(): array
    {
        if (empty($this->images)) return [];
        return array_filter(explode(',', $this->images));
    }

    public function getKeywords(): array
    {
        $content = (string)($this->content ?? '');
        preg_match_all('/#([\p{L}\p{N}_-]+)/u', $content, $matches);

        $keywords = array_values(array_unique(array_filter(array_map('trim', $matches[1] ?? []))));
        if (!empty($keywords)) {
            return $keywords;
        }

        return [];
    }

    public function contentWithoutKeywords(): string
    {
        $content = (string)($this->content ?? '');
        $content = preg_replace('/[ \t]*#[\p{L}\p{N}_-]+/u', '', $content) ?? $content;
        return trim($content);
    }

    public function locationDisplayName(): string
    {
        $name = trim((string)($this->location_name ?? ''));
        $city = trim((string)($this->location_city ?? ''));
        $value = $name !== '' ? $name : $city;
        return self::compactLocationName($value);
    }

    public function locationFullName(): string
    {
        $name = trim((string)($this->location_name ?? ''));
        $city = trim((string)($this->location_city ?? ''));
        $data = json_decode((string)($this->location_data ?? ''), true);
        if (is_array($data)) {
            $full = trim((string)($data['full_name'] ?? $data['place_name'] ?? ''));
            if ($full !== '') {
                return $full;
            }
        }
        return $name !== '' ? $name : $city;
    }

    private static function compactLocationName(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $value = preg_replace('/^中华人民共和国/u', '', $value) ?? $value;
        $value = trim($value, " \t\n\r\0\x0B,，");
        if ($value === '') {
            return '';
        }

        $parts = preg_split('/[,，]/u', $value);
        if (is_array($parts) && count($parts) > 1) {
            $first = trim((string)$parts[0]);
            if ($first !== '') {
                return $first;
            }
        }

        preg_match_all('/[^省市县区旗盟州]+(?:自治区|特别行政区|省|市|县|区|旗|盟|州)/u', $value, $matches);
        $segments = array_values(array_filter(array_map('trim', $matches[0] ?? [])));
        if (!empty($segments)) {
            return (string)end($segments);
        }

        return mb_strlen($value) > 18 ? mb_substr($value, 0, 18) . '...' : $value;
    }

    public function weatherDisplayText(): string
    {
        $label = trim((string)($this->weather_label ?? ''));
        if ($label === '') {
            return '';
        }
        $temp = trim((string)($this->weather_temp ?? ''));
        return $temp !== '' ? $label . ' ' . $temp . '°C' : $label;
    }

    /**
     * @param self[] $items
     * @return self[]
     */
    public static function withRealCommentCounts(array $items): array
    {
        $ids = array_values(array_unique(array_filter(array_map(
            static fn(self $item): int => (int)$item->id,
            $items
        ))));
        if ($ids === []) {
            return $items;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $rows = self::db()->fetchAll(
            "SELECT talk_id, COUNT(*) AS total
             FROM comments
             WHERE status = ? AND talk_id IN ({$placeholders})
             GROUP BY talk_id",
            array_merge([\App\Enums\CommentStatus::Approved->value], $ids)
        );
        $counts = [];
        foreach ($rows as $row) {
            $counts[(int)$row['talk_id']] = (int)$row['total'];
        }
        foreach ($items as $item) {
            $item->comments_count = $counts[(int)$item->id] ?? 0;
        }
        return $items;
    }

    public static function like(int $id): int
    {
        if ($id <= 0) {
            return 0;
        }
        self::db()->query('UPDATE talk SET likes_count = COALESCE(likes_count, 0) + 1 WHERE id = ?', [$id]);
        return (int) self::db()->fetchColumn('SELECT COALESCE(likes_count, 0) FROM talk WHERE id = ?', [$id]);
    }
}
