<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;

/**
 * 前台/后台静态资源发布路径：生产用 .min.css/.min.js，开发(APP_DEBUG=true)用源文件。
 */
final class PublishedAsset
{
    public static function useSourceAssets(): bool
    {
        return (bool)Config::get('app.debug', false);
    }

    public static function url(string $asset): string
    {
        $asset = '/' . ltrim($asset, '/');
        if (self::useSourceAssets()) {
            return $asset;
        }

        $min = self::minifiedPath($asset);
        return $min !== null ? $min : $asset;
    }

    public static function version(string $asset): int
    {
        $resolved = self::url($asset);
        $file = self::basePath() . $resolved;
        return is_file($file) ? (int)(filemtime($file) ?: time()) : time();
    }

    public static function isUncompressedBlocked(string $publicPath, string $absoluteFile): bool
    {
        if (self::useSourceAssets()) {
            return false;
        }

        if (!preg_match('/\.(css|js)$/i', $absoluteFile)) {
            return false;
        }
        if (preg_match('/\.min\.(css|js)$/i', $absoluteFile)) {
            return false;
        }

        $minPath = self::minifiedPath($publicPath);
        if ($minPath === null) {
            return false;
        }

        return is_file(self::basePath() . $minPath);
    }

    public static function minifiedPath(string $asset): ?string
    {
        $asset = '/' . ltrim($asset, '/');
        if (preg_match('/\.min\.(css|js)$/i', $asset)) {
            return $asset;
        }
        if (!preg_match('/\.(css|js)$/i', $asset)) {
            return null;
        }

        $min = preg_replace('/\.(css|js)$/i', '.min.$1', $asset);
        return is_file(self::basePath() . $min) ? $min : null;
    }

    private static function basePath(): string
    {
        return defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 3);
    }
}
