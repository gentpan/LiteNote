<?php
declare(strict_types=1);

namespace App\Services;

use App\Enums\PostStatus;
use App\Models\Category;
use App\Models\Page;
use App\Models\Post;
use App\Models\Setting;

final class PermalinkService
{
    public const BASE_NONE = '';
    public const BASE_CUSTOM = 'custom';
    public const PATTERN_SLUG = 'slug';
    public const PATTERN_ID = 'id';
    public const PATTERN_YM_SLUG = 'ym_slug';
    public const PATTERN_YMD_SLUG = 'ymd_slug';
    public const PATTERN_YMD_TIME_SLUG = 'ymd_time_slug';
    public const PATTERN_CATEGORY_SLUG = 'category_slug';
    public const PATTERN_CATEGORY_YMD_SLUG = 'category_ymd_slug';
    public const PATTERN_CATEGORY_ID = 'category_id';
    public const PATTERN_YMD_ID = 'ymd_id';

    public static function ensureDefaults(): void
    {
        Setting::ensureDefaults([
            ['k' => 'permalink_base', 'v' => 'post', 'type' => 'select', 'label' => '路径前缀', 'group_name' => 'permalink', 'sort' => 1],
            ['k' => 'permalink_base_custom', 'v' => 'blog', 'label' => '自定义路径名称', 'group_name' => 'permalink', 'sort' => 2],
            ['k' => 'permalink_pattern', 'v' => self::PATTERN_SLUG, 'type' => 'select', 'label' => '链接字段', 'group_name' => 'permalink', 'sort' => 3],
            ['k' => 'permalink_suffix_mode', 'v' => '.html', 'type' => 'select', 'label' => '链接后缀', 'group_name' => 'permalink', 'sort' => 4],
            ['k' => 'permalink_suffix_custom', 'v' => '.html', 'label' => '自定义后缀', 'group_name' => 'permalink', 'sort' => 5],
        ]);
    }

    public static function settings(): array
    {
        try {
            self::ensureDefaults();
            $base = self::sanitizeBase((string) Setting::get('permalink_base', 'post'));
            $baseCustom = self::sanitizeSegment((string) Setting::get('permalink_base_custom', 'blog'), 'blog');
            $pattern = self::sanitizePattern((string) Setting::get('permalink_pattern', self::PATTERN_SLUG));
            $suffixMode = (string) Setting::get('permalink_suffix_mode', '.html');
            $suffixCustom = (string) Setting::get('permalink_suffix_custom', '.html');
        } catch (\Throwable) {
            $base = 'post';
            $baseCustom = 'blog';
            $pattern = self::PATTERN_SLUG;
            $suffixMode = '.html';
            $suffixCustom = '.html';
        }

        $suffix = self::sanitizeSuffix($suffixMode === 'custom' ? $suffixCustom : $suffixMode);
        $prefix = $base === self::BASE_CUSTOM ? $baseCustom : $base;

        return compact('base', 'baseCustom', 'prefix', 'pattern', 'suffixMode', 'suffixCustom', 'suffix');
    }

    public static function postUrl(Post $post): string
    {
        $settings = self::settings();
        $parts = self::pathParts($post, (string)$settings['pattern']);
        if ($parts === []) {
            $parts = [self::slug($post)];
        }
        $last = array_key_last($parts);
        $parts[$last] .= (string)$settings['suffix'];

        $prefix = trim((string)$settings['prefix'], '/');
        if ($prefix !== '') {
            array_unshift($parts, $prefix);
        }

        return '/' . implode('/', $parts);
    }

    public static function postUrlFromParts(int $id, string $slug, ?string $categorySlug = null): string
    {
        $post = Post::find($id) ?: new Post([
            'id' => $id,
            'slug' => $slug,
        ]);
        $categorySlug = trim((string)$categorySlug, '/');
        if ($categorySlug !== '') {
            $post->setRelation('category', new Category([
                'slug' => $categorySlug,
            ]));
        }
        return self::postUrl($post);
    }

    public static function resolve(string $path): ?Post
    {
        $path = self::cleanPath($path);
        if ($path === '') {
            return null;
        }

        $settings = self::settings();
        if (self::isReservedPath($path, (string)$settings['prefix'])) {
            return null;
        }
        $post = self::resolveConfigured($path, $settings);

        return $post && (string)$post->status === PostStatus::Published->value ? $post : null;
    }

    public static function resolveLegacyDefault(string $slug): ?Post
    {
        $post = Post::findBySlug($slug);
        return $post && (string)$post->status === PostStatus::Published->value ? $post : null;
    }

    public static function pageSlugConflicts(int $limit = 20): array
    {
        $settings = self::settings();
        if ((string)$settings['prefix'] !== '' || (string)$settings['suffix'] !== '') {
            return [];
        }

        $rows = Post::db()->fetchAll(
            "SELECT p.id, p.slug, p.title, p.published_at, p.category_id
             FROM posts p
             WHERE p.status = ?
             ORDER BY p.id DESC
             LIMIT 100",
            [PostStatus::Published->value]
        );
        $conflicts = [];
        foreach ($rows as $row) {
            $post = new Post($row);
            $parts = self::pathParts($post, (string)$settings['pattern']);
            if (count($parts) !== 1) {
                continue;
            }
            if (Page::findBySlug($parts[0])) {
                $conflicts[] = ['slug' => $parts[0], 'title' => (string)$row['title']];
                if (count($conflicts) >= $limit) {
                    break;
                }
            }
        }

        return $conflicts;
    }

    private static function resolveConfigured(string $path, array $settings): ?Post
    {
        $prefix = trim((string)$settings['prefix'], '/');
        if ($prefix !== '') {
            if (!str_starts_with($path, $prefix . '/')) {
                return null;
            }
            $path = substr($path, strlen($prefix) + 1);
        }

        $suffix = (string)$settings['suffix'];
        if ($suffix !== '') {
            if (!str_ends_with($path, $suffix)) {
                return null;
            }
            $path = substr($path, 0, -strlen($suffix));
        }

        if ($path === '') {
            return null;
        }
        $parts = array_values(array_filter(explode('/', $path), static fn(string $part): bool => $part !== ''));
        if ($parts === []) {
            return null;
        }

        $identity = end($parts);
        $pattern = (string)$settings['pattern'];
        $post = self::patternUsesId($pattern) ? Post::find((int)$identity) : Post::findBySlug((string)$identity);
        if (!$post) {
            return null;
        }

        if ($prefix === '' && count($parts) === 1 && Page::findBySlug($parts[0])) {
            return null;
        }

        $expected = self::pathParts($post, $pattern);
        return $expected === $parts ? $post : null;
    }

    private static function pathParts(Post $post, string $pattern): array
    {
        $date = self::dateParts($post);
        return match ($pattern) {
            self::PATTERN_ID => [self::id($post)],
            self::PATTERN_YM_SLUG => [$date['Y'], $date['m'], self::slug($post)],
            self::PATTERN_YMD_SLUG => [$date['Y'], $date['m'], $date['d'], self::slug($post)],
            self::PATTERN_YMD_TIME_SLUG => [$date['Y'], $date['m'], $date['d'], $date['His'], self::slug($post)],
            self::PATTERN_CATEGORY_SLUG => [self::categorySlug($post), self::slug($post)],
            self::PATTERN_CATEGORY_YMD_SLUG => [self::categorySlug($post), $date['Y'], $date['m'], $date['d'], self::slug($post)],
            self::PATTERN_CATEGORY_ID => [self::categorySlug($post), self::id($post)],
            self::PATTERN_YMD_ID => [$date['Y'], $date['m'], $date['d'], self::id($post)],
            default => [self::slug($post)],
        };
    }

    private static function dateParts(Post $post): array
    {
        $raw = trim((string)($post->published_at ?? '')) ?: trim((string)($post->created_at ?? ''));
        $ts = $raw !== '' ? strtotime($raw) : false;
        if ($ts === false) {
            $ts = time();
        }

        return [
            'Y' => date('Y', $ts),
            'm' => date('m', $ts),
            'd' => date('d', $ts),
            'His' => date('His', $ts),
        ];
    }

    private static function id(Post $post): string
    {
        return (string)max(1, (int)$post->id);
    }

    private static function slug(Post $post): string
    {
        $slug = trim((string)$post->slug, '/');
        return $slug !== '' ? $slug : self::id($post);
    }

    private static function patternUsesId(string $pattern): bool
    {
        return in_array($pattern, [self::PATTERN_ID, self::PATTERN_CATEGORY_ID, self::PATTERN_YMD_ID], true);
    }

    private static function categorySlug(Post $post): string
    {
        $category = $post->getCategory();
        $slug = $category ? trim((string)$category->slug, '/') : '';
        return $slug !== '' ? $slug : 'post';
    }

    public static function allowedBases(): array
    {
        return [self::BASE_NONE, 'post', 'posts', 'article', 'archive', 'blog', self::BASE_CUSTOM];
    }

    public static function allowedPatterns(): array
    {
        return [
            self::PATTERN_SLUG,
            self::PATTERN_ID,
            self::PATTERN_YM_SLUG,
            self::PATTERN_YMD_SLUG,
            self::PATTERN_YMD_TIME_SLUG,
            self::PATTERN_CATEGORY_SLUG,
            self::PATTERN_CATEGORY_YMD_SLUG,
            self::PATTERN_CATEGORY_ID,
            self::PATTERN_YMD_ID,
        ];
    }

    public static function sanitizeBase(string $value): string
    {
        $value = trim($value, '/');
        return in_array($value, self::allowedBases(), true) ? $value : 'post';
    }

    public static function sanitizePattern(string $value): string
    {
        return in_array($value, self::allowedPatterns(), true) ? $value : self::PATTERN_SLUG;
    }

    public static function sanitizeSegment(string $value, string $default = 'blog'): string
    {
        $value = trim($value, '/');
        if ($value === '') {
            return $default;
        }
        if (!preg_match('/^[a-zA-Z0-9_-]{1,40}$/', $value)) {
            return $default;
        }
        return in_array($value, self::reservedFirstSegments(), true) ? $default : $value;
    }

    public static function sanitizeSuffix(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if ($value[0] !== '.') {
            $value = '.' . $value;
        }
        return preg_match('/^\.[a-zA-Z0-9][a-zA-Z0-9_-]{0,11}$/', $value) ? strtolower($value) : '.html';
    }

    private static function cleanPath(string $path): string
    {
        if (preg_match('#^https?://#i', $path)) {
            $parts = parse_url($path);
            $path = is_array($parts) && isset($parts['path']) ? (string)$parts['path'] : $path;
        } else {
            $path = preg_split('/[?#]/', $path, 2)[0] ?? $path;
        }
        return trim(rawurldecode($path), '/');
    }

    private static function isReservedPath(string $path, string $configuredPrefix = ''): bool
    {
        $first = explode('/', $path, 2)[0] ?? '';
        if ($configuredPrefix !== '' && $first === $configuredPrefix && str_contains($path, '/')) {
            return false;
        }
        return in_array($first, self::reservedFirstSegments(), true);
    }

    private static function reservedFirstSegments(): array
    {
        return [
            'admin', 'api', 'category', 'posts', 'readers', 'activity', 'talk', 'x',
            'music', 'archives', 'search', 'links', 'subscribe', 'comment',
            'captcha', 'auth', 'rss.xml', 'feed', 'mail', 'themes', 'uploads',
        ];
    }
}
