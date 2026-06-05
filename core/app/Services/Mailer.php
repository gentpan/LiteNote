<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;

/**
 * 通用邮件发送(SendFlare API)。
 * 与 CommentMailer 共用 config('mail.sendflare') 配置。
 * 未配置时 isConfigured() 返回 false,调用方据此降级。
 */
final class Mailer
{
    /**
     * 后台是否已配置邮件发送。
     */
    public static function isConfigured(): bool
    {
        $cfg = Config::get('mail.sendflare', []);
        return (bool)($cfg['enabled'] ?? false)
            && trim((string)($cfg['token'] ?? '')) !== ''
            && trim((string)($cfg['from'] ?? '')) !== ''
            && function_exists('curl_init');
    }

    /**
     * 发送一封 HTML 邮件,成功返回 true。
     */
    public static function send(string $to, string $subject, string $html): bool
    {
        if (!self::isConfigured()) {
            return false;
        }
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $cfg = Config::get('mail.sendflare', []);
        $token = trim((string)($cfg['token'] ?? ''));
        $from = trim((string)($cfg['from'] ?? ''));
        $fromName = trim((string)($cfg['from_name'] ?? 'LiteNote'));

        $payload = [
            'from' => $fromName !== '' ? sprintf('%s <%s>', $fromName, $from) : $from,
            'to' => $to,
            'subject' => $subject,
            'body' => $html,
        ];

        $ch = curl_init((string)($cfg['endpoint'] ?? 'https://api.sendflare.com/v1/send'));
        if ($ch === false) {
            return false;
        }
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 8,
        ]);
        curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        unset($ch);

        return $code >= 200 && $code < 300;
    }

    /**
     * 渲染密码重置邮件正文。
     */
    public static function renderPasswordReset(string $resetUrl, string $username): string
    {
        $siteName = (string)Config::get('site.title', Config::get('app.name', 'LiteNote'));
        $safeUrl = htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8');
        $safeName = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');

        return '<!doctype html><html><body style="margin:0;padding:0;background:#F4F1ED;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,PingFang SC,sans-serif;color:#1A1A1A;">'
            . '<div style="max-width:560px;margin:0 auto;padding:28px 16px;">'
            . '<div style="background:#fff;border:1px solid #E5E2DE;border-radius:14px;overflow:hidden;">'
            . '<div style="padding:22px 24px;background:#1A1A1A;color:#fff;">'
            . '<div style="font-size:12px;color:#F4B8AF;">' . htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') . '</div>'
            . '<h1 style="margin:6px 0 0;font-size:22px;line-height:1.35;">重置后台密码</h1>'
            . '</div>'
            . '<div style="padding:22px 24px;">'
            . '<p style="margin:0 0 12px;color:#8B8680;font-size:14px;">你好 <strong style="color:#1A1A1A;">' . $safeName . '</strong>,我们收到了重置后台登录密码的请求。</p>'
            . '<p style="margin:0 0 16px;font-size:15px;line-height:1.8;">点击下面的按钮设置新密码,链接 <strong>1 小时</strong>内有效:</p>'
            . '<p style="margin:0 0 18px;"><a href="' . $safeUrl . '" style="display:inline-block;padding:11px 18px;border-radius:999px;background:#E65A4C;color:#fff;text-decoration:none;font-size:14px;">设置新密码</a></p>'
            . '<p style="margin:0 0 6px;color:#8B8680;font-size:13px;">如果按钮无法点击,请复制以下链接到浏览器打开:</p>'
            . '<p style="margin:0 0 16px;word-break:break-all;font-size:13px;"><a href="' . $safeUrl . '" style="color:#E65A4C;">' . $safeUrl . '</a></p>'
            . '<p style="margin:0;color:#B5B0A9;font-size:12px;">如果这不是你本人操作,请忽略本邮件,你的密码不会被更改。</p>'
            . '</div></div></div></body></html>';
    }
}
