<?php
declare(strict_types=1);

namespace App\Core;

final class StaticAssetServer
{
    private const ALLOWED_ROOTS = ['admin', 'themes', 'plugins', 'uploads'];
    private const ALLOWED_EXTENSIONS = ['css', 'js', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'ico', 'xml', 'json', 'webmanifest', 'txt', 'lrc', 'woff', 'woff2', 'ttf', 'otf', 'map'];
    private const MIME_MAP = [
        'css' => 'text/css; charset=utf-8',
        'js' => 'application/javascript; charset=utf-8',
        'svg' => 'image/svg+xml',
        'ico' => 'image/x-icon',
        'xml' => 'application/xml; charset=utf-8',
        'json' => 'application/json; charset=utf-8',
        'webmanifest' => 'application/manifest+json; charset=utf-8',
        'txt' => 'text/plain; charset=utf-8',
        'lrc' => 'text/plain; charset=utf-8',
        'map' => 'application/json; charset=utf-8',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'otf' => 'font/otf',
    ];

    public static function normalizePath(string $requestPath): string
    {
        $path = '/' . ltrim(rawurldecode($requestPath), '/');
        $rootFavicons = [
            '/favicon.ico' => '/uploads/favicon/favicon.ico',
            '/favicon.svg' => '/uploads/favicon/favicon.svg',
            '/favicon-16x16.png' => '/uploads/favicon/favicon-16x16.png',
            '/favicon-32x32.png' => '/uploads/favicon/favicon-32x32.png',
            '/favicon-48x48.png' => '/uploads/favicon/favicon-48x48.png',
            '/favicon-96x96.png' => '/uploads/favicon/favicon-96x96.png',
            '/apple-touch-icon.png' => '/uploads/favicon/apple-touch-icon.png',
            '/android-chrome-192x192.png' => '/uploads/favicon/android-chrome-192x192.png',
            '/android-chrome-512x512.png' => '/uploads/favicon/android-chrome-512x512.png',
            '/mstile-150x150.png' => '/uploads/favicon/mstile-150x150.png',
            '/site.webmanifest' => '/uploads/favicon/site.webmanifest',
        ];
        if (isset($rootFavicons[$path])) {
            return $rootFavicons[$path];
        }
        if (str_starts_with($path, '/assets/uploads/')) {
            return '/uploads/' . substr($path, strlen('/assets/uploads/'));
        }
        return $path;
    }

    public static function isLegacyUploadPath(string $requestPath): bool
    {
        $path = '/' . ltrim(rawurldecode($requestPath), '/');
        return str_starts_with($path, '/assets/uploads/') || str_starts_with($path, '/uploads/');
    }

    public static function isAllowedPath(string $path): bool
    {
        $firstSegment = strtok(ltrim($path, '/'), '/');
        return in_array($firstSegment, self::ALLOWED_ROOTS, true);
    }

    public static function resolveFile(string $basePath, string $requestPath): ?string
    {
        $path = self::normalizePath($requestPath);
        if (!self::isAllowedPath($path)) {
            return null;
        }

        $base = realpath($basePath);
        $file = $base ? realpath($base . $path) : false;
        if (!is_string($base) || !is_string($file)) {
            return null;
        }
        if (!str_starts_with($file, $base . DIRECTORY_SEPARATOR) || !is_file($file)) {
            return null;
        }

        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        return in_array($ext, self::ALLOWED_EXTENSIONS, true) ? $file : null;
    }

    public static function serve(string $basePath, string $requestPath): bool
    {
        $file = self::resolveFile($basePath, $requestPath);
        if ($file === null) {
            return false;
        }

        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $mime = self::MIME_MAP[$ext] ?? (mime_content_type($file) ?: 'application/octet-stream');
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . (string) filesize($file));
        header('Cache-Control: public, max-age=31536000');
        readfile($file);
        return true;
    }
}
