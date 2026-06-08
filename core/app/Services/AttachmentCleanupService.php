<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Models\Attachment;

final class AttachmentCleanupService
{
    /**
     * @param array<int,string|null> $values
     */
    public static function deleteUnusedFromValues(array $values): void
    {
        $urls = [];
        foreach ($values as $value) {
            $urls = array_merge($urls, self::extractLocalUploadUrls((string)$value));
        }
        self::deleteUnusedUrls($urls);
    }

    /**
     * @param array<int,string> $urls
     */
    public static function deleteUnusedUrls(array $urls): void
    {
        foreach (array_values(array_unique(array_filter(array_map([self::class, 'normalizeUploadUrl'], $urls)))) as $url) {
            try {
                if ($url === '' || self::isReferenced($url)) {
                    continue;
                }

                $attachments = Attachment::db()->fetchAll('SELECT * FROM attachments WHERE fileurl = ?', [$url]);
                foreach ($attachments as $row) {
                    self::deleteFile((string)($row['filepath'] ?? ''));
                    Attachment::db()->delete('attachments', 'id = ?', [(int)$row['id']]);
                }

                if ($attachments === []) {
                    self::deleteFile(self::pathFromUploadUrl($url));
                }
            } catch (\Throwable) {
                continue;
            }
        }
    }

    /**
     * @return array<int,string>
     */
    public static function extractLocalUploadUrls(string $text): array
    {
        $text = trim($text);
        if ($text === '') {
            return [];
        }

        $urls = [];
        if (preg_match_all('~(?:"|\')?((?:https?://[^\\s"\'<>)]+|/uploads/[^\\s"\'<>)]+))~i', $text, $matches)) {
            foreach ($matches[1] as $match) {
                $url = self::normalizeUploadUrl($match);
                if ($url !== '') {
                    $urls[] = $url;
                }
            }
        }

        foreach (preg_split('/[,\\s]+/', $text) ?: [] as $part) {
            $url = self::normalizeUploadUrl($part);
            if ($url !== '') {
                $urls[] = $url;
            }
        }

        return array_values(array_unique($urls));
    }

    private static function normalizeUploadUrl(?string $value): string
    {
        $value = trim((string)$value, " \t\n\r\0\x0B\"'()[]{}<>.,，。");
        if ($value === '') {
            return '';
        }

        $uploadUrl = rtrim((string)Config::get('upload.url', '/uploads'), '/');
        if ($uploadUrl === '') {
            return '';
        }

        $path = $value;
        if (preg_match('~^https?://~i', $value)) {
            $path = (string)(parse_url($value, PHP_URL_PATH) ?? '');
        }

        if ($path === $uploadUrl || !str_starts_with($path, $uploadUrl . '/')) {
            return '';
        }

        return $path;
    }

    private static function pathFromUploadUrl(string $url): string
    {
        $uploadUrl = rtrim((string)Config::get('upload.url', '/uploads'), '/');
        $uploadDir = rtrim((string)Config::get('upload.path', ''), '/');
        if ($uploadUrl === '' || $uploadDir === '' || !str_starts_with($url, $uploadUrl . '/')) {
            return '';
        }

        return $uploadDir . '/' . ltrim(substr($url, strlen($uploadUrl)), '/');
    }

    private static function deleteFile(string $path): void
    {
        $path = trim($path);
        if ($path === '') {
            return;
        }

        $uploadDir = realpath((string)Config::get('upload.path', ''));
        $realPath = realpath($path);
        if (!$uploadDir || !$realPath || !str_starts_with($realPath, rtrim($uploadDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)) {
            return;
        }

        if (is_file($realPath)) {
            @unlink($realPath);
        }
    }

    private static function isReferenced(string $url): bool
    {
        $db = Attachment::db();
        $like = '%' . $url . '%';
        $checks = [
            ['posts', ['cover', 'content', 'markdown_content']],
            ['talk', ['images', 'content', 'music_cover', 'music']],
            ['music', ['audio_url', 'cover_url', 'lyrics_url', 'lyrics', 'description']],
            ['x_tweets', ['images', 'content', 'tweet_author_avatar', 'tweet_data']],
            ['pages', ['content']],
        ];

        foreach ($checks as [$table, $columns]) {
            foreach ($columns as $column) {
                try {
                    $count = (int)$db->fetchColumn("SELECT COUNT(*) FROM {$table} WHERE {$column} = ? OR {$column} LIKE ?", [$url, $like]);
                } catch (\Throwable) {
                    continue;
                }
                if ($count > 0) {
                    return true;
                }
            }
        }

        return false;
    }
}
