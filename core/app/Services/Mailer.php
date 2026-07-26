<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Http;
use App\Models\MailLog;
use App\Models\MailUnsubscribe;
use App\Models\Setting;
use App\Models\User;

/**
 * 通用邮件发送中心。
 *
 * 支持:
 * - SendFlare API
 * - SMTP
 */
final class Mailer
{
    public static function isConfigured(): bool
    {
        $cfg = self::settings();
        if (!(bool)($cfg['enabled'] ?? false)) {
            return false;
        }
        if (!filter_var((string)($cfg['from'] ?? ''), FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        return match ((string)($cfg['driver'] ?? 'sendflare')) {
            'smtp' => trim((string)($cfg['smtp_host'] ?? '')) !== '',
            'sendflare' => trim((string)($cfg['sendflare_token'] ?? '')) !== '' && function_exists('curl_init'),
            default => false,
        };
    }

    /**
     * 发送一封 HTML 邮件,成功返回 true。
     *
     * @param array<string,mixed> $options
     */
    public static function send(string $to, string $subject, string $html, array $options = []): bool
    {
        $to = trim($to);
        $type = (string)($options['type'] ?? 'system');
        $respectUnsubscribe = (bool)($options['respect_unsubscribe'] ?? true);

        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            self::log('', $type, $to, $subject, 'failed', '收件人邮箱无效');
            return false;
        }

        if ($respectUnsubscribe && MailUnsubscribe::isUnsubscribed($to, $type)) {
            self::log('', $type, $to, $subject, 'skipped', '收件人已退订');
            return false;
        }

        if (!self::isConfigured()) {
            self::log('', $type, $to, $subject, 'failed', '邮件服务未配置或缺少运行依赖');
            return false;
        }

        $cfg = self::settings();
        $driver = (string)($cfg['driver'] ?? 'sendflare');
        $text = trim((string)($options['text'] ?? ''));
        if ($text === '') {
            $text = self::htmlToText($html);
        }
        $headers = (array)($options['headers'] ?? []);
        if ($respectUnsubscribe) {
            $headers['List-Unsubscribe'] = '<' . MailUnsubscribe::url($to, $type) . '>';
        }

        try {
            $ok = match ($driver) {
                'smtp' => self::sendSmtp($cfg, $to, $subject, $html, $text, $headers),
                default => self::sendSendflare($cfg, $to, $subject, $html),
            };
            self::log($driver, $type, $to, $subject, $ok ? 'sent' : 'failed', $ok ? '' : '服务商返回发送失败');
            return $ok;
        } catch (\Throwable $e) {
            self::log($driver, $type, $to, $subject, 'failed', $e->getMessage());
            return false;
        }
    }

    /**
     * @param string[] $recipients
     * @param array<string,mixed> $options
     * @return array{sent:int,failed:int}
     */
    public static function sendMany(array $recipients, string $subject, string $html, array $options = []): array
    {
        $sent = 0;
        $failed = 0;
        foreach (self::normalizeRecipients($recipients) as $email) {
            if (self::send($email, $subject, $html, $options)) {
                $sent++;
            } else {
                $failed++;
            }
        }
        return ['sent' => $sent, 'failed' => $failed];
    }

    /**
     * @return string[]
     */
    public static function adminRecipients(): array
    {
        $configured = self::splitEmails((string)self::setting('mail_notify_to', Config::get('mail.notify_to', '')));
        if ($configured !== []) {
            return $configured;
        }

        $admin = User::findBy('role', 'admin');
        $email = trim((string)($admin?->email ?? ''));
        return filter_var($email, FILTER_VALIDATE_EMAIL) ? [$email] : [];
    }

    /**
     * @return string[]
     */
    public static function postRecipients(): array
    {
        return self::splitEmails((string)self::setting('mail_post_recipients', Config::get('mail.post_recipients', '')));
    }

    public static function isEventEnabled(string $event): bool
    {
        return (bool)self::setting('mail_event_' . $event, '1');
    }

    /**
     * @return array<string,mixed>
     */
    public static function settings(): array
    {
        $legacySendflare = Config::get('mail.sendflare', []);
        $cfg = Config::get('mail', []);
        $driver = (string)self::setting('mail_driver', $cfg['driver'] ?? 'sendflare');
        $enabledDefault = (bool)($cfg['enabled'] ?? ($legacySendflare['enabled'] ?? false));

        $from = trim((string)self::setting('mail_from', $cfg['from'] ?? ($legacySendflare['from'] ?? '')));
        if ($from === '') {
            $from = trim((string)($cfg['from'] ?? ($legacySendflare['from'] ?? '')));
        }
        $fromName = trim((string)self::setting('mail_from_name', $cfg['from_name'] ?? ($legacySendflare['from_name'] ?? 'LiteNote')));
        if ($fromName === '') {
            $fromName = trim((string)($cfg['from_name'] ?? ($legacySendflare['from_name'] ?? 'LiteNote')));
        }

        return [
            'enabled' => (bool)self::setting('mail_enabled', $enabledDefault ? '1' : '0'),
            'driver' => in_array($driver, ['sendflare', 'smtp'], true) ? $driver : 'sendflare',
            'from' => $from,
            'from_name' => $fromName,
            'notify_to' => trim((string)self::setting('mail_notify_to', $cfg['notify_to'] ?? ($legacySendflare['notify_to'] ?? ''))),
            'post_recipients' => trim((string)self::setting('mail_post_recipients', $cfg['post_recipients'] ?? '')),
            'sendflare_endpoint' => trim((string)self::setting('mail_sendflare_endpoint', $cfg['sendflare']['endpoint'] ?? ($legacySendflare['endpoint'] ?? 'https://api.sendflare.com/v1/send'))),
            'sendflare_token' => self::secretSetting('mail_sendflare_token', $cfg['sendflare']['token'] ?? ($legacySendflare['token'] ?? '')),
            'smtp_host' => trim((string)self::setting('mail_smtp_host', $cfg['smtp']['host'] ?? '')),
            'smtp_port' => (int)self::setting('mail_smtp_port', (string)($cfg['smtp']['port'] ?? 587)),
            'smtp_secure' => trim((string)self::setting('mail_smtp_secure', $cfg['smtp']['secure'] ?? 'tls')),
            'smtp_username' => trim((string)self::setting('mail_smtp_username', $cfg['smtp']['username'] ?? '')),
            'smtp_password' => self::secretSetting('mail_smtp_password', $cfg['smtp']['password'] ?? ''),
            'footer' => trim((string)self::setting('mail_template_footer', '')),
        ];
    }

    public static function renderPasswordReset(string $resetUrl, string $username): string
    {
        return MailTemplate::passwordReset($resetUrl, $username);
    }

    public static function renderEmailVerify(string $verifyUrl, string $displayName): string
    {
        return MailTemplate::emailVerify($verifyUrl, $displayName);
    }

    private static function sendSendflare(array $cfg, string $to, string $subject, string $html): bool
    {
        $payload = [
            'from' => self::formatFrom((string)$cfg['from'], (string)$cfg['from_name']),
            'to' => $to,
            'subject' => $subject,
            'body' => $html,
        ];

        return self::postJson((string)$cfg['sendflare_endpoint'], [
            'Authorization: Bearer ' . (string)$cfg['sendflare_token'],
            'Content-Type: application/json; charset=utf-8',
        ], $payload);
    }

    private static function sendSmtp(array $cfg, string $to, string $subject, string $html, string $text, array $headers): bool
    {
        $host = (string)$cfg['smtp_host'];
        $port = (int)($cfg['smtp_port'] ?: 587);
        $secure = strtolower((string)($cfg['smtp_secure'] ?? 'tls'));
        $target = ($secure === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
        $fp = @stream_socket_client($target, $errno, $errstr, 12, STREAM_CLIENT_CONNECT);
        if (!is_resource($fp)) {
            throw new \RuntimeException('SMTP 连接失败: ' . ($errstr ?: (string)$errno));
        }
        stream_set_timeout($fp, 12);

        $read = static function () use ($fp): string {
            $data = '';
            while (($line = fgets($fp, 515)) !== false) {
                $data .= $line;
                if (strlen($line) < 4 || $line[3] !== '-') {
                    break;
                }
            }
            return $data;
        };
        $cmd = static function (string $command, array $okCodes = [250]) use ($fp, $read): string {
            fwrite($fp, $command . "\r\n");
            $resp = $read();
            $code = (int)substr($resp, 0, 3);
            if (!in_array($code, $okCodes, true)) {
                throw new \RuntimeException('SMTP 命令失败: ' . trim($resp));
            }
            return $resp;
        };

        $greeting = $read();
        if ((int)substr($greeting, 0, 3) !== 220) {
            throw new \RuntimeException('SMTP 服务未就绪: ' . trim($greeting));
        }

        $serverName = $_SERVER['SERVER_NAME'] ?? 'localhost';
        $cmd('EHLO ' . $serverName);
        if ($secure === 'tls') {
            $cmd('STARTTLS', [220]);
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new \RuntimeException('SMTP STARTTLS 失败');
            }
            $cmd('EHLO ' . $serverName);
        }

        $username = (string)($cfg['smtp_username'] ?? '');
        $password = (string)($cfg['smtp_password'] ?? '');
        if ($username !== '') {
            $cmd('AUTH LOGIN', [334]);
            $cmd(base64_encode($username), [334]);
            $cmd(base64_encode($password), [235]);
        }

        $from = (string)$cfg['from'];
        $cmd('MAIL FROM:<' . $from . '>');
        $cmd('RCPT TO:<' . $to . '>', [250, 251]);
        $cmd('DATA', [354]);

        $message = self::smtpMessage($cfg, $to, $subject, $html, $text, $headers);
        fwrite($fp, self::dotStuff($message) . "\r\n.\r\n");
        $resp = $read();
        $code = (int)substr($resp, 0, 3);
        $cmd('QUIT', [221, 250]);
        fclose($fp);

        return $code >= 200 && $code < 300;
    }

    private static function postJson(string $url, array $headers, array $payload): bool
    {
        $res = Http::request('POST', $url, [
            'headers' => $headers,
            'body' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'timeout' => 12,
            'default_headers' => false,
        ]);
        $code = $res['status'];
        $body = $res['body'];
        if ($code < 200 || $code >= 300) {
            throw new \RuntimeException($res['error'] !== '' ? $res['error'] : ('HTTP ' . $code . ': ' . mb_substr($body, 0, 300)));
        }
        $decoded = json_decode($body, true);
        if (is_array($decoded)) {
            if (array_key_exists('success', $decoded) && $decoded['success'] === false) {
                $messages = [];
                foreach ((array)($decoded['errors'] ?? []) as $err) {
                    if (is_array($err) && isset($err['message'])) {
                        $messages[] = (string)$err['message'];
                    }
                }
                throw new \RuntimeException($messages ? implode('; ', $messages) : '服务商返回 success=false');
            }
            if (isset($decoded['error']) && $decoded['error']) {
                throw new \RuntimeException(is_string($decoded['error']) ? $decoded['error'] : json_encode($decoded['error'], JSON_UNESCAPED_UNICODE));
            }
        }
        return true;
    }

    private static function smtpMessage(array $cfg, string $to, string $subject, string $html, string $text, array $headers): string
    {
        $boundary = 'LiteNote_' . bin2hex(random_bytes(12));
        $lines = [
            'Date: ' . date(DATE_RFC2822),
            'From: ' . self::smtpAddressHeader((string)$cfg['from'], (string)$cfg['from_name']),
            'To: <' . $to . '>',
            'Subject: ' . self::encodeHeader($subject),
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        ];
        foreach ($headers as $name => $value) {
            if (preg_match('/^[A-Za-z0-9-]+$/', (string)$name)) {
                $lines[] = (string)$name . ': ' . str_replace(["\r", "\n"], '', (string)$value);
            }
        }
        $lines[] = '';
        $lines[] = '--' . $boundary;
        $lines[] = 'Content-Type: text/plain; charset=UTF-8';
        $lines[] = 'Content-Transfer-Encoding: base64';
        $lines[] = '';
        $lines[] = chunk_split(base64_encode($text));
        $lines[] = '--' . $boundary;
        $lines[] = 'Content-Type: text/html; charset=UTF-8';
        $lines[] = 'Content-Transfer-Encoding: base64';
        $lines[] = '';
        $lines[] = chunk_split(base64_encode($html));
        $lines[] = '--' . $boundary . '--';
        return implode("\r\n", $lines);
    }

    private static function dotStuff(string $message): string
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $message);
        $stuffed = preg_replace('/^\./m', '..', $normalized) ?? $normalized;
        return str_replace("\n", "\r\n", $stuffed);
    }

    private static function htmlToText(string $html): string
    {
        $text = preg_replace('/<br\s*\/?>/i', "\n", $html) ?? $html;
        $text = preg_replace('/<\/p>/i', "\n\n", $text) ?? $text;
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        return trim(preg_replace("/\n{3,}/", "\n\n", $text) ?? $text);
    }

    private static function formatFrom(string $email, string $name): string
    {
        return $name !== '' ? sprintf('%s <%s>', $name, $email) : $email;
    }

    private static function smtpAddressHeader(string $email, string $name): string
    {
        return $name !== '' ? self::encodeHeader($name) . ' <' . $email . '>' : '<' . $email . '>';
    }

    private static function encodeHeader(string $value): string
    {
        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }

    private static function setting(string $key, mixed $default = null): mixed
    {
        try {
            return Setting::get($key, $default);
        } catch (\Throwable) {
            return $default;
        }
    }

    private static function secretSetting(string $key, mixed $default = ''): string
    {
        $value = trim((string)self::setting($key, ''));
        return $value !== '' ? $value : trim((string)$default);
    }

    private static function log(string $driver, string $type, string $recipient, string $subject, string $status, string $error): void
    {
        try {
            MailLog::record($driver, $type, $recipient, $subject, $status, $error);
        } catch (\Throwable) {
            // 邮件日志不能影响主流程。
        }
    }

    /**
     * @param string[] $recipients
     * @return string[]
     */
    private static function normalizeRecipients(array $recipients): array
    {
        return array_values(array_unique(array_filter(array_map('trim', $recipients), static function (string $email): bool {
            return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
        })));
    }

    /**
     * @return string[]
     */
    private static function splitEmails(string $emails): array
    {
        return self::normalizeRecipients(preg_split('/[,\s;]+/', $emails) ?: []);
    }
}
