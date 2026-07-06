<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Helper;
use App\Core\Config;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\EnvService;

final class TelegramController
{
    private const SETTINGS_URL = '/admin/settings/telegram';
    private const ENV_KEYS = [
        'TELEGRAM_BOT_TOKEN',
        'TELEGRAM_WEBHOOK_SECRET',
        'TELEGRAM_ALLOWED_CHAT_IDS',
    ];

    public function index(): string
    {
        $values = EnvService::getMany(self::ENV_KEYS);
        $webhookUrl = $this->webhookUrl();

        return View::render('telegram.index', [
            'settings' => $values,
            'envStatus' => EnvService::status(),
            'configured' => trim((string)$values['TELEGRAM_BOT_TOKEN']) !== '' && trim((string)$values['TELEGRAM_WEBHOOK_SECRET']) !== '',
            'webhookUrl' => $webhookUrl,
            'webhookUrlWithSecret' => $webhookUrl . '/' . rawurlencode((string)$values['TELEGRAM_WEBHOOK_SECRET']),
            'setWebhookCommand' => $this->setWebhookCommand($webhookUrl, (string)$values['TELEGRAM_WEBHOOK_SECRET']),
            'csrf' => Session::csrfToken(),
            'pageTitle' => '系统设置',
        ], 'layouts.admin');
    }

    public function save(Request $request): never
    {
        $data = (array)$request->input('telegram', []);
        $current = EnvService::getMany(self::ENV_KEYS);
        $token = trim((string)($data['telegram_bot_token'] ?? ''));
        $secret = trim((string)($data['telegram_webhook_secret'] ?? ''));
        $chatIds = trim((string)($data['telegram_allowed_chat_ids'] ?? ''));

        $values = [
            'TELEGRAM_BOT_TOKEN' => $token !== '' ? $token : (string)$current['TELEGRAM_BOT_TOKEN'],
            'TELEGRAM_WEBHOOK_SECRET' => $secret,
            'TELEGRAM_ALLOWED_CHAT_IDS' => $this->sanitizeChatIds($chatIds),
        ];

        if ($values['TELEGRAM_WEBHOOK_SECRET'] === '') {
            $values['TELEGRAM_WEBHOOK_SECRET'] = bin2hex(random_bytes(16));
        }

        try {
            EnvService::setMany($values);
            Session::flash('success', 'Telegram 设置已保存');
        } catch (\Throwable $e) {
            Session::flash('error', Helper::publicErrorMessage($e));
        }

        Response::redirect(self::SETTINGS_URL);
    }

    private function webhookUrl(): string
    {
        $base = rtrim((string)Config::get('app.url', ''), '/');
        if ($base === '') {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = (string)($_SERVER['HTTP_HOST'] ?? '127.0.0.1:5555');
            $base = $scheme . '://' . $host;
        }
        return $base . '/telegram/webhook';
    }

    private function setWebhookCommand(string $webhookUrl, string $secret): string
    {
        $token = '${TELEGRAM_BOT_TOKEN}';
        $secret = $secret !== '' ? $secret : '${TELEGRAM_WEBHOOK_SECRET}';
        return 'curl -X POST "https://api.telegram.org/bot' . $token . '/setWebhook" '
            . '-d "url=' . $webhookUrl . '" '
            . '-d "secret_token=' . $secret . '"';
    }

    private function sanitizeChatIds(string $value): string
    {
        $ids = preg_split('/[,;\s]+/', $value) ?: [];
        $ids = array_values(array_unique(array_filter(array_map(
            static fn(string $id): string => preg_match('/^-?\d+$/', trim($id)) ? trim($id) : '',
            $ids
        ))));
        return implode(',', $ids);
    }
}
