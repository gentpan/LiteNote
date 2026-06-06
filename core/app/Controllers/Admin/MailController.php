<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\MailLog;
use App\Models\MailUnsubscribe;
use App\Models\Setting;
use App\Services\Mailer;
use App\Services\MailTemplate;

final class MailController
{
    private const MAIL_SETTINGS_URL = '/admin/settings/mail';

    public function index(): string
    {
        $this->ensureDefaults();
        MailLog::ensureTable();
        MailUnsubscribe::ensureTable();

        return View::render('mail.index', [
            'settings' => Mailer::settings(),
            'logs' => MailLog::recent(30),
            'stats' => MailLog::stats(),
            'configured' => Mailer::isConfigured(),
            'csrf' => Session::csrfToken(),
            'pageTitle' => '系统设置',
        ], 'layouts.admin');
    }

    public function save(Request $request): never
    {
        $this->ensureDefaults();
        $data = (array)$request->input('mail', []);
        $allowed = [
            'mail_enabled',
            'mail_driver',
            'mail_from',
            'mail_from_name',
            'mail_notify_to',
            'mail_post_recipients',
            'mail_template_footer',
            'mail_sendflare_endpoint',
            'mail_sendflare_token',
            'mail_smtp_host',
            'mail_smtp_port',
            'mail_smtp_secure',
            'mail_smtp_username',
            'mail_smtp_password',
            'mail_event_comment_admin',
            'mail_event_comment_reply',
            'mail_event_post',
            'mail_event_link',
        ];
        $secrets = [
            'mail_sendflare_token',
            'mail_smtp_password',
        ];

        foreach ($allowed as $key) {
            $value = $data[$key] ?? null;
            if (str_starts_with($key, 'mail_event_') || $key === 'mail_enabled') {
                $value = isset($data[$key]) ? '1' : '0';
            }
            if ($value === null) {
                continue;
            }
            $value = trim((string)$value);
            if (in_array($key, $secrets, true) && $value === '') {
                continue;
            }
            if ($key === 'mail_driver' && !in_array($value, ['sendflare', 'smtp'], true)) {
                $value = 'sendflare';
            }
            Setting::set($key, $value);
        }

        Session::flash('success', '邮件设置已保存');
        Response::redirect(self::MAIL_SETTINGS_URL);
    }

    public function test(Request $request): never
    {
        $to = trim((string)$request->input('test_to', ''));
        if ($to === '') {
            $to = Mailer::adminRecipients()[0] ?? '';
        }
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            Session::flash('error', '请填写有效的测试收件邮箱');
            Response::redirect(self::MAIL_SETTINGS_URL);
        }

        $settings = Mailer::settings();
        $ok = Mailer::send($to, 'LiteNote 邮件测试', MailTemplate::test((string)$settings['driver']), [
            'type' => 'test',
            'respect_unsubscribe' => false,
        ]);

        Session::flash($ok ? 'success' : 'error', $ok ? '测试邮件已发送' : '测试邮件发送失败，请查看邮件日志');
        Response::redirect(self::MAIL_SETTINGS_URL);
    }

    private function ensureDefaults(): void
    {
        $cfg = Mailer::settings();
        Setting::ensureDefaults([
            ['k' => 'mail_enabled', 'v' => !empty($cfg['enabled']) ? '1' : '0', 'type' => 'bool', 'label' => '启用邮件', 'group_name' => 'mail', 'sort' => 1],
            ['k' => 'mail_driver', 'v' => (string)($cfg['driver'] ?? 'sendflare'), 'label' => '邮件驱动', 'group_name' => 'mail', 'sort' => 2],
            ['k' => 'mail_from', 'v' => (string)($cfg['from'] ?? 'noreply@example.com'), 'label' => '发件邮箱', 'group_name' => 'mail', 'sort' => 3],
            ['k' => 'mail_from_name', 'v' => (string)($cfg['from_name'] ?? 'LiteNote'), 'label' => '发件名称', 'group_name' => 'mail', 'sort' => 4],
            ['k' => 'mail_notify_to', 'v' => (string)($cfg['notify_to'] ?? ''), 'label' => '管理员通知邮箱', 'group_name' => 'mail', 'sort' => 5],
            ['k' => 'mail_post_recipients', 'v' => (string)($cfg['post_recipients'] ?? ''), 'label' => '新文章通知收件人', 'group_name' => 'mail', 'sort' => 6],
            ['k' => 'mail_template_footer', 'v' => '', 'type' => 'text', 'label' => '邮件页脚', 'group_name' => 'mail', 'sort' => 7],
            ['k' => 'mail_sendflare_endpoint', 'v' => (string)($cfg['sendflare_endpoint'] ?? 'https://api.sendflare.com/v1/send'), 'label' => 'SendFlare Endpoint', 'group_name' => 'mail', 'sort' => 20],
            ['k' => 'mail_sendflare_token', 'v' => '', 'type' => 'password', 'label' => 'SendFlare Token', 'group_name' => 'mail', 'sort' => 21],
            ['k' => 'mail_smtp_host', 'v' => (string)($cfg['smtp_host'] ?? ''), 'label' => 'SMTP Host', 'group_name' => 'mail', 'sort' => 50],
            ['k' => 'mail_smtp_port', 'v' => (string)($cfg['smtp_port'] ?? 587), 'label' => 'SMTP Port', 'group_name' => 'mail', 'sort' => 51],
            ['k' => 'mail_smtp_secure', 'v' => (string)($cfg['smtp_secure'] ?? 'tls'), 'label' => 'SMTP 加密', 'group_name' => 'mail', 'sort' => 52],
            ['k' => 'mail_smtp_username', 'v' => (string)($cfg['smtp_username'] ?? ''), 'label' => 'SMTP 用户名', 'group_name' => 'mail', 'sort' => 53],
            ['k' => 'mail_smtp_password', 'v' => '', 'type' => 'password', 'label' => 'SMTP 密码', 'group_name' => 'mail', 'sort' => 54],
            ['k' => 'mail_event_comment_admin', 'v' => '1', 'type' => 'bool', 'label' => '新评论通知管理员', 'group_name' => 'mail', 'sort' => 70],
            ['k' => 'mail_event_comment_reply', 'v' => '1', 'type' => 'bool', 'label' => '评论回复通知访客', 'group_name' => 'mail', 'sort' => 71],
            ['k' => 'mail_event_post', 'v' => '1', 'type' => 'bool', 'label' => '发布新文章通知', 'group_name' => 'mail', 'sort' => 72],
            ['k' => 'mail_event_link', 'v' => '1', 'type' => 'bool', 'label' => '友链审核通过通知', 'group_name' => 'mail', 'sort' => 73],
        ]);
    }
}
