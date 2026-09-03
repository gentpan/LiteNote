<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Helper;
use App\Core\Markdown;
use App\Enums\PostStatus;
use App\Enums\Toggle;
use App\Services\PermalinkService;
use App\Services\PostContentStorage;
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
        return self::paginatePublishedRange(max(0, ($page - 1) * $perPage), max(1, $perPage), $categoryId);
    }

    /** 按显式 offset/limit 取已发布文章(支持首页非均匀分页:第1页13、之后10)。 */
    public static function paginatePublishedRange(int $offset, int $limit, ?int $categoryId = null): array
    {
        self::ensurePublishingOptionsSchema();
        $offset = max(0, $offset);
        $limit = max(1, $limit);

        $where = ["p.status = '" . PostStatus::Published->value . "'", 'COALESCE(p.is_private, 0) = 0'];
        $params = [];
        if ($categoryId) {
            $where[] = 'p.category_id = ?';
            $params[] = $categoryId;
        }
        $whereSql = implode(' AND ', $where);
        $total = (int) self::db()->fetchColumn("SELECT COUNT(*) FROM posts p WHERE {$whereSql}", $params);

        $sql = "SELECT p.*,
                  (
                    SELECT COUNT(*)
                    FROM posts pn
                    WHERE pn.status = '" . PostStatus::Published->value . "'
                      AND COALESCE(pn.is_private, 0) = 0
                      AND (
                        pn.published_at > p.published_at
                        OR (pn.published_at = p.published_at AND pn.id >= p.id)
                      )
                  ) AS article_number,
                  COALESCE(cc.total, 0) AS __comments_count,
                  c.name as __category_name, c.slug as __category_slug
                FROM posts p
                LEFT JOIN categories c ON p.category_id = c.id
                LEFT JOIN (
                    SELECT post_id, COUNT(*) AS total
                    FROM comments
                    WHERE status = 'approved'
                    GROUP BY post_id
                ) cc ON cc.post_id = p.id
                WHERE {$whereSql}
                ORDER BY p.is_top DESC, p.published_at DESC
                LIMIT {$limit} OFFSET {$offset}";
        $rows = self::db()->fetchAll($sql, $params);

        $items = [];
        foreach ($rows as $r) {
            $r['comments_count'] = (int)($r['__comments_count'] ?? 0);
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
            "SELECT post_id, COUNT(*) AS total
             FROM comments
             WHERE status = ? AND post_id IN ({$placeholders})
             GROUP BY post_id",
            array_merge([\App\Enums\CommentStatus::Approved->value], $ids)
        );
        $counts = [];
        foreach ($rows as $row) {
            $counts[(int)$row['post_id']] = (int)$row['total'];
        }
        foreach ($items as $item) {
            $item->comments_count = $counts[(int)$item->id] ?? 0;
        }
        return $items;
    }

    public static function search(string $keyword, int $page = 1, int $perPage = 20): array
    {
        self::ensurePublishingOptionsSchema();
        $keyword = trim($keyword);
        if ($keyword === '' || mb_strlen($keyword) > 100) {
            return ['items' => [], 'total' => 0];
        }

        $published = PostStatus::Published->value;
        $like = '%' . $keyword . '%';
        $where = "p.status = ? AND COALESCE(p.is_private, 0) = 0 AND (p.title LIKE ? OR p.summary LIKE ? OR c.name LIKE ?)";
        $params = [$published, $like, $like, $like];

        $total = (int) self::db()->fetchColumn(
            "SELECT COUNT(*) FROM posts p LEFT JOIN categories c ON p.category_id = c.id WHERE {$where}",
            $params
        );

        $sql = "SELECT p.*, c.name AS category_name FROM posts p LEFT JOIN categories c ON p.category_id = c.id WHERE {$where} ORDER BY p.published_at DESC, p.id DESC";
        if ($perPage > 0) {
            $offset = max(0, ($page - 1) * $perPage);
            $sql .= " LIMIT {$perPage} OFFSET {$offset}";
        }
        $rows = self::db()->fetchAll($sql, $params);

        return [
            'items' => array_map(fn($r) => new self($r), $rows),
            'total' => $total,
        ];
    }

    public static function whereInIds(array $ids): array
    {
        return parent::whereInIds($ids);
    }

    public static function archives(): array
    {
        self::ensurePublishingOptionsSchema();
        $published = PostStatus::Published->value;
        return self::db()->fetchAll(
            "SELECT p.id, p.title, p.slug, p.summary, p.category_id, p.views, p.published_at,
                    COALESCE(cc.total, 0) AS comments_count,
                    c.name AS category_name, c.slug AS category_slug, c.icon AS category_icon, c.color AS category_color
             FROM posts p
             LEFT JOIN categories c ON p.category_id = c.id
             LEFT JOIN (
                 SELECT post_id, COUNT(*) AS total
                 FROM comments
                 WHERE status = 'approved'
                 GROUP BY post_id
             ) cc ON cc.post_id = p.id
             WHERE p.status='{$published}' AND COALESCE(p.is_private, 0) = 0
             ORDER BY p.published_at DESC, p.id DESC"
        );
    }

    public static function recent(int $limit = 10): array
    {
        self::ensurePublishingOptionsSchema();
        $published = PostStatus::Published->value;
        $rows = self::db()->fetchAll(
            "SELECT id, title, slug, published_at FROM posts WHERE status='{$published}' AND COALESCE(is_private, 0) = 0 ORDER BY published_at DESC LIMIT {$limit}"
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
        $this->views = (int) ($this->views ?? 0) + 1;
    }

    /** 记录一次浏览（与页面一致：每次打开都 +1，并同步到当前对象供模板显示）。 */
    public function trackView(): void
    {
        $id = (int) $this->id;
        if ($id <= 0) {
            return;
        }

        $this->incrementViews();
    }

    public static function ensureEngagementSchema(): void
    {
        try {
            self::db()->query('ALTER TABLE posts ADD COLUMN likes_count INTEGER DEFAULT 0');
        } catch (\Throwable) {
            // 已存在则忽略。
        }
    }

    public static function ensurePublishingOptionsSchema(): void
    {
        static $checked = false;
        if ($checked) {
            return;
        }
        $checked = true;

        foreach ([
            'allow_comments' => 'INTEGER DEFAULT 1',
            'allow_rss' => 'INTEGER DEFAULT 1',
            'is_private' => 'INTEGER DEFAULT 0',
        ] as $column => $definition) {
            try {
                self::db()->query("ALTER TABLE posts ADD COLUMN {$column} {$definition}");
            } catch (\Throwable) {
                // 已存在则忽略。
            }
        }
    }

    public static function like(int $id): int
    {
        self::ensureEngagementSchema();
        self::db()->query('UPDATE posts SET likes_count = COALESCE(likes_count, 0) + 1 WHERE id = ?', [$id]);
        return (int) self::db()->fetchColumn('SELECT COALESCE(likes_count, 0) FROM posts WHERE id = ?', [$id]);
    }

    public function getUrl(): string
    {
        return PermalinkService::postUrl($this);
    }

    public function displayCover(): string
    {
        $cover = trim((string)($this->cover ?? ''));
        if ($cover !== '') {
            return $cover;
        }

        return 'https://img.et/2560/1080?type=banner&r=' . max(1, (int)$this->id);
    }

    public function summaryOrContent(int $length = 200): string
    {
        if (!empty($this->summary)) {
            return Helper::truncate($this->summary, $length);
        }
        return Helper::truncate($this->markdown(), $length);
    }

    public function markdown(): string
    {
        $markdown = PostContentStorage::read((string)$this->slug);
        if ($markdown !== '') {
            return $markdown;
        }
        return (string)($this->markdown_content ?? '');
    }

    public function html(): string
    {
        $markdown = $this->markdown();
        if ($markdown !== '') {
            return Markdown::parse($markdown);
        }
        return (string)$this->content;
    }

}
