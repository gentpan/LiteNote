<?php
declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\Request;
use App\Core\Response;
use App\Models\MailUnsubscribe;

final class MailController
{
    public function unsubscribe(Request $request): never
    {
        $email = trim((string)$request->input('email', ''));
        $type = trim((string)$request->input('type', 'all'));
        $token = trim((string)$request->input('token', ''));

        if (!MailUnsubscribe::verify($email, $type, $token)) {
            Response::html($this->page('退订链接无效', '这个退订链接无效或已过期。'), 400);
        }

        MailUnsubscribe::unsubscribe($email, $type, $request->ip, $request->ua);
        Response::html($this->page('退订成功', '你已经退订此类邮件。以后同类通知不会再发送到这个邮箱。'));
    }

    private function page(string $title, string $message): string
    {
        return '<!doctype html><html lang="zh-CN"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . ' - LiteNote</title>'
            . '<style>body{margin:0;background:#f4f1ed;color:#1f2937;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI","PingFang SC","Microsoft YaHei",sans-serif;}'
            . '.box{max-width:520px;margin:12vh auto;padding:28px 24px;border:1px solid #e5e2de;border-radius:8px;background:#fff;box-shadow:0 16px 36px rgba(17,24,39,.08);}'
            . 'h1{margin:0 0 12px;font-size:24px;}p{margin:0;color:#6b7280;line-height:1.8;}a{color:#0052d9;text-decoration:none;}</style></head><body>'
            . '<div class="box"><h1>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1><p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p><p style="margin-top:18px"><a href="/">返回首页</a></p></div>'
            . '</body></html>';
    }
}
