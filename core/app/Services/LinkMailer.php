<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Link;

final class LinkMailer
{
    public static function notifyApproved(Link $link, string $email): void
    {
        $email = trim($email);
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || !Mailer::isEventEnabled('link')) {
            return;
        }

        Mailer::send($email, '友链审核通过', MailTemplate::linkApproved($link, $email), [
            'type' => 'link',
            'respect_unsubscribe' => true,
        ]);
    }
}
