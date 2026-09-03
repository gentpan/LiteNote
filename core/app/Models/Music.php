<?php
declare(strict_types=1);

namespace App\Models;

use App\Enums\Toggle;

final class Music extends Model
{
    protected static string $table = 'music';
    protected static array $sortable = [
        'id',
        'sort',
        'published_at',
        'created_at',
        'updated_at',
        'play_count',
        'likes_count',
        'comments_count',
    ];

    private static bool $schemaChecked = false;

    public static function ensurePublishedAtColumn(): void
    {
        if (self::$schemaChecked) {
            return;
        }
        self::$schemaChecked = true;

        $db = self::db();
        try {
            $db->query('ALTER TABLE music ADD COLUMN published_at DATETIME');
        } catch (\Throwable) {
            // 已存在则忽略
        }
        try {
            $db->query('ALTER TABLE music ADD COLUMN lyrics_url TEXT');
        } catch (\Throwable) {
            // 已存在则忽略
        }
        try {
            $db->query(
                "UPDATE music
                 SET published_at = COALESCE(NULLIF(TRIM(published_at), ''), created_at, updated_at, CURRENT_TIMESTAMP)
                 WHERE published_at IS NULL OR TRIM(published_at) = ''"
            );
        } catch (\Throwable) {
            // 老库异常不阻塞页面
        }
        try {
            $db->query('CREATE INDEX IF NOT EXISTS idx_music_public_published ON music(is_public, published_at, sort, id)');
        } catch (\Throwable) {
            // ignore
        }
    }

    public static function paginate(int $page = 1, int $perPage = 20, string $orderBy = 'published_at DESC, sort ASC, id DESC', ?string $whereSql = null, array $params = []): array
    {
        self::ensurePublishedAtColumn();
        $result = parent::paginate($page, $perPage, $orderBy, $whereSql, $params);
        $result['items'] = self::withRealCommentCounts($result['items']);
        return $result;
    }

    /**
     * @param int[] $ids
     * @return array<int, self>
     */
    public static function mapByIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $id): bool => $id > 0)));
        if (empty($ids)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $rows = self::db()->fetchAll(
            "SELECT * FROM music WHERE id IN ({$placeholders})",
            $ids
        );

        $map = [];
        foreach ($rows as $row) {
            $item = new self($row);
            $map[(int)$item->id] = $item;
        }
        return $map;
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
            "SELECT music_id, COUNT(*) AS total
             FROM comments
             WHERE status = ? AND music_id IN ({$placeholders})
             GROUP BY music_id",
            array_merge([\App\Enums\CommentStatus::Approved->value], $ids)
        );
        $counts = [];
        foreach ($rows as $row) {
            $counts[(int)$row['music_id']] = (int)$row['total'];
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
        self::db()->query('UPDATE music SET likes_count = COALESCE(likes_count, 0) + 1 WHERE id = ?', [$id]);
        return (int) self::db()->fetchColumn('SELECT COALESCE(likes_count, 0) FROM music WHERE id = ?', [$id]);
    }

    public static function recordPlay(int $id): int
    {
        if ($id <= 0) {
            return 0;
        }
        self::db()->query('UPDATE music SET play_count = COALESCE(play_count, 0) + 1 WHERE id = ?', [$id]);
        return (int) self::db()->fetchColumn('SELECT COALESCE(play_count, 0) FROM music WHERE id = ?', [$id]);
    }

    /** Session 去重后记录播放，避免刷量。 */
    public static function trackPlay(int $id): int
    {
        if ($id <= 0) {
            return 0;
        }

        $played = \App\Core\Session::get('played_music', []);
        $played = is_array($played) ? $played : [];
        if (!empty($played[$id])) {
            return (int) self::db()->fetchColumn('SELECT COALESCE(play_count, 0) FROM music WHERE id = ?', [$id]);
        }

        $count = self::recordPlay($id);
        $played[$id] = time();
        if (count($played) > 300) {
            $played = array_slice($played, -200, null, true);
        }
        \App\Core\Session::set('played_music', $played);
        return $count;
    }

    public function lyricsLines(int $limit = 4): array
    {
        $lyrics = trim(self::plainLyricsText((string)($this->lyrics ?? '')));
        if ($lyrics === '') {
            return [];
        }

        $lines = preg_split('/\R/u', $lyrics) ?: [];
        $lines = array_values(array_filter(array_map('trim', $lines), static fn(string $line): bool => $line !== ''));
        return $limit > 0 ? array_slice($lines, 0, $limit) : $lines;
    }

    public static function normalizeLyricsText(string $lyrics): string
    {
        return trim(str_replace(["\r\n", "\r"], "\n", $lyrics));
    }

    public static function plainLyricsText(string $lyrics): string
    {
        $lyrics = trim(str_replace(["\r\n", "\r"], "\n", $lyrics));
        if ($lyrics === '' || !preg_match('/\[\d{1,2}:\d{2}(?:[\.:]\d{1,3})?\]/', $lyrics)) {
            return $lyrics;
        }

        $clean = [];
        foreach (explode("\n", $lyrics) as $line) {
            $line = trim($line);
            if ($line === '' || preg_match('/^\[(?:ti|ar|al|by|offset|length|re):[^\]]*\]$/i', $line)) {
                continue;
            }
            $line = trim((string)preg_replace('/(?:\[\d{1,2}:\d{2}(?:[\.:]\d{1,3})?\])+/', '', $line));
            if ($line !== '') {
                $clean[] = $line;
            }
        }

        return implode("\n", $clean);
    }

    public static function normalizePublishedAt(?string $value, ?string $fallback = null): string
    {
        $value = trim(str_replace('T', ' ', (string)$value));
        if ($value !== '' && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $value)) {
            $value .= ':00';
        }

        $ts = $value !== '' ? strtotime($value) : false;
        if ($ts === false && $fallback !== null && trim($fallback) !== '') {
            $ts = strtotime((string)$fallback);
        }
        if ($ts === false) {
            $ts = time();
        }

        return date('Y-m-d H:i:s', $ts);
    }

    public function publishedAt(): string
    {
        return self::normalizePublishedAt(
            (string)($this->published_at ?? ''),
            (string)($this->created_at ?? '')
        );
    }

    public function fallbackInitial(): string
    {
        $title = trim((string)($this->title ?? ''));
        if ($title === '') {
            return '♪';
        }
        return mb_substr($title, 0, 1);
    }
}
