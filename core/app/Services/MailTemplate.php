<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Helper;
use App\Models\Comment;
use App\Models\Link;
use App\Models\MailUnsubscribe;
use App\Models\Post;

final class MailTemplate
{
    public static function passwordReset(string $resetUrl, string $username): string
    {
        $body = '<p>你好 <strong>' . self::e($username) . '</strong>，我们收到了重置后台登录密码的请求。</p>'
            . '<p>点击下面的按钮设置新密码，链接 <strong>1 小时</strong>内有效。</p>'
            . '<p class="muted">如果这不是你本人操作，请忽略本邮件，你的密码不会被更改。</p>';

        return self::layout('重置后台密码', $body, [
            'button_url' => $resetUrl,
            'button_text' => '设置新密码',
        ]);
    }

    public static function emailVerify(string $verifyUrl, string $displayName): string
    {
        $body = '<p>你好 <strong>' . self::e($displayName) . '</strong>，感谢注册成为本站读者。</p>'
            . '<p>请点击下面的按钮验证邮箱，激活账号后即可登录。链接 <strong>24 小时</strong>内有效。</p>'
            . '<p class="muted">如果这不是你本人操作，请忽略本邮件。</p>';

        return self::layout('验证读者邮箱', $body, [
            'button_url' => $verifyUrl,
            'button_text' => '验证邮箱并登录',
        ]);
    }

    public static function newComment(Comment $comment, array $target): string
    {
        $content = self::commentContent($comment);
        $body = '<p>来自 <strong>' . self::e((string)($comment->nickname ?? '读者')) . '</strong> 的新评论。</p>'
            . '<p class="muted">目标：' . self::e((string)($target['title'] ?? '内容')) . '</p>'
            . '<div class="quote">' . $content . '</div>';

        return self::layout('你有一条新评论', $body, [
            'button_url' => (string)($target['url'] ?? ''),
            'button_text' => '查看原文',
        ]);
    }

    public static function reply(Comment $reply, Comment $parent, array $target): string
    {
        $body = '<p><strong>' . self::e((string)($reply->nickname ?? '读者')) . '</strong> 回复了你的评论。</p>'
            . '<p class="muted">目标：' . self::e((string)($target['title'] ?? '内容')) . '</p>'
            . '<div class="quote quote-muted"><strong>你的评论：</strong><br>' . self::commentContent($parent) . '</div>'
            . '<div class="quote">' . self::commentContent($reply) . '</div>';

        return self::layout('你的评论有新回复', $body, [
            'button_url' => (string)($target['url'] ?? ''),
            'button_text' => '查看回复',
            'unsubscribe_url' => MailUnsubscribe::url((string)($parent->email ?? ''), 'comment_reply'),
        ]);
    }

    public static function postPublished(Post $post): string
    {
        $title = (string)($post->title ?? '新文章');
        $summary = trim((string)($post->summary ?? ''));
        if ($summary === '') {
            $summary = Helper::truncate($post->markdown(), 180);
        }

        $body = '<p>站点发布了新文章：</p>'
            . '<h2>' . self::e($title) . '</h2>'
            . ($summary !== '' ? '<div class="quote">' . nl2br(self::e($summary)) . '</div>' : '');

        return self::layout('新文章：' . $title, $body, [
            'button_url' => Helper::url($post->getUrl()),
            'button_text' => '阅读文章',
        ]);
    }

    public static function linkApproved(Link $link, string $email): string
    {
        $body = '<p>你的友情链接申请已经通过。</p>'
            . '<div class="quote">'
            . '<strong>' . self::e((string)$link->name) . '</strong><br>'
            . self::e((string)$link->url)
            . '</div>';

        return self::layout('友链审核通过', $body, [
            'button_url' => Helper::url('/links'),
            'button_text' => '查看友情链接',
            'unsubscribe_url' => MailUnsubscribe::url($email, 'link'),
        ]);
    }

    public static function test(string $driver): string
    {
        $body = '<p>这是一封 LiteNote 邮件系统测试邮件。</p>'
            . '<p class="muted">当前驱动：' . self::e($driver) . '</p>';

        return self::layout('邮件发送测试', $body);
    }

    /**
     * @param array<string,string> $options
     */
    private static function layout(string $title, string $body, array $options = []): string
    {
        $siteName = (string)Config::get('site.title', Config::get('app.name', 'LiteNote'));
        $buttonUrl = trim((string)($options['button_url'] ?? ''));
        $buttonText = trim((string)($options['button_text'] ?? '查看'));
        $unsubscribeUrl = trim((string)($options['unsubscribe_url'] ?? ''));
        $footer = trim((string)(Mailer::settings()['footer'] ?? ''));

        $button = $buttonUrl !== ''
            ? '<p class="action"><a href="' . self::e($buttonUrl) . '">' . self::e($buttonText) . '</a></p>'
            : '';
        $unsubscribe = $unsubscribeUrl !== ''
            ? '<p class="unsubscribe"><a href="' . self::e($unsubscribeUrl) . '">退订此类邮件</a></p>'
            : '';
        $footerHtml = $footer !== '' ? '<p class="footer-note">' . nl2br(self::e($footer)) . '</p>' : '';

        return '<!doctype html><html><head><meta charset="UTF-8">'
            . '<style>'
            . 'body{margin:0;padding:0;background:#f4f1ed;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI","PingFang SC","Microsoft YaHei",sans-serif;color:#1a1a1a;}'
            . '.wrap{max-width:600px;margin:0 auto;padding:28px 16px;}'
            . '.card{overflow:hidden;border:1px solid #e5e2de;border-radius:8px;background:#fff;}'
            . '.head{padding:22px 24px;background:#111827;color:#fff;}'
            . '.site{font-size:12px;color:#9ac2ff;}'
            . 'h1{margin:6px 0 0;font-size:22px;line-height:1.35;}'
            . 'h2{margin:8px 0 12px;font-size:19px;line-height:1.45;}'
            . '.body{padding:22px 24px;font-size:15px;line-height:1.8;}'
            . 'p{margin:0 0 13px;}'
            . '.muted{color:#7b756e;font-size:14px;}'
            . '.quote{margin:15px 0;padding:14px 16px;border-left:3px solid #0052d9;background:#f2f6ff;color:#1a1a1a;}'
            . '.quote-muted{border-left-color:#d1d5db;background:#f8fafc;color:#4b5563;}'
            . '.action{margin:20px 0 0;}'
            . '.action a{display:inline-block;padding:10px 16px;border-radius:0;background:#0052d9;color:#fff;text-decoration:none;font-size:14px;font-weight:700;}'
            . '.unsubscribe,.footer-note{margin:18px 0 0;color:#9a948d;font-size:12px;line-height:1.6;}'
            . '.unsubscribe a{color:#7b756e;}'
            . '</style></head><body><div class="wrap"><div class="card">'
            . '<div class="head"><div class="site">' . self::e($siteName) . '</div><h1>' . self::e($title) . '</h1></div>'
            . '<div class="body">' . $body . $button . $footerHtml . $unsubscribe . '</div>'
            . '</div></div></body></html>';
    }

    private static function commentContent(Comment $comment): string
    {
        $raw = html_entity_decode((string)($comment->content ?? ''), ENT_QUOTES, 'UTF-8');
        return nl2br(self::e($raw));
    }

    private static function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
