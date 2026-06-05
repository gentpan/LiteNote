<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Models\Comment;
use App\Models\User;

final class CommentMailer
{
    public static function notifyNewComment(Comment $comment, array $target): void
    {
        $to = self::notifyTo();
        if ($to === '') {
            return;
        }

        self::send($to, '你有一条新评论', self::render('新评论', $comment, $target, null));
    }

    public static function notifyReply(Comment $reply, Comment $parent, array $target): void
    {
        $to = trim((string)($parent->email ?? ''));
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        if (strcasecmp($to, trim((string)($reply->email ?? ''))) === 0) {
            return;
        }

        $nickname = html_entity_decode((string)($reply->nickname ?? '读者'), ENT_QUOTES, 'UTF-8');
        self::send($to, $nickname . ' 回复了你的评论', self::render('评论回复', $reply, $target, $parent));
    }

    private static function send(string $to, string $subject, string $html): void
    {
        $cfg = Config::get('mail.sendflare', []);
        $enabled = (bool)($cfg['enabled'] ?? false);
        $token = trim((string)($cfg['token'] ?? ''));
        $from = trim((string)($cfg['from'] ?? ''));
        if (!$enabled || $token === '' || $from === '' || !function_exists('curl_init')) {
            return;
        }

        $fromName = trim((string)($cfg['from_name'] ?? 'LiteNote'));
        $payload = [
            'from' => $fromName !== '' ? sprintf('%s <%s>', $fromName, $from) : $from,
            'to' => $to,
            'subject' => $subject,
            'body' => $html,
        ];

        $ch = curl_init((string)($cfg['endpoint'] ?? 'https://api.sendflare.com/v1/send'));
        if ($ch === false) {
            return;
        }
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 6,
        ]);
        curl_exec($ch);
        unset($ch);
    }

    private static function notifyTo(): string
    {
        $configured = trim((string)Config::get('mail.sendflare.notify_to', ''));
        if ($configured !== '' && filter_var($configured, FILTER_VALIDATE_EMAIL)) {
            return $configured;
        }

        $admin = User::findBy('role', 'admin');
        $email = trim((string)($admin?->email ?? ''));
        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
    }

    private static function render(string $title, Comment $comment, array $target, ?Comment $parent): string
    {
        $siteName = (string)Config::get('site.title', Config::get('app.name', 'LiteNote'));
        $targetTitle = (string)($target['title'] ?? '内容');
        $targetUrl = (string)($target['url'] ?? '');
        $nickname = (string)($comment->nickname ?? '读者');
        $rawContent = html_entity_decode((string)$comment->content, ENT_QUOTES, 'UTF-8');
        $content = nl2br(htmlspecialchars($rawContent, ENT_QUOTES, 'UTF-8'));
        $parentHtml = '';
        if ($parent) {
            $rawParentContent = html_entity_decode((string)$parent->content, ENT_QUOTES, 'UTF-8');
            $parentHtml = '<div style="margin:16px 0;padding:12px 14px;border-left:3px solid #E65A4C;background:#FBF9F6;color:#6F6A64;font-size:14px;line-height:1.7;">'
                . '<strong style="color:#1A1A1A;">原评论：</strong><br>'
                . nl2br(htmlspecialchars($rawParentContent, ENT_QUOTES, 'UTF-8'))
                . '</div>';
        }

        return '<!doctype html><html><body style="margin:0;padding:0;background:#F4F1ED;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,PingFang SC,sans-serif;color:#1A1A1A;">'
            . '<div style="max-width:560px;margin:0 auto;padding:28px 16px;">'
            . '<div style="background:#fff;border:1px solid #E5E2DE;border-radius:14px;overflow:hidden;">'
            . '<div style="padding:22px 24px;background:#1A1A1A;color:#fff;">'
            . '<div style="font-size:12px;letter-spacing:0;color:#F4B8AF;">' . htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') . '</div>'
            . '<h1 style="margin:6px 0 0;font-size:22px;line-height:1.35;">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1>'
            . '</div>'
            . '<div style="padding:22px 24px;">'
            . '<p style="margin:0 0 12px;color:#8B8680;font-size:14px;">来自 <strong style="color:#1A1A1A;">' . htmlspecialchars($nickname, ENT_QUOTES, 'UTF-8') . '</strong>，目标：' . htmlspecialchars($targetTitle, ENT_QUOTES, 'UTF-8') . '</p>'
            . $parentHtml
            . '<div style="padding:16px 18px;border-radius:10px;background:#FCEAE7;color:#1A1A1A;font-size:15px;line-height:1.8;">' . $content . '</div>'
            . ($targetUrl !== '' ? '<p style="margin:22px 0 0;"><a href="' . htmlspecialchars($targetUrl, ENT_QUOTES, 'UTF-8') . '" style="display:inline-block;padding:9px 14px;border-radius:999px;background:#E65A4C;color:#fff;text-decoration:none;font-size:14px;">查看原文</a></p>' : '')
            . '</div></div></div></body></html>';
    }
}
