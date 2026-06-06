<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Comment;

final class CommentMailer
{
    public static function notifyNewComment(Comment $comment, array $target): void
    {
        if (!Mailer::isEventEnabled('comment_admin')) {
            return;
        }

        $recipients = Mailer::adminRecipients();
        if ($recipients === []) {
            return;
        }

        Mailer::sendMany($recipients, '你有一条新评论', MailTemplate::newComment($comment, $target), [
            'type' => 'comment_admin',
            'respect_unsubscribe' => false,
        ]);
    }

    public static function notifyReply(Comment $reply, Comment $parent, array $target): void
    {
        if (!Mailer::isEventEnabled('comment_reply')) {
            return;
        }

        $to = trim((string)($parent->email ?? ''));
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        if (strcasecmp($to, trim((string)($reply->email ?? ''))) === 0) {
            return;
        }

        $nickname = html_entity_decode((string)($reply->nickname ?? '读者'), ENT_QUOTES, 'UTF-8');
        Mailer::send($to, $nickname . ' 回复了你的评论', MailTemplate::reply($reply, $parent, $target), [
            'type' => 'comment_reply',
            'respect_unsubscribe' => true,
        ]);
    }
}
