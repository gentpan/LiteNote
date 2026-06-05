<?php
declare(strict_types=1);

namespace App\Core;

final class ApiResponse
{
    public static function ok(mixed $data = null, array $meta = [], int $status = 200): never
    {
        self::send([
            'ok' => true,
            'data' => $data,
            'meta' => $meta,
        ], $status);
    }

    public static function error(string $message, int $status = 400, string $code = 'bad_request', array $meta = []): never
    {
        self::send([
            'ok' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
            'meta' => $meta,
        ], $status);
    }

    private static function send(array $payload, int $status): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
