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
        return parent::paginate($page, $perPage, $orderBy, $whereSql, $params);
    }

    public static function paginatePublic(int $page = 1, int $perPage = 10): array
    {
        self::ensurePublishedAtColumn();
        return parent::paginate(
            $page,
            $perPage,
            'published_at DESC, sort ASC, id DESC',
            'is_public = ?',
            [Toggle::On->value]
        );
    }

    public static function recentPublic(int $limit = 6): array
    {
        self::ensurePublishedAtColumn();
        $rows = self::db()->fetchAll(
            "SELECT * FROM music WHERE is_public = ? ORDER BY published_at DESC, sort ASC, id DESC LIMIT {$limit}",
            [Toggle::On->value]
        );
        return array_map(fn(array $row) => new self($row), $rows);
    }

    public static function publicOptions(int $limit = 120): array
    {
        self::ensurePublishedAtColumn();
        $limit = max(1, min(300, $limit));
        $rows = self::db()->fetchAll(
            "SELECT * FROM music WHERE is_public = ? ORDER BY published_at DESC, sort ASC, id DESC LIMIT {$limit}",
            [Toggle::On->value]
        );
        return array_map(fn(array $row) => new self($row), $rows);
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

    public function lyricsLines(int $limit = 4): array
    {
        $lyrics = trim(self::normalizeLyricsText((string)($this->lyrics ?? '')));
        if ($lyrics === '') {
            return [];
        }

        $lines = preg_split('/\R/u', $lyrics) ?: [];
        $lines = array_values(array_filter(array_map('trim', $lines), static fn(string $line): bool => $line !== ''));
        return $limit > 0 ? array_slice($lines, 0, $limit) : $lines;
    }

    public static function normalizeLyricsText(string $lyrics): string
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

    public function publishedInputValue(): string
    {
        $ts = strtotime($this->publishedAt()) ?: time();
        return date('Y-m-d\TH:i', $ts);
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
