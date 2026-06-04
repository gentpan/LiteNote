<?php
declare(strict_types=1);

namespace App\Models;

use App\Enums\CommentStatus;
use App\Services\Gravatar;

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
    protected static array $sortable = ['id', 'created_at', 'post_id', 'page_id', 'shuoshuo_id', 'status'];

    /**
     * 向后兼容 alias —— 旧代码可能引用了 Comment::STATUS_*
     * 推荐迁移到 CommentStatus::Pending/Approved/Spam->value
     */
    public const STATUS_PENDING  = CommentStatus::Pending->value;
    public const STATUS_APPROVED = CommentStatus::Approved->value;
    public const STATUS_SPAM     = CommentStatus::Spam->value;

    public static function forPost(int $postId, string|CommentStatus $status = CommentStatus::Approved): array
    {
        $statusValue = $status instanceof CommentStatus ? $status->value : $status;
        return self::query(
            'SELECT * FROM comments WHERE post_id = ? AND status = ? ORDER BY id ASC',
            [$postId, $statusValue]
        );
    }

    public static function forPage(int $pageId, string|CommentStatus $status = CommentStatus::Approved): array
    {
        $statusValue = $status instanceof CommentStatus ? $status->value : $status;
        return self::query(
            'SELECT * FROM comments WHERE page_id = ? AND status = ? ORDER BY id ASC',
            [$pageId, $statusValue]
        );
    }

    public static function forShuoshuo(int $shuoshuoId, string|CommentStatus $status = CommentStatus::Approved): array
    {
        $statusValue = $status instanceof CommentStatus ? $status->value : $status;
        return self::query(
            'SELECT * FROM comments WHERE shuoshuo_id = ? AND status = ? ORDER BY id ASC',
            [$shuoshuoId, $statusValue]
        );
    }

    public static function recent(int $limit = 10): array
    {
        $rows = self::db()->fetchAll(
            "SELECT c.*,
                    COALESCE(p.title, pg.title, '说说 #' || s.id) AS target_title,
                    COALESCE(p.slug, pg.slug, s.id) AS target_slug
             FROM comments c
             LEFT JOIN posts p ON c.post_id = p.id
             LEFT JOIN pages pg ON c.page_id = pg.id
             LEFT JOIN shuoshuo s ON c.shuoshuo_id = s.id
             ORDER BY c.id DESC LIMIT {$limit}"
        );
        return $rows;
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

    public static function countByShuoshuo(int $shuoshuoId, string|CommentStatus $status = CommentStatus::Approved): int
    {
        $statusValue = $status instanceof CommentStatus ? $status->value : $status;
        return (int) self::db()->fetchColumn(
            'SELECT COUNT(*) FROM comments WHERE shuoshuo_id = ? AND status = ?',
            [$shuoshuoId, $statusValue]
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

    public static function syncCountForShuoshuo(int $shuoshuoId): void
    {
        if ($shuoshuoId <= 0) {
            return;
        }
        $count = self::countByShuoshuo($shuoshuoId, CommentStatus::Approved);
        self::db()->update('shuoshuo', ['comments_count' => $count], 'id = :id', [':id' => $shuoshuoId]);
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
}
