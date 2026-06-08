<?php
declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Enums\CommentStatus;
use App\Models\Comment;
use App\Models\Music;
use App\Models\Page;
use App\Models\Post;
use App\Models\Talk;
use App\Services\CommentModerationService;
use App\Services\Notifications;
use App\Services\CommentSettingsService;
use App\Services\IpGeoService;

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
    private bool $isAjax = false;

    public function submit(Request $request): never
    {
        $this->isAjax = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';

        $data = [
            'post_id'   => (int) $request->input('post_id', 0),
            'page_id'   => (int) $request->input('page_id', 0),
            'talk_id'=> (int) $request->input('talk_id', 0),
            'music_id'=> (int) $request->input('music_id', 0),
            'x_tweet_id'=> (int) $request->input('x_tweet_id', 0),
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

        $target = $this->resolveTarget((int)$data['post_id'], (int)$data['page_id'], (int)$data['talk_id'], (int)$data['music_id'], (int)$data['x_tweet_id']);
        if (!$target) {
            $this->backWithError('评论目标不存在');
        }

        $targetType = CommentSettingsService::typeFromIds((int)$data['post_id'], (int)$data['page_id'], (int)$data['talk_id'], (int)$data['music_id'], (int)$data['x_tweet_id']);
        if (!CommentSettingsService::enabledFor($targetType, $target)) {
            $this->backWithError('当前内容已关闭评论');
        }
        if ((int)$data['parent_id'] > 0 && !CommentSettingsService::repliesEnabled()) {
            $this->backWithError('当前不允许回复评论');
        }

        // 评论验证码(开关开启且非登录管理员时校验,一次性使用)
        $isAdmin = (int) Session::get('admin_user.id', 0) > 0;
        if (!$isAdmin && CommentSettingsService::captchaEnabled()) {
            $captchaInput = strtolower(trim((string) $request->input('captcha', '')));
            $captchaCode  = (string) Session::get('_captcha', '');
            Session::forget('_captcha');
            if ($captchaCode === '' || $captchaInput !== $captchaCode) {
                $this->backWithError('验证码错误，请重新输入');
            }
        }

        $rules = [
            'nickname' => 'required|string|min:1|max:50',
            'content'  => 'required|string|min:2|max:2000',
        ];
        if (CommentSettingsService::emailRequired()) {
            $rules['email'] = 'required|email';
        } elseif (trim((string)$data['email']) !== '') {
            $rules['email'] = 'email';
        }

        $validator = \App\Core\Validator::make($data, $rules);
        if (!$validator->validate()) {
            $this->backWithError($validator->firstError() ?? '校验失败');
        }

        $content = trim((string)$data['content']);
        if ($this->isSpam($content)) {
            $this->backWithError('评论包含过多链接，已被拦截');
        }

        $parentId = $this->normalizeParentId((int)$data['parent_id'], (int)$data['post_id'], (int)$data['page_id'], (int)$data['talk_id'], (int)$data['music_id'], (int)$data['x_tweet_id']);

        $needAudit = CommentSettingsService::needAudit();
        $defaultStatus = $needAudit ? CommentStatus::Pending->value : CommentStatus::Approved->value;
        $status = CommentModerationService::statusFor(trim((string)$data['email']), $request->ip, $defaultStatus);
        Comment::ensureGeoColumns();
        $geo = IpGeoService::lookup($request->ip);

        $cmt = new Comment([
            'post_id'   => (int)$data['post_id'],
            'page_id'   => (int)$data['page_id'],
            'talk_id'=> (int)$data['talk_id'],
            'music_id'=> (int)$data['music_id'],
            'x_tweet_id'=> (int)$data['x_tweet_id'],
            'parent_id' => $parentId,
            'nickname'  => htmlspecialchars(trim((string)$data['nickname']), ENT_QUOTES, 'UTF-8'),
            'email'     => trim((string)$data['email']),
            'website'   => trim((string)$data['website']),
            'content'   => htmlspecialchars($content, ENT_QUOTES, 'UTF-8'),
            'ip'        => $request->ip,
            'ua'        => mb_substr($request->ua, 0, 250),
            'geo_country_code' => $geo['geo_country_code'] ?? '',
            'geo_country' => $geo['geo_country'] ?? '',
            'geo_region' => $geo['geo_region'] ?? '',
            'geo_city' => $geo['geo_city'] ?? '',
            'geo_data' => $geo['geo_data'] ?? '',
            'status'    => $status,
        ]);
        $cmt->save();

        if ($status === CommentStatus::Approved->value) {
            Comment::syncCountForPost((int)$data['post_id']);
            Comment::syncCountForTalk((int)$data['talk_id']);
            Comment::syncCountForMusic((int)$data['music_id']);
            Comment::syncCountForXTweet((int)$data['x_tweet_id']);
        }

        if ($status !== CommentStatus::Spam->value) {
            $this->sendNotifications(
                $cmt,
                $target,
                (int)$data['post_id'],
                (int)$data['page_id'],
                (int)$data['talk_id'],
                (int)$data['music_id'],
                (int)$data['x_tweet_id'],
                $parentId,
                $status,
                $request
            );
        }

        $isPending = $status !== CommentStatus::Approved->value;
        $successMsg = $isPending ? '评论已提交，等待审核后显示' : '评论发布成功';

        if ($this->isAjax) {
            $resp = [
                'code' => 0,
                'msg' => $successMsg,
                'pending' => $isPending,
                'avatar_url' => $cmt->getAvatarUrl(80),
                'comment' => [
                    'id'       => (int) $cmt->id,
                    'nickname' => trim((string) $data['nickname']),
                    'website'  => trim((string) $data['website']),
                    'content'  => $content,
                    'time'     => \App\Core\Helper::timeTag(date('Y-m-d H:i:s')),
                    'avatar_url' => $cmt->getAvatarUrl(80),
                    'location' => $cmt->frontLocationLabel(),
                    'parent_id'=> $parentId,
                    'post_id'  => (int) $data['post_id'],
                    'page_id'  => (int) $data['page_id'],
                    'talk_id'  => (int) $data['talk_id'],
                    'music_id' => (int) $data['music_id'],
                    'pending'  => $isPending,
                ],
            ];
            Response::json($resp);
        }

        Session::flash('comment_success', $successMsg);
        $this->back();
    }

    private function isSpam(string $content): bool
    {
        $linkCount = preg_match_all('#https?://#i', $content);
        return $linkCount > 3;
    }

    private function resolveTarget(int $postId, int $pageId, int $talkId, int $musicId, int $xTweetId = 0): ?object
    {
        if ($postId) {
            return Post::find($postId);
        }
        if ($pageId) {
            return Page::find($pageId);
        }
        if ($talkId) {
            $talk = Talk::find($talkId);
            if ($talk && (int)$talk->is_public === 1) {
                return $talk;
            }
        }
        if ($musicId) {
            $music = Music::find($musicId);
            if ($music && (int)$music->is_public === 1) {
                return $music;
            }
        }
        if ($xTweetId) {
            // 不依赖插件类:对 x_tweets 表做通用查询(插件禁用 / 表不存在时静默容错)。
            try {
                $row = Comment::db()->fetchOne('SELECT id, is_public FROM x_tweets WHERE id = ? LIMIT 1', [$xTweetId]);
                if ($row && (int)($row['is_public'] ?? 0) === 1) {
                    return (object)$row;
                }
            } catch (\Throwable) {
            }
        }
        return null;
    }

    private function normalizeParentId(int $parentId, int $postId, int $pageId, int $talkId, int $musicId, int $xTweetId = 0): int
    {
        if ($parentId <= 0) {
            return 0;
        }

        $parent = Comment::find($parentId);
        if (!$parent) {
            return 0;
        }

        if ($postId > 0 && (int)$parent->post_id === $postId) {
            return $parentId;
        }

        if ($pageId > 0 && (int)$parent->page_id === $pageId) {
            return $parentId;
        }

        if ($talkId > 0 && (int)$parent->talk_id === $talkId) {
            return $parentId;
        }

        if ($musicId > 0 && (int)$parent->music_id === $musicId) {
            return $parentId;
        }

        if ($xTweetId > 0 && (int)$parent->x_tweet_id === $xTweetId) {
            return $parentId;
        }

        return 0;
    }

    private function sendNotifications(
        Comment $comment,
        object $target,
        int $postId,
        int $pageId,
        int $talkId,
        int $musicId,
        int $xTweetId,
        int $parentId,
        string $status,
        Request $request
    ): void {
        try {
            $targetInfo = $this->targetInfo($target, $postId, $pageId, $talkId, $musicId, $xTweetId, $request);
            Notifications::newComment($comment, $targetInfo);

            if ($status === CommentStatus::Approved->value && $parentId > 0) {
                $parent = Comment::find($parentId);
                if ($parent && (int)$parent->id !== (int)$comment->id) {
                    Notifications::commentReply($comment, $parent, $targetInfo);
                }
            }
        } catch (\Throwable) {
            // 邮件通知不能影响评论发布。
        }
    }

    private function targetInfo(object $target, int $postId, int $pageId, int $talkId, int $musicId, int $xTweetId, Request $request): array
    {
        if ($postId > 0) {
            return [
                'title' => (string)($target->title ?? '文章'),
                'url' => $this->absoluteUrl(method_exists($target, 'getUrl') ? (string)$target->getUrl() : '/', $request),
            ];
        }

        if ($pageId > 0) {
            return [
                'title' => '页面：' . (string)($target->title ?? ('#' . $pageId)),
                'url' => $this->absoluteUrl((string)($target->getUrl() ?? '/'), $request),
            ];
        }

        if ($musicId > 0) {
            return [
                'title' => '音乐：' . (string)($target->title ?? ('#' . $musicId)),
                'url' => $this->absoluteUrl('/music#music-comments', $request),
            ];
        }

        if ($xTweetId > 0) {
            return [
                'title' => '推文分享 #' . $xTweetId,
                'url' => $this->absoluteUrl('/#x-tweet-' . $xTweetId, $request),
            ];
        }

        return [
            'title' => '滔客 #' . $talkId,
            'url' => $this->absoluteUrl('/talk#talk-' . $talkId, $request),
        ];
    }

    private function absoluteUrl(string $path, Request $request): string
    {
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        $scheme = (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']))
            ? (string)$_SERVER['HTTP_X_FORWARDED_PROTO']
            : ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http');
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if ($host === '') {
            return $path;
        }

        return $scheme . '://' . $host . $path;
    }

    private function backWithError(string $message): never
    {
        if ($this->isAjax) {
            Response::json(['code' => 1, 'msg' => $message]);
        }
        Session::flash('comment_error', $message);
        $this->back();
    }

    private function back(): never
    {
        $ref = $_SERVER['HTTP_REFERER'] ?? '/';
        Response::redirect($ref);
    }
}
