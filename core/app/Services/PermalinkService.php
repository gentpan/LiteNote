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
    public const MODE_DEFAULT = 'default';
    public const MODE_SIMPLE = 'simple';
    public const MODE_CATEGORY = 'category';
    public const MODE_NUMERIC = 'numeric';

    public static function ensureDefaults(): void
    {
        Setting::ensureDefaults([
            ['k' => 'permalink_mode', 'v' => self::MODE_DEFAULT, 'type' => 'select', 'label' => '文章链接模式', 'group_name' => 'permalink', 'sort' => 1],
            ['k' => 'permalink_numeric_prefix', 'v' => 'post', 'label' => '数字链接前缀', 'group_name' => 'permalink', 'sort' => 10],
            ['k' => 'permalink_numeric_source', 'v' => 'six', 'type' => 'select', 'label' => '数字来源', 'group_name' => 'permalink', 'sort' => 11],
            ['k' => 'permalink_numeric_suffix', 'v' => '.html', 'type' => 'select', 'label' => '数字链接后缀', 'group_name' => 'permalink', 'sort' => 12],
        ]);
    }

    public static function settings(): array
    {
        try {
            self::ensureDefaults();
            $mode = (string) Setting::get('permalink_mode', self::MODE_DEFAULT);
            $prefix = trim((string) Setting::get('permalink_numeric_prefix', 'post'), '/');
            $source = (string) Setting::get('permalink_numeric_source', 'six');
            $suffix = (string) Setting::get('permalink_numeric_suffix', '.html');
        } catch (\Throwable) {
            $mode = self::MODE_DEFAULT;
            $prefix = 'post';
            $source = 'six';
            $suffix = '.html';
        }

        if (!in_array($mode, [self::MODE_DEFAULT, self::MODE_SIMPLE, self::MODE_CATEGORY, self::MODE_NUMERIC], true)) {
            $mode = self::MODE_DEFAULT;
        }
        if (!in_array($source, ['id', 'six'], true)) {
            $source = 'six';
        }
        if (!in_array($suffix, ['', '.html'], true)) {
            $suffix = '.html';
        }
        if ($prefix !== '' && !preg_match('/^[a-zA-Z0-9_-]+$/', $prefix)) {
            $prefix = 'post';
        }

        return compact('mode', 'prefix', 'source', 'suffix');
    }

    public static function postUrl(Post $post): string
    {
        $settings = self::settings();
        $slug = trim((string)$post->slug, '/');

        $path = match ($settings['mode']) {
            self::MODE_SIMPLE => '/' . $slug,
            self::MODE_CATEGORY => '/' . self::categorySlug($post) . '/' . $slug,
            self::MODE_NUMERIC => self::numericPath($post, $settings),
            default => '/post/' . $slug . '.html',
        };

        return $path;
    }

    public static function postUrlFromParts(int $id, string $slug, ?string $categorySlug = null): string
    {
        $post = new Post([
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
        if ($path === '' || self::isReservedPath($path)) {
            return null;
        }

        $settings = self::settings();
        $post = match ($settings['mode']) {
            self::MODE_SIMPLE => self::resolveSimple($path),
            self::MODE_CATEGORY => self::resolveCategory($path),
            self::MODE_NUMERIC => self::resolveNumeric($path, $settings),
            default => self::resolveDefault($path),
        };

        return $post && (string)$post->status === PostStatus::Published->value ? $post : null;
    }

    public static function resolveLegacyDefault(string $slug): ?Post
    {
        $post = Post::findBySlug($slug);
        return $post && (string)$post->status === PostStatus::Published->value ? $post : null;
    }

    public static function pageSlugConflicts(int $limit = 20): array
    {
        if (self::settings()['mode'] !== self::MODE_SIMPLE) {
            return [];
        }
        $rows = Post::db()->fetchAll(
            "SELECT p.slug, p.title
             FROM posts p
             INNER JOIN pages pg ON pg.slug = p.slug
             WHERE p.status = ?
             ORDER BY p.id DESC
             LIMIT " . max(1, min(100, $limit)),
            [PostStatus::Published->value]
        );

        return $rows;
    }

    private static function resolveDefault(string $path): ?Post
    {
        if (!preg_match('#^post/([^/]+)\.html$#', $path, $match)) {
            return null;
        }
        return Post::findBySlug($match[1]);
    }

    private static function resolveSimple(string $path): ?Post
    {
        if (str_contains($path, '/') || str_ends_with($path, '.html')) {
            return null;
        }
        if (Page::findBySlug($path)) {
            return null;
        }
        return Post::findBySlug($path);
    }

    private static function resolveCategory(string $path): ?Post
    {
        $parts = explode('/', $path);
        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            return null;
        }

        $post = Post::findBySlug($parts[1]);
        if (!$post) {
            return null;
        }
        return self::categorySlug($post) === $parts[0] ? $post : null;
    }

    private static function resolveNumeric(string $path, array $settings): ?Post
    {
        $prefix = (string)$settings['prefix'];
        $suffix = (string)$settings['suffix'];

        if ($prefix !== '') {
            if (!str_starts_with($path, $prefix . '/')) {
                return null;
            }
            $path = substr($path, strlen($prefix) + 1);
        } elseif (str_contains($path, '/')) {
            return null;
        }

        if ($suffix !== '') {
            if (!str_ends_with($path, $suffix)) {
                return null;
            }
            $path = substr($path, 0, -strlen($suffix));
        }

        if (!preg_match('/^\d+$/', $path)) {
            return null;
        }

        $id = self::idFromNumber($path, (string)$settings['source']);
        return $id > 0 ? Post::find($id) : null;
    }

    private static function numericPath(Post $post, array $settings): string
    {
        $number = self::numberForPost($post, (string)$settings['source']);
        $prefix = trim((string)$settings['prefix'], '/');
        $suffix = (string)$settings['suffix'];
        $parts = $prefix !== '' ? [$prefix, $number . $suffix] : [$number . $suffix];
        return '/' . implode('/', $parts);
    }

    private static function numberForPost(Post $post, string $source): string
    {
        $id = max(1, (int)$post->id);
        if ($source === 'id') {
            return (string)$id;
        }
        return (string)(100000 + $id);
    }

    private static function idFromNumber(string $number, string $source): int
    {
        $value = (int)$number;
        if ($source === 'six') {
            return $value >= 100001 ? $value - 100000 : 0;
        }
        return $value;
    }

    private static function categorySlug(Post $post): string
    {
        $category = $post->getCategory();
        $slug = $category ? trim((string)$category->slug, '/') : '';
        return $slug !== '' ? $slug : 'post';
    }

    private static function cleanPath(string $path): string
    {
        $parts = parse_url($path);
        $path = is_array($parts) && isset($parts['path']) ? (string)$parts['path'] : $path;
        return trim(rawurldecode($path), '/');
    }

    private static function isReservedPath(string $path): bool
    {
        $first = explode('/', $path, 2)[0] ?? '';
        return in_array($first, [
            'admin', 'api', 'category', 'posts', 'readers', 'activity', 'talk', 'x',
            'music', 'archives', 'search', 'links', 'subscribe', 'comment',
            'captcha', 'rss.xml', 'feed', 'mail', 'themes', 'uploads',
        ], true);
    }
}
