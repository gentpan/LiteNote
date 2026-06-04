<?php
declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Enums\CommentStatus;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Setting;
use App\Models\Shuoshuo;

/**
 * 评论提交（改进版）
 * 变更点：
 * 1. 使用 Validator 统一校验，消除手工 if 链。
 * 2. 提取反垃圾规则为独立方法，便于扩展。
 * 3. 使用 backWithError 统一错误跳转。
 * 4. 评论内容增加 HTML 过滤，防止 XSS。
 * 5. 同步更新评论数逻辑提取到 Comment 模型。
 */
class CommentController
{
    public function submit(Request $request): never
    {
        $data = [
            'post_id'   => (int) $request->input('post_id', 0),
            'shuoshuo_id'=> (int) $request->input('shuoshuo_id', 0),
            'parent_id' => (int) $request->input('parent_id', 0),
            'nickname'  => $request->input('nickname', ''),
            'email'     => $request->input('email', ''),
            'website'   => $request->input('website', ''),
            'content'   => $request->input('content', ''),
            '_csrf'     => $request->input('_csrf'),
        ];

        if (!Session::verifyCsrf($data['_csrf'])) {
            $this->backWithError('会话已过期，请刷新页面后重试');
        }

        $validator = \App\Core\Validator::make($data, [
            'nickname' => 'required|string|min:1|max:50',
            'content'  => 'required|string|min:2|max:2000',
            'email'    => 'email',
        ]);
        if (!$validator->validate()) {
            $this->backWithError($validator->firstError() ?? '校验失败');
        }

        $content = trim((string)$data['content']);
        if ($this->isSpam($content)) {
            $this->backWithError('评论包含过多链接，已被拦截');
        }

        $target = $this->resolveTarget((int)$data['post_id'], (int)$data['shuoshuo_id']);
        if (!$target) {
            $this->backWithError('评论目标不存在');
        }

        $needAudit = (bool) Setting::get('comment_need_audit', true);
        $status = $needAudit ? CommentStatus::Pending->value : CommentStatus::Approved->value;

        $cmt = new Comment([
            'post_id'   => (int)$data['post_id'],
            'page_id'   => 0,
            'shuoshuo_id'=> (int)$data['shuoshuo_id'],
            'parent_id' => (int)$data['parent_id'],
            'nickname'  => htmlspecialchars(trim((string)$data['nickname']), ENT_QUOTES, 'UTF-8'),
            'email'     => trim((string)$data['email']),
            'website'   => trim((string)$data['website']),
            'content'   => htmlspecialchars($content, ENT_QUOTES, 'UTF-8'),
            'ip'        => $request->ip,
            'ua'        => mb_substr($request->ua, 0, 250),
            'status'    => $status,
        ]);
        $cmt->save();

        if ($status === CommentStatus::Approved->value) {
            Comment::syncCountForPost((int)$data['post_id']);
            Comment::syncCountForShuoshuo((int)$data['shuoshuo_id']);
        }

        Session::flash('comment_success', $needAudit ? '评论已提交，等待审核后显示' : '评论发布成功');
        $this->back();
    }

    private function isSpam(string $content): bool
    {
        $linkCount = preg_match_all('#https?://#i', $content);
        return $linkCount > 3;
    }

    private function resolveTarget(int $postId, int $shuoshuoId): ?object
    {
        if ($postId) {
            return Post::find($postId);
        }
        if ($shuoshuoId) {
            $shuoshuo = Shuoshuo::find($shuoshuoId);
            if ($shuoshuo && (int)$shuoshuo->is_public === 1) {
                return $shuoshuo;
            }
        }
        return null;
    }

    private function backWithError(string $message): never
    {
        Session::flash('comment_error', $message);
        $this->back();
    }

    private function back(): never
    {
        $ref = $_SERVER['HTTP_REFERER'] ?? '/';
        Response::redirect($ref);
    }
}
