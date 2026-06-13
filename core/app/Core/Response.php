<?php
declare(strict_types=1);

namespace App\Core;

/**
 * HTTP 响应
 */
final class Response
{
    public static function json(mixed $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function text(string $body, int $status = 200, string $contentType = 'text/plain; charset=utf-8'): never
    {
        http_response_code($status);
        header("Content-Type: {$contentType}");
        echo $body;
        exit;
    }

    public static function html(string $body, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        echo $body;
        exit;
    }

    public static function redirect(string $url, int $status = 302): never
    {
        http_response_code($status);
        header('Location: ' . $url);
        exit;
    }

    public static function xml(string $body, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/xml; charset=utf-8');
        echo $body;
        exit;
    }

    public static function status(int $code): never
    {
        http_response_code($code);
        exit;
    }

    public static function notFound(string $message = '404 Not Found'): never
    {
        http_response_code(404);
        header('Content-Type: text/html; charset=utf-8');
        echo '<h1>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</h1>';
        exit;
    }
}
