<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Comment;
use App\Models\Link;
use App\Models\Post;

/**
 * 邮件通知编排层。
 *
 * 合并原 CommentMailer / PostMailer / LinkMailer:这三者都是
 * "事件开关 → 解析收件人 → 渲染模板 → 调 Mailer" 的同构薄封装。
 * 这里集中收口,并抽出 dispatchMany/dispatchOne 两个公共骨架。
 *
 * 传输层(Mailer)与视图层(MailTemplate)仍保持独立。
 */
final class Notifications
{
    /** 新评论通知管理员(不受退订限制)。 */
    public static function newComment(Comment $comment, array $target): void
    {
        self::dispatchMany(
            'comment_admin',
            Mailer::adminRecipients(),
            '你有一条新评论',
            MailTemplate::newComment($comment, $target),
            false
        );
    }

    /** 评论回复通知原评论者(回复者本人除外)。 */
    public static function commentReply(Comment $reply, Comment $parent, array $target): void
    {
        $to = trim((string)($parent->email ?? ''));
        // 回复自己不通知。
        if ($to === '' || strcasecmp($to, trim((string)($reply->email ?? ''))) === 0) {
            return;
        }
        $nickname = html_entity_decode((string)($reply->nickname ?? '读者'), ENT_QUOTES, 'UTF-8');
        self::dispatchOne('comment_reply', $to, $nickname . ' 回复了你的评论', MailTemplate::reply($reply, $parent, $target));
    }

    /** 新文章通知订阅者。 */
    public static function postPublished(Post $post): void
    {
        $title = '新文章：' . (string)($post->title ?? '未命名文章');
        self::dispatchMany('post', Mailer::postRecipients(), $title, MailTemplate::postPublished($post), true);
    }

    /** 友链审核通过通知申请者。 */
    public static function linkApproved(Link $link, string $email): void
    {
        self::dispatchOne('link', $email, '友链审核通过', MailTemplate::linkApproved($link, $email));
    }

    /**
     * 群发骨架:事件开关 + 收件人非空才发送。
     *
     * @param string[] $recipients
     */
    private static function dispatchMany(string $event, array $recipients, string $subject, string $html, bool $respectUnsubscribe): void
    {
        if (!Mailer::isEventEnabled($event) || $recipients === []) {
            return;
        }
        Mailer::sendMany($recipients, $subject, $html, [
            'type' => $event,
            'respect_unsubscribe' => $respectUnsubscribe,
        ]);
    }

    /** 单发骨架:事件开关 + 邮箱合法才发送(默认尊重退订)。 */
    private static function dispatchOne(string $event, string $to, string $subject, string $html): void
    {
        $to = trim($to);
        if (!Mailer::isEventEnabled($event) || $to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        Mailer::send($to, $subject, $html, [
            'type' => $event,
            'respect_unsubscribe' => true,
        ]);
    }
}
