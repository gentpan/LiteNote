<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;

final class MusicAssetCacheService
{
    private const IMAGE_MIMES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    private int $timeout;

    public function __construct(?int $timeout = null)
    {
        $this->timeout = max(3, min(30, $timeout ?? (int)Config::get('meting.timeout', 12)));
    }

    public function cacheRemoteCover(string $url, string $source, string $sourceId): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }
        if (str_starts_with($url, '/uploads/')) {
            return $url;
        }
        if (!preg_match('~^https?://~i', $url)) {
            return null;
        }

        $body = $this->download($url, 12 * 1024 * 1024, [
            'Accept: image/avif,image/webp,image/apng,image/*,*/*;q=0.8',
            'User-Agent: LiteNote/1.0 MusicAssetCache',
        ]);
        $mime = $this->detectImageMime($body);
        if (!isset(self::IMAGE_MIMES[$mime])) {
            return null;
        }

        $ext = self::IMAGE_MIMES[$mime];
        $filename = $this->filename($source, $sourceId, $url) . '.' . $ext;
        $dir = $this->uploadPath('music/covers');
        $path = $dir . '/' . $filename;
        if (!is_file($path) && file_put_contents($path, $body, LOCK_EX) === false) {
            throw new \RuntimeException('封面保存失败');
        }

        return $this->uploadUrl('music/covers/' . $filename);
    }

    public function cacheLyricsText(string $lyrics, string $source, string $sourceId): ?string
    {
        $lyrics = trim(str_replace(["\r\n", "\r"], "\n", $lyrics));
        if ($lyrics === '') {
            return null;
        }

        $filename = $this->filename($source, $sourceId, $lyrics) . '.lrc';
        $dir = $this->uploadPath('music/lyrics');
        $path = $dir . '/' . $filename;
        if (!is_file($path) && file_put_contents($path, $lyrics . "\n", LOCK_EX) === false) {
            throw new \RuntimeException('歌词保存失败');
        }

        return $this->uploadUrl('music/lyrics/' . $filename);
    }

    private function download(string $url, int $maxBytes, array $headers): string
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", $headers),
                'timeout' => $this->timeout,
                'ignore_errors' => true,
            ],
        ]);

        $body = @file_get_contents($url, false, $context);
        $status = 0;
        foreach (($http_response_header ?? []) as $header) {
            if (preg_match('~^HTTP/\S+\s+(\d+)~', $header, $match)) {
                $status = (int)$match[1];
            }
        }

        if ($body === false || $status >= 400 || $body === '') {
            throw new \RuntimeException('远程资源下载失败');
        }
        if (strlen($body) > $maxBytes) {
            throw new \RuntimeException('远程资源太大');
        }

        return (string)$body;
    }

    private function detectImageMime(string $body): string
    {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $mime = finfo_buffer($finfo, $body);
                if (is_string($mime) && isset(self::IMAGE_MIMES[$mime])) {
                    return $mime;
                }
            }
        }

        return match (true) {
            str_starts_with($body, "\xFF\xD8\xFF") => 'image/jpeg',
            str_starts_with($body, "\x89PNG\r\n\x1A\n") => 'image/png',
            str_starts_with($body, 'GIF87a') || str_starts_with($body, 'GIF89a') => 'image/gif',
            substr($body, 0, 4) === 'RIFF' && substr($body, 8, 4) === 'WEBP' => 'image/webp',
            default => '',
        };
    }

    private function uploadPath(string $subPath): string
    {
        $base = rtrim((string)Config::get('upload.path', $this->basePath() . '/uploads'), '/');
        $path = $base . '/' . trim($subPath, '/');
        if (!is_dir($path) && !mkdir($path, 0775, true) && !is_dir($path)) {
            throw new \RuntimeException('上传目录创建失败');
        }
        return $path;
    }

    private function uploadUrl(string $subPath): string
    {
        return rtrim((string)Config::get('upload.url', '/uploads'), '/') . '/' . ltrim($subPath, '/');
    }

    private function filename(string $source, string $sourceId, string $fingerprint): string
    {
        $base = strtolower(trim($source . '-' . $sourceId, '-'));
        $base = preg_replace('/[^a-z0-9_-]+/', '-', $base) ?: 'music';
        return trim($base, '-') . '-' . substr(sha1($fingerprint), 0, 12);
    }

    private function basePath(): string
    {
        return defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 3);
    }
}
