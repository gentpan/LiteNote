<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Helper;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Enums\CommentStatus;
use App\Models\Comment;
use App\Traits\HasFlashRedirect;

/**
 * 评论管理（改进版）
 * 变更点：
 * 1. 使用 HasFlashRedirect trait。
 * 2. 提取 syncPostCommentCount() 消除 approve/destroy 中的重复代码。
 * 3. 列表查询使用基类 paginate 能力。
 * 4. 增加 JSON 响应统一格式。
 */
class CommentController
{
    use HasFlashRedirect;

    public function index(): string
    {
        $status = $_GET['status'] ?? 'all';
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = (int) \App\Core\Config::get('pagination.admin_per_page', 20);

        $where = [];
        $params = [];
        if ($status !== 'all') {
            $where[] = 'c.status = ?';
            $params[] = $status;
        }
        $whereSql = $where ? implode(' AND ', $where) : null;

        $total = (int) Comment::db()->fetchColumn(
            'SELECT COUNT(*) FROM comments c' . ($whereSql ? ' WHERE ' . $whereSql : ''),
            $params
        );
        $offset = ($page - 1) * $perPage;
        $rows = Comment::db()->fetchAll(
            "SELECT c.*,
                    COALESCE(p.title, pg.title, '说说 #' || s.id) AS target_title,
                    COALESCE(p.slug, pg.slug, s.id) AS target_slug
             FROM comments c
             LEFT JOIN posts p ON c.post_id = p.id
             LEFT JOIN pages pg ON c.page_id = pg.id
             LEFT JOIN shuoshuo s ON c.shuoshuo_id = s.id
             " . ($whereSql ? ' WHERE ' . $whereSql : '') . "
             ORDER BY c.id DESC LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return View::render('comment.index', [
            'comments'  => $rows,
            'status'    => $status,
            'page'      => $page,
            'perPage'   => $perPage,
            'total'     => $total,
            'paginator' => Helper::paginate($page, $total, $perPage, '/admin/comments?status=' . $status),
            'csrf'      => Session::csrfToken(),
            'pageTitle' => '评论管理',
        ], 'layouts.admin');
    }

    public function approve(Request $request): never
    {
        $id = (int) $request->input('id', 0);
        $cmt = Comment::find($id);
        if ($cmt) {
            $cmt->status = CommentStatus::Approved->value;
            $cmt->save();
            $this->syncPostCommentCount($cmt);
        }
        Response::json(['code' => 0, 'msg' => '已通过']);
    }

    public function spam(Request $request): never
    {
        $id = (int) $request->input('id', 0);
        $cmt = Comment::find($id);
        if ($cmt) {
            $cmt->status = CommentStatus::Spam->value;
            $cmt->save();
            $this->syncPostCommentCount($cmt);
        }
        Response::json(['code' => 0, 'msg' => '已标记垃圾']);
    }

    public function destroy(Request $request): never
    {
        $id = (int) $request->input('id', 0);
        $cmt = Comment::find($id);
        if ($cmt) {
            Comment::db()->delete('comments', 'id = ?', [$id]);
            $this->syncPostCommentCount($cmt);
        }
        Response::json(['code' => 0, 'msg' => '已删除']);
    }

    /**
     * 同步文章的已审核评论数。
     */
    private function syncPostCommentCount(Comment $cmt): void
    {
        if (!$cmt->post_id) {
            if ($cmt->shuoshuo_id) {
                Comment::syncCountForShuoshuo((int)$cmt->shuoshuo_id);
            }
            return;
        }
        Comment::syncCountForPost((int)$cmt->post_id);
    }
}
