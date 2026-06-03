<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Helper;
use App\Enums\PostStatus;
use App\Enums\Toggle;
use App\Traits\HasSlug;

/**
 * Post 模型（改进版）
 * 变更点：
 * 1. 使用 HasSlug trait，消除重复 slug 逻辑。
 * 2. 扩展 $sortable 白名单。
 * 3. paginatePublished 使用 LEFT JOIN 预加载分类，消除 N+1。
 * 4. getCategory 优先返回预加载缓存。
 * 5. search() 增加关键词长度限制，防止超长查询。
 * 6. 状态/标志统一走 PostStatus / Toggle enum。
 * 7. tags 功能已彻底移除(2026-06)。
 */
final class Post extends Model
{
    use HasSlug;

    protected static string $table = 'posts';
    protected static array $sortable = ['id', 'published_at', 'views', 'created_at', 'updated_at', 'is_top'];

    /** 便捷访问 */
    public const STATUS_PUBLISHED = PostStatus::Published->value;
    public const STATUS_DRAFT     = PostStatus::Draft->value;

    public static function findBySlug(string $slug): ?self
    {
        return self::findBy('slug', $slug);
    }

    /**
     * 分页查询已发布文章,JOIN 预加载分类(消除 N+1)。
     * 注:tags 功能已移除,不再做 tag 预加载。
     */
    public static function paginatePublished(int $page, int $perPage, ?int $categoryId = null): array
    {
        $offset = max(0, ($page - 1) * $perPage);

        $where = ["p.status = '" . PostStatus::Published->value . "'"];
        $params = [];
        if ($categoryId) {
            $where[] = 'p.category_id = ?';
            $params[] = $categoryId;
        }
        $whereSql = implode(' AND ', $where);
        $total = (int) self::db()->fetchColumn("SELECT COUNT(*) FROM posts p WHERE {$whereSql}", $params);

        $sql = "SELECT p.*,
                  c.name as __category_name, c.slug as __category_slug
                FROM posts p
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE {$whereSql}
                GROUP BY p.id
                ORDER BY p.is_top DESC, p.published_at DESC
                LIMIT {$perPage} OFFSET {$offset}";
        $rows = self::db()->fetchAll($sql, $params);

        $items = [];
        foreach ($rows as $r) {
            $post = new self($r);
            if (!empty($r['__category_name'])) {
                $post->setRelation('category', new Category([
                    'id'   => $r['category_id'] ?? 0,
                    'name' => $r['__category_name'],
                    'slug' => $r['__category_slug'],
                ]));
            }
            $items[] = $post;
        }
        return ['items' => $items, 'total' => $total];
    }

    public static function search(string $keyword, int $page, int $perPage): array
    {
        $keyword = trim($keyword);
        if ($keyword === '' || mb_strlen($keyword) > 100) {
            return ['items' => [], 'total' => 0];
        }
        $like = '%' . $keyword . '%';
        $published = PostStatus::Published->value;
        $total = (int) self::db()->fetchColumn(
            "SELECT COUNT(*) FROM posts WHERE status='{$published}' AND (title LIKE ? OR summary LIKE ? OR content LIKE ?)",
            [$like, $like, $like]
        );
        $offset = max(0, ($page - 1) * $perPage);
        $rows = self::db()->fetchAll(
            "SELECT * FROM posts WHERE status='{$published}' AND (title LIKE ? OR summary LIKE ? OR content LIKE ?) ORDER BY published_at DESC LIMIT {$perPage} OFFSET {$offset}",
            [$like, $like, $like]
        );
        return [
            'items' => array_map(fn($r) => new self($r), $rows),
            'total' => $total,
        ];
    }

    public static function archives(): array
    {
        $published = PostStatus::Published->value;
        return self::db()->fetchAll(
            "SELECT id, title, slug, published_at FROM posts WHERE status='{$published}' ORDER BY published_at DESC"
        );
    }

    public static function popular(int $limit = 10): array
    {
        $published = PostStatus::Published->value;
        $rows = self::db()->fetchAll(
            "SELECT id, title, slug, views FROM posts WHERE status='{$published}' ORDER BY views DESC LIMIT {$limit}"
        );
        return array_map(fn($r) => new self($r), $rows);
    }

    public static function recent(int $limit = 10): array
    {
        $published = PostStatus::Published->value;
        $rows = self::db()->fetchAll(
            "SELECT id, title, slug, published_at FROM posts WHERE status='{$published}' ORDER BY published_at DESC LIMIT {$limit}"
        );
        return array_map(fn($r) => new self($r), $rows);
    }

    public function getCategory(): ?Category
    {
        $cached = $this->getRelation('category');
        if ($cached !== null) return $cached;
        if (!$this->category_id) return null;
        return Category::find($this->category_id);
    }

    public function incrementViews(): void
    {
        self::db()->query('UPDATE posts SET views = views + 1 WHERE id = ?', [$this->id]);
    }

    public function getUrl(): string
    {
        return Helper::url('/post/' . $this->slug . '.html');
    }

    public function summaryOrContent(int $length = 200): string
    {
        if (!empty($this->summary)) {
            return Helper::truncate($this->summary, $length);
        }
        return Helper::truncate(strip_tags((string)$this->content), $length);
    }
}
