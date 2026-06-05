<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Markdown;

/**
 * Stores post bodies as Markdown files instead of database HTML.
 */
final class PostContentStorage
{
    private const DIR = 'runtime/storage/posts';

    public static function write(string $slug, string $markdown): void
    {
        $dir = self::dir();
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException("Cannot create post storage directory: {$dir}");
        }

        $path = self::path($slug);
        $tmp = $path . '.tmp';
        if (file_put_contents($tmp, self::normalize($markdown), LOCK_EX) === false) {
            throw new \RuntimeException("Cannot write post Markdown: {$path}");
        }
        if (!rename($tmp, $path)) {
            @unlink($tmp);
            throw new \RuntimeException("Cannot move post Markdown into place: {$path}");
        }
    }

    public static function read(string $slug): string
    {
        $path = self::path($slug);
        if (!is_file($path)) {
            return '';
        }
        return (string) file_get_contents($path);
    }

    public static function html(string $slug): string
    {
        $markdown = self::read($slug);
        return $markdown === '' ? '' : Markdown::parse($markdown);
    }

    public static function delete(string $slug): void
    {
        $path = self::path($slug);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    public static function rename(string $oldSlug, string $newSlug): void
    {
        if ($oldSlug === $newSlug) {
            return;
        }

        $old = self::path($oldSlug);
        if (!is_file($old)) {
            return;
        }

        $dir = self::dir();
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException("Cannot create post storage directory: {$dir}");
        }

        $new = self::path($newSlug);
        if (!rename($old, $new)) {
            throw new \RuntimeException("Cannot rename post Markdown: {$oldSlug} -> {$newSlug}");
        }
    }

    public static function path(string $slug): string
    {
        return self::dir() . '/' . self::filename($slug);
    }

    private static function dir(): string
    {
        return dirname(__DIR__, 3) . '/' . self::DIR;
    }

    private static function filename(string $slug): string
    {
        $slug = trim($slug);
        $slug = preg_replace('/[^\p{L}\p{N}_-]+/u', '-', $slug) ?? '';
        $slug = trim($slug, '-');
        if ($slug === '') {
            $slug = substr(md5(uniqid('', true)), 0, 8);
        }
        return $slug . '.md';
    }

    private static function normalize(string $markdown): string
    {
        $markdown = str_replace(["\r\n", "\r"], "\n", $markdown);
        return rtrim($markdown) . "\n";
    }
}
