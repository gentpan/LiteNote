<?php
declare(strict_types=1);

namespace App\Models;

use App\Enums\PostStatus;
use App\Models\Post;

final class Category extends Model
{
    protected static string $table = 'categories';

    public static function allEnabled(): array
    {
        return self::query('SELECT * FROM categories ORDER BY sort ASC, id ASC');
    }

    /** 仅返回勾选了「在菜单栏显示」的分类(用于前台导航下拉)。 */
    public static function navList(): array
    {
        return self::query('SELECT * FROM categories WHERE show_in_nav = 1 ORDER BY sort ASC, id ASC');
    }

    /**
     * 分类配色索引(0-5),按 id 稳定取色 —— 下拉菜单与分类页 Hero 共用,
     * 保证同一分类在任何位置颜色一致(不随排序变化)。
     */
    public function colorIndex(): int
    {
        // 优先用手动指定的 color(0-5);未指定则按 id 稳定取色
        $color = $this->color;
        if ($color !== null && $color !== '') {
            return ((int) $color) % 6;
        }
        return ((int) $this->id) % 6;
    }

    /**
     * 安全的 FontAwesome 图标类名(白名单过滤,空/非法时回退)。
     */
    public function iconClass(string $fallback = 'fa-regular fa-folder'): string
    {
        $icon = trim((string) ($this->icon ?? ''));
        if ($icon === '' || !preg_match('/^[a-zA-Z0-9 _-]+$/', $icon)) {
            return $fallback;
        }
        return $icon;
    }

    public static function findBySlug(string $slug): ?self
    {
        $slug = trim($slug);
        $decodedSlug = self::decodeSlug($slug);

        $category = self::findBy('slug', $decodedSlug);
        if ($category || $decodedSlug === $slug) {
            return $category;
        }

        return self::findBy('slug', $slug);
    }

    public static function decodeSlug(string $slug): string
    {
        return rawurldecode(trim($slug));
    }

    public function getArticleStats(): array
    {
        Post::ensurePublishingOptionsSchema();
        $published = PostStatus::Published->value;
        $hasLikesColumn = (int) self::db()->fetchColumn(
            "SELECT COUNT(*) FROM pragma_table_info('posts') WHERE name = 'likes_count'"
        ) > 0;

        $likesExpr = $hasLikesColumn
            ? 'COALESCE(SUM(COALESCE(likes_count, 0)), 0) AS likes_count'
            : '0 AS likes_count';

        $row = self::db()->fetchOne(
            "SELECT
                COUNT(*) AS article_count,
                COALESCE(SUM(COALESCE(views, 0)), 0) AS views,
                COALESCE(SUM(
                    LENGTH(COALESCE(summary, '')) +
                    LENGTH(COALESCE(content, '')) +
                    LENGTH(COALESCE(markdown_content, ''))
                ), 0) AS words,
                {$likesExpr}
            FROM posts
            WHERE category_id = ? AND status = ? AND COALESCE(is_private, 0) = 0",
            [$this->id, $published]
        );

        $commentsCount = (int) self::db()->fetchColumn(
            "SELECT COUNT(*)
             FROM comments cm
             INNER JOIN posts p ON p.id = cm.post_id
             WHERE p.category_id = ? AND p.status = ? AND COALESCE(p.is_private, 0) = 0 AND cm.status = 'approved'",
            [$this->id, $published]
        );

        if (!$row) {
            return [
                'article_count' => 0,
                'views' => 0,
                'words' => 0,
                'comments_count' => 0,
                'likes_count' => 0,
            ];
        }

        return [
            'article_count' => (int) $row['article_count'],
            'views' => (int) $row['views'],
            'words' => (int) $row['words'],
            'comments_count' => $commentsCount,
            'likes_count' => (int) $row['likes_count'],
        ];
    }
}
