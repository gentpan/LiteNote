<?php
declare(strict_types=1);

namespace App\Models;

use App\Enums\CommentStatus;
use App\Services\Gravatar;
use App\Services\IpGeoService;

/**
 * Comment 模型（改进版）
 * 变更点：
 * 1. 增加 syncCountForPost() 统一评论数同步逻辑。
 * 2. 扩展 $sortable 白名单。
 * 3. 状态值改用 App\Enums\CommentStatus,删除原有 const(向后兼容 alias)。
 */
final class Comment extends Model
{
    protected static string $table = 'comments';
    protected static array $sortable = ['id', 'created_at', 'post_id', 'page_id', 'talk_id', 'music_id', 'status'];
    private static bool $geoSchemaChecked = false;

    /**
     * 向后兼容 alias —— 旧代码可能引用了 Comment::STATUS_*
     * 推荐迁移到 CommentStatus::Pending/Approved/Spam->value
     */
    public const STATUS_PENDING  = CommentStatus::Pending->value;
    public const STATUS_APPROVED = CommentStatus::Approved->value;
    public const STATUS_SPAM     = CommentStatus::Spam->value;

    public static function ensureGeoColumns(): void
    {
        if (self::$geoSchemaChecked) {
            return;
        }
        self::$geoSchemaChecked = true;
        foreach ([
            ['geo_country_code', 'VARCHAR(2)'],
            ['geo_country', 'VARCHAR(64)'],
            ['geo_region', 'VARCHAR(80)'],
            ['geo_city', 'VARCHAR(80)'],
            ['geo_data', 'TEXT'],
        ] as [$column, $type]) {
            try {
                self::db()->query("ALTER TABLE comments ADD COLUMN {$column} {$type}");
            } catch (\Throwable) {
                // Column already exists or database is not ready.
            }
        }
    }

    public static function forPost(int $postId, string|CommentStatus $status = CommentStatus::Approved): array
    {
        self::ensureGeoColumns();
        $statusValue = $status instanceof CommentStatus ? $status->value : $status;
        $comments = self::query(
            'SELECT * FROM comments WHERE post_id = ? AND status = ? ORDER BY id ASC',
            [$postId, $statusValue]
        );
        self::hydrateGeoForComments($comments);
        return $comments;
    }

    public static function forPage(int $pageId, string|CommentStatus $status = CommentStatus::Approved): array
    {
        self::ensureGeoColumns();
        $statusValue = $status instanceof CommentStatus ? $status->value : $status;
        $comments = self::query(
            'SELECT * FROM comments WHERE page_id = ? AND status = ? ORDER BY id ASC',
            [$pageId, $statusValue]
        );
        self::hydrateGeoForComments($comments);
        return $comments;
    }

    public static function forTalk(int $talkId, string|CommentStatus $status = CommentStatus::Approved): array
    {
        self::ensureGeoColumns();
        $statusValue = $status instanceof CommentStatus ? $status->value : $status;
        $comments = self::query(
            'SELECT * FROM comments WHERE talk_id = ? AND status = ? ORDER BY id ASC',
            [$talkId, $statusValue]
        );
        self::hydrateGeoForComments($comments);
        return $comments;
    }

    public static function forMusic(int $musicId, string|CommentStatus $status = CommentStatus::Approved): array
    {
        self::ensureGeoColumns();
        $statusValue = $status instanceof CommentStatus ? $status->value : $status;
        $comments = self::query(
            'SELECT * FROM comments WHERE music_id = ? AND status = ? ORDER BY id ASC',
            [$musicId, $statusValue]
        );
        self::hydrateGeoForComments($comments);
        return $comments;
    }

    /**
     * 读路径不再同步请求外部 GeoIP:geo 字段已在评论入库时
     * (CommentController::submit) 写入,这里只保证列存在。
     * 历史缺 geo 的评论留空即可,避免访客打开公开页面被外部 API 阻塞。
     *
     * @param self[] $comments
     */
    public static function hydrateGeoForComments(array $comments): void
    {
        self::ensureGeoColumns();
    }

    public static function recent(int $limit = 10): array
    {
        $rows = self::db()->fetchAll(
            "SELECT c.*,
                    COALESCE(p.title, pg.title, '滔客 #' || s.id, '音乐 #' || m.id) AS target_title,
                    COALESCE(p.slug, pg.slug, s.id, m.id) AS target_slug
             FROM comments c
             LEFT JOIN posts p ON c.post_id = p.id
             LEFT JOIN pages pg ON c.page_id = pg.id
             LEFT JOIN talk s ON c.talk_id = s.id
             LEFT JOIN music m ON c.music_id = m.id
             ORDER BY c.id DESC LIMIT {$limit}"
        );
        return $rows;
    }

    /**
     * 按邮箱统计某访客的已审核评论数(用于侧边访客身份卡)。
     */
    public static function countApprovedByEmail(string $email): int
    {
        $email = trim($email);
        if ($email === '') {
            return 0;
        }
        return (int) self::db()->fetchColumn(
            'SELECT COUNT(*) FROM comments WHERE email = ? AND status = ?',
            [$email, CommentStatus::Approved->value]
        );
    }

    public static function countByPost(int $postId, string|CommentStatus $status = CommentStatus::Approved): int
    {
        $statusValue = $status instanceof CommentStatus ? $status->value : $status;
        return (int) self::db()->fetchColumn(
            'SELECT COUNT(*) FROM comments WHERE post_id = ? AND status = ?',
            [$postId, $statusValue]
        );
    }

    public static function countByPage(int $pageId, string|CommentStatus $status = CommentStatus::Approved): int
    {
        $statusValue = $status instanceof CommentStatus ? $status->value : $status;
        return (int) self::db()->fetchColumn(
            'SELECT COUNT(*) FROM comments WHERE page_id = ? AND status = ?',
            [$pageId, $statusValue]
        );
    }

    public static function countByTalk(int $talkId, string|CommentStatus $status = CommentStatus::Approved): int
    {
        $statusValue = $status instanceof CommentStatus ? $status->value : $status;
        return (int) self::db()->fetchColumn(
            'SELECT COUNT(*) FROM comments WHERE talk_id = ? AND status = ?',
            [$talkId, $statusValue]
        );
    }

    public static function countByMusic(int $musicId, string|CommentStatus $status = CommentStatus::Approved): int
    {
        $statusValue = $status instanceof CommentStatus ? $status->value : $status;
        return (int) self::db()->fetchColumn(
            'SELECT COUNT(*) FROM comments WHERE music_id = ? AND status = ?',
            [$musicId, $statusValue]
        );
    }

    /**
     * 同步指定文章的已审核评论数到 posts 表。
     */
    public static function syncCountForPost(int $postId): void
    {
        if ($postId <= 0) {
            return;
        }
        $count = self::countByPost($postId, CommentStatus::Approved);
        self::db()->update('posts', ['comments_count' => $count], 'id = :id', [':id' => $postId]);
    }

    public static function syncCountForTalk(int $talkId): void
    {
        if ($talkId <= 0) {
            return;
        }
        $count = self::countByTalk($talkId, CommentStatus::Approved);
        self::db()->update('talk', ['comments_count' => $count], 'id = :id', [':id' => $talkId]);
    }

    public static function syncCountForMusic(int $musicId): void
    {
        if ($musicId <= 0) {
            return;
        }
        $count = self::countByMusic($musicId, CommentStatus::Approved);
        self::db()->update('music', ['comments_count' => $count], 'id = :id', [':id' => $musicId]);
    }

    public static function forXTweet(int $xTweetId, string|CommentStatus $status = CommentStatus::Approved): array
    {
        self::ensureGeoColumns();
        $statusValue = $status instanceof CommentStatus ? $status->value : $status;
        $comments = self::query(
            'SELECT * FROM comments WHERE x_tweet_id = ? AND status = ? ORDER BY id ASC',
            [$xTweetId, $statusValue]
        );
        self::hydrateGeoForComments($comments);
        return $comments;
    }

    public static function countByXTweet(int $xTweetId, string|CommentStatus $status = CommentStatus::Approved): int
    {
        $statusValue = $status instanceof CommentStatus ? $status->value : $status;
        return (int) self::db()->fetchColumn(
            'SELECT COUNT(*) FROM comments WHERE x_tweet_id = ? AND status = ?',
            [$xTweetId, $statusValue]
        );
    }

    public static function syncCountForXTweet(int $xTweetId): void
    {
        if ($xTweetId <= 0) {
            return;
        }
        $count = self::countByXTweet($xTweetId, CommentStatus::Approved);
        self::db()->update('x_tweets', ['comments_count' => $count], 'id = :id', [':id' => $xTweetId]);
    }

    public function parent(): ?self
    {
        if (!$this->parent_id) {
            return null;
        }
        return self::find($this->parent_id);
    }

    /**
     * 头像 URL(优先用用户在评论里填的 email 生成 gravatar)。
     * 若 email 为空,返回带 identicon 默认的 URL(不会 404)。
     */
    public function getAvatarUrl(int $size = 60): string
    {
        $email = (string)($this->email ?? '');
        return Gravatar::url($email, $size, 'identicon');
    }

    public function flagUrl(): string
    {
        return IpGeoService::flagUrl((string)($this->geo_country_code ?? ''));
    }

    public function locationLabel(): string
    {
        return IpGeoService::locationLabel($this);
    }

    public function frontLocationLabel(): string
    {
        return IpGeoService::frontLocationLabel($this);
    }
}
