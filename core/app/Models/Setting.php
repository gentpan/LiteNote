<?php
declare(strict_types=1);

namespace App\Models;

final class Setting extends Model
{
    protected static string $table = 'settings';

    /** 请求级缓存:key => 已 cast 的值。避免一次请求里对同一 key 反复查库。 */
    private static array $cache = [];
    /** 请求级缓存:key => 该 key 是否存在于表中(区分"值为 0/false"与"缺失")。 */
    private static array $loaded = [];

    private const HIDDEN_KEYS = [
        'theme',
        'beian',
        'site_icp',
        'site_theme',
        'site_favicon_mode',
        'site_favicon_source',
        'site_favicon_error',
        'site_favicon_updated_at',
        'site_favicon_version',
        'plugins_enabled',
        'attachment_cdn_enabled',
        'attachment_cdn_url',
        'attachment_image_webp_enabled',
        'attachment_s3_enabled',
        'attachment_s3_endpoint',
        'attachment_s3_bucket',
        'attachment_s3_region',
        'attachment_s3_access_key',
        'attachment_s3_secret_key',
        'attachment_s3_prefix',
        'attachment_s3_delete_remote',
        'attachment_backup_enabled',
        'attachment_backup_s3_enabled',
        'attachment_backup_time',
        'attachment_backup_retention_days',
        'attachment_backup_keep_versions',
        'attachment_backup_last_run_date',
        'attachment_backup_last_status',
        'home_feed_mode',
        'home_total_limit',
        'home_post_limit',
        'home_talk_limit',
        'post_list_per_page',
        'home_fixed_posts',
        'home_fixed_talks',
        'post_article_font',
        'post_title_font',
        'comment_enabled',
        'comment_post_enabled',
        'comment_page_enabled',
        'comment_talk_enabled',
        'comment_music_enabled',
        'comment_email_required',
        'comment_replies_enabled',
        'comment_close_old_days',
        'permalink_mode',
        'permalink_numeric_prefix',
        'permalink_numeric_source',
        'permalink_numeric_suffix',
    ];

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
        foreach ($rows as $r) {
            if (self::isHiddenKey((string)($r['k'] ?? ''))) {
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
            // 新插入的默认值同步进请求级缓存,避免之前 get() 缓存的"缺失"状态变陈旧。
            self::$loaded[(string)$setting['k']] = true;
            self::$cache[(string)$setting['k']] = self::cast((string)($setting['v'] ?? ''));
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        if (!array_key_exists($key, self::$loaded)) {
            $row = self::findBy('k', $key);
            self::$loaded[$key] = $row !== null;
            self::$cache[$key] = $row !== null ? self::cast($row->v) : null;
        }
        return self::$loaded[$key] ? self::$cache[$key] : $default;
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
        // 同步请求级缓存,使后续 get() 立即可见新值。
        self::$loaded[$key] = true;
        self::$cache[$key] = self::cast($v);
    }

    public static function setMany(array $data): void
    {
        foreach ($data as $k => $v) {
            self::set($k, $v);
        }
    }

    public static function isHiddenKey(string $key): bool
    {
        return in_array($key, self::HIDDEN_KEYS, true);
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
