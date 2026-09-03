<?php
declare(strict_types=1);

namespace App\Models;

use App\Enums\Toggle;

final class Link extends Model
{
    protected static string $table = 'links';
    private static bool $requestColumnsChecked = false;

    public static function enabled(): array
    {
        self::ensureRequestColumns();
        return self::query('SELECT * FROM links WHERE is_enabled = ' . Toggle::On->value . ' ORDER BY sort ASC, id ASC');
    }

    public static function withRss(): array
    {
        self::ensureRequestColumns();
        return self::query(
            'SELECT * FROM links WHERE is_enabled = ' . Toggle::On->value .
            ' AND rss_url IS NOT NULL AND rss_url <> "" ORDER BY sort ASC, id ASC'
        );
    }

    public static function ensureRequestColumns(): void
    {
        if (self::$requestColumnsChecked) {
            return;
        }

        $existing = array_map(
            static fn(array $column): string => (string)($column['name'] ?? ''),
            self::db()->fetchAll('PRAGMA table_info(links)')
        );

        $columns = [
            'contact_email' => 'VARCHAR(255)',
            'request_type' => "VARCHAR(20) DEFAULT 'admin'",
            'previous_url' => 'VARCHAR(255)',
            'submitted_at' => 'DATETIME',
            'updated_at' => 'DATETIME',
        ];

        foreach ($columns as $name => $definition) {
            if (!in_array($name, $existing, true)) {
                self::db()->query('ALTER TABLE links ADD COLUMN ' . $name . ' ' . $definition);
            }
        }

        self::$requestColumnsChecked = true;
    }

    public static function findEnabledByUrl(string $url): ?self
    {
        self::ensureRequestColumns();
        $row = self::db()->fetchOne(
            "SELECT * FROM links WHERE (url = ? OR rtrim(url, '/') = rtrim(?, '/')) AND is_enabled = ? LIMIT 1",
            [$url, $url, Toggle::On->value]
        );
        return $row ? new self($row) : null;
    }

    public static function findPendingRequest(string $type, string $url, string $email): ?self
    {
        self::ensureRequestColumns();
        $field = $type === 'modify' ? 'previous_url' : 'url';
        $row = self::db()->fetchOne(
            'SELECT * FROM links WHERE request_type = ? AND ' . $field . ' = ? AND contact_email = ? AND is_enabled = 0 LIMIT 1',
            [$type, $url, $email]
        );
        return $row ? new self($row) : null;
    }
}
