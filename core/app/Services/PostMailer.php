<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Post;

final class PostMailer
{
    public static function notifyPublished(Post $post): void
    {
        if (!Mailer::isEventEnabled('post')) {
            return;
        }

        $recipients = Mailer::postRecipients();
        if ($recipients === []) {
            return;
        }

        $title = '新文章：' . (string)($post->title ?? '未命名文章');
        Mailer::sendMany($recipients, $title, MailTemplate::postPublished($post), [
            'type' => 'post',
            'respect_unsubscribe' => true,
        ]);
    }
}
