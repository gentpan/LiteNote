<?php
declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\Request;
use App\Core\Response;
use App\Services\TelegramTalkService;

final class TelegramController
{
    public function webhook(Request $request, array $params): never
    {
        $secret = trim((string)($params['secret'] ?? ''));
        $headerSecret = trim((string)($request->header('X-Telegram-Bot-Api-Secret-Token') ?? ''));
        $expected = trim((string)(getenv('TELEGRAM_WEBHOOK_SECRET') ?: ($_ENV['TELEGRAM_WEBHOOK_SECRET'] ?? $_SERVER['TELEGRAM_WEBHOOK_SECRET'] ?? '')));
        $matchesPath = $secret !== '' && hash_equals($expected, $secret);
        $matchesHeader = $headerSecret !== '' && hash_equals($expected, $headerSecret);
        if ($expected === '' || (!$matchesPath && !$matchesHeader)) {
            Response::json(['code' => 403, 'msg' => 'forbidden'], 403);
        }

        $payload = is_array($request->json) ? $request->json : json_decode($request->body, true);
        if (!is_array($payload)) {
            Response::json(['code' => 1, 'msg' => 'invalid payload'], 400);
        }

        $service = new TelegramTalkService();
        $result = $service->handleUpdate($payload);
        Response::json(['code' => 0, 'data' => $result]);
    }
}
