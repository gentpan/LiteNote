<?php
declare(strict_types=1);

namespace App\Models;

final class Setting extends Model
{
    protected static string $table = 'settings';

    public static function allAsArray(): array
    {
        $rows = self::db()->fetchAll('SELECT k, v FROM settings');
        $result = [];
        foreach ($rows as $r) {
            $result[$r['k']] = self::cast($r['v']);
        }
        return $result;
    }

    public static function grouped(): array
    {
        $rows = self::db()->fetchAll('SELECT * FROM settings ORDER BY group_name ASC, sort ASC, id ASC');
        $grouped = [];
        $hidden = [
            'theme',
            'site_theme',
            'site_favicon_mode',
            'site_favicon_source',
            'site_favicon_error',
            'site_favicon_updated_at',
            'site_favicon_version',
        ];
        foreach ($rows as $r) {
            if (in_array(($r['k'] ?? ''), $hidden, true)) {
                continue;
            }
            $grouped[$r['group_name']][] = $r;
        }
        return $grouped;
    }

    public static function ensureDefaults(array $settings): void
    {
        foreach ($settings as $setting) {
            if (!isset($setting['k'])) {
                continue;
            }
            if (self::findBy('k', (string)$setting['k'])) {
                continue;
            }
            self::db()->insert('settings', [
                'k' => (string)$setting['k'],
                'v' => (string)($setting['v'] ?? ''),
                'type' => (string)($setting['type'] ?? 'string'),
                'label' => (string)($setting['label'] ?? $setting['k']),
                'group_name' => (string)($setting['group_name'] ?? 'basic'),
                'sort' => (int)($setting['sort'] ?? 0),
            ]);
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $row = self::findBy('k', $key);
        if (!$row) return $default;
        return self::cast($row->v);
    }

    public static function set(string $key, mixed $value): void
    {
        $existing = self::findBy('k', $key);
        $v = is_array($value) || is_object($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string)$value;
        if ($existing) {
            $existing->v = $v;
            $existing->save();
        } else {
            $m = new self(['k' => $key, 'v' => $v]);
            $m->save();
        }
    }

    public static function setMany(array $data): void
    {
        foreach ($data as $k => $v) {
            self::set($k, $v);
        }
    }

    private static function cast(mixed $v): mixed
    {
        if ($v === '1') return 1;
        if ($v === '0') return 0;
        if ($v === 'true') return true;
        if ($v === 'false') return false;
        return $v;
    }
}
