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
        self::ensurePublishingOptionsSchema();
        $offset = max(0, ($page - 1) * $perPage);

        $where = ["p.status = '" . PostStatus::Published->value . "'", 'COALESCE(p.is_private, 0) = 0'];
        $params = [];
        if ($categoryId) {
            $where[] = 'p.category_id = ?';
            $params[] = $categoryId;
        }
        $whereSql = implode(' AND ', $where);
        $total = (int) self::db()->fetchColumn("SELECT COUNT(*) FROM posts p WHERE {$whereSql}", $params);

        $published = PostStatus::Published->value;
        $sql = "SELECT p.*,
                  (
                    SELECT COUNT(*)
                    FROM posts pn
                    WHERE pn.status = '{$published}'
                      AND COALESCE(pn.is_private, 0) = 0
                      AND (
                        pn.published_at < p.published_at
                        OR (pn.published_at = p.published_at AND pn.id <= p.id)
                      )
                  ) as article_number,
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
        self::ensurePublishingOptionsSchema();
        $keyword = trim($keyword);
        if ($keyword === '' || mb_strlen($keyword) > 100) {
            return ['items' => [], 'total' => 0];
        }
        $published = PostStatus::Published->value;
        $rows = self::db()->fetchAll(
            "SELECT * FROM posts WHERE status='{$published}' AND COALESCE(is_private, 0) = 0 ORDER BY published_at DESC"
        );
        $matches = [];
        foreach ($rows as $row) {
            $post = new self($row);
            $haystacks = [
                (string)$post->title,
                (string)($post->summary ?? ''),
                $post->markdown(),
            ];
            foreach ($haystacks as $haystack) {
                if (mb_stripos($haystack, $keyword) !== false) {
                    $matches[] = $post;
                    break;
                }
            }
        }

        $offset = max(0, ($page - 1) * $perPage);
        return [
            'items' => array_slice($matches, $offset, $perPage),
            'total' => count($matches),
        ];
    }

    /**
     * @param int[] $ids
     * @return self[]
     */
    public static function whereInIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($ids === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $rows = self::db()->fetchAll("SELECT * FROM posts WHERE id IN ({$placeholders})", $ids);
        return array_map(fn($row) => new self($row), $rows);
    }

    public static function archives(): array
    {
        self::ensurePublishingOptionsSchema();
        $published = PostStatus::Published->value;
        return self::db()->fetchAll(
            "SELECT p.id, p.title, p.slug, p.summary, p.category_id, p.views, p.comments_count, p.published_at,
                    c.name AS category_name, c.slug AS category_slug, c.icon AS category_icon, c.color AS category_color
             FROM posts p
             LEFT JOIN categories c ON p.category_id = c.id
             WHERE p.status='{$published}' AND COALESCE(p.is_private, 0) = 0
             ORDER BY p.published_at DESC, p.id DESC"
        );
    }

    public static function popular(int $limit = 10): array
    {
        self::ensurePublishingOptionsSchema();
        $published = PostStatus::Published->value;
        $rows = self::db()->fetchAll(
            "SELECT id, title, slug, views FROM posts WHERE status='{$published}' AND COALESCE(is_private, 0) = 0 ORDER BY views DESC LIMIT {$limit}"
        );
        return array_map(fn($r) => new self($r), $rows);
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

    public function getTextForStats(): string
    {
        $raw = $this->markdown();
        if ($raw === '') {
            $raw = (string)$this->content;
        }

        $plain = preg_replace('/```[\s\S]*?```/u', ' ', $raw);
        $plain = preg_replace('/!\[[^\]]*\]\([^)]+\)/u', ' ', (string)$plain);
        $plain = preg_replace('/\[(.*?)\]\([^)]+\)/u', '$1', (string)$plain);
        $plain = preg_replace('/`{1,3}(.*?)`{1,3}/u', '$1', (string)$plain);
        $plain = preg_replace('/[#*_~>\-+=|`]/u', ' ', (string)$plain);
        $plain = strip_tags((string)$plain);
        $plain = html_entity_decode($plain, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $plain = preg_replace('/\s+/u', ' ', (string)$plain);
        return trim((string)$plain);
    }

    public function textWordCount(): int
    {
        $text = $this->getTextForStats();
        if ($text === '') {
            return 0;
        }

        $hanCount = preg_match_all('/\p{Han}/u', $text, $matches);
        if ($hanCount === false) {
            $hanCount = 0;
        }

        $latinText = preg_replace('/\p{Han}/u', ' ', $text);
        $latinText = preg_replace('/[^\pL\pN\s]/u', ' ', (string)$latinText);
        $latinText = trim((string)preg_replace('/\s+/u', ' ', $latinText));
        $latinCount = $latinText === '' ? 0 : count(preg_split('/\s+/u', $latinText, -1, PREG_SPLIT_NO_EMPTY));

        return (int) $hanCount + (int) $latinCount;
    }

    public function readingMinutes(int $speedPerMinute = 200): int
    {
        $count = max(1, $this->textWordCount());
        return (int)max(1, (int)ceil($count / max(1, $speedPerMinute)));
    }
}
