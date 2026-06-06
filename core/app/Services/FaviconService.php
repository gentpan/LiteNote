<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Helper;
use App\Models\Setting;

final class FaviconService
{
    private const DIR = '/uploads/favicon';
    private const ADMIN_ICON = '/admin/assets/img/litenote-logo.svg';
    private const PNG_FILES = [
        16 => 'favicon-16x16.png',
        32 => 'favicon-32x32.png',
        48 => 'favicon-48x48.png',
        96 => 'favicon-96x96.png',
        150 => 'mstile-150x150.png',
        180 => 'apple-touch-icon.png',
        192 => 'android-chrome-192x192.png',
        512 => 'android-chrome-512x512.png',
    ];
    private const ICO_SIZES = [16, 32, 48];
    private const RASTER_MIMES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    public static function adminHeadHtml(): string
    {
        return '<link rel="icon" type="image/svg+xml" href="' . self::e(self::ADMIN_ICON) . '">' . "\n"
            . '    <link rel="shortcut icon" href="' . self::e(self::ADMIN_ICON) . '">' . "\n";
    }

    public static function headHtml(array $site = []): string
    {
        $version = self::version();
        $tags = [];
        if (is_file(self::abs('favicon.svg'))) {
            $tags[] = '<link rel="icon" type="image/svg+xml" href="' . self::e(self::url('favicon.svg', $version)) . '">';
        }

        if (is_file(self::abs('favicon.ico'))) {
            $tags[] = '<link rel="icon" href="' . self::e('/favicon.ico?v=' . $version) . '" sizes="any">';
            $tags[] = '<link rel="shortcut icon" href="' . self::e('/favicon.ico?v=' . $version) . '">';
        }

        foreach ([16, 32, 96] as $size) {
            $file = self::PNG_FILES[$size];
            if (is_file(self::abs($file))) {
                $tags[] = '<link rel="icon" type="image/png" sizes="' . $size . 'x' . $size . '" href="' . self::e(self::rootUrl($file, $version)) . '">';
            }
        }

        if (is_file(self::abs('apple-touch-icon.png'))) {
            $tags[] = '<link rel="apple-touch-icon" sizes="180x180" href="' . self::e('/apple-touch-icon.png?v=' . $version) . '">';
        }
        if (is_file(self::abs('site.webmanifest'))) {
            $tags[] = '<link rel="manifest" href="' . self::e('/site.webmanifest?v=' . $version) . '">';
        }
        if ($tags === []) {
            $tags[] = '<link rel="icon" type="image/svg+xml" href="' . self::e(self::ADMIN_ICON) . '">';
        }

        $tags[] = '<meta name="theme-color" content="#0052d9">';
        return implode("\n    ", $tags);
    }

    public static function status(): array
    {
        $version = self::version();
        $assets = [];
        $labels = [
            'favicon.ico' => 'ICO 多尺寸',
            'favicon.svg' => 'SVG',
            'favicon-16x16.png' => 'PNG 16',
            'favicon-32x32.png' => 'PNG 32',
            'favicon-48x48.png' => 'PNG 48',
            'favicon-96x96.png' => 'PNG 96',
            'mstile-150x150.png' => 'Windows Tile 150',
            'apple-touch-icon.png' => 'Apple 180',
            'android-chrome-192x192.png' => 'Android 192',
            'android-chrome-512x512.png' => 'Android 512',
            'site.webmanifest' => 'Web Manifest',
        ];
        foreach ($labels as $file => $label) {
            $path = self::abs($file);
            $assets[] = [
                'file' => $file,
                'label' => $label,
                'exists' => is_file($path),
                'url' => is_file($path) ? self::rootUrl($file, $version) : '',
                'size' => is_file($path) ? (int)filesize($path) : 0,
            ];
        }

        $preview = self::ADMIN_ICON;
        foreach (['apple-touch-icon.png', 'android-chrome-192x192.png', 'favicon.svg'] as $file) {
            if (is_file(self::abs($file))) {
                $preview = self::url($file, $version);
                break;
            }
        }

        return [
            'version' => $version,
            'mode' => (string)Setting::get('site_favicon_mode', ''),
            'updated_at' => (string)Setting::get('site_favicon_updated_at', ''),
            'source' => (string)Setting::get('site_favicon_source', ''),
            'error' => (string)Setting::get('site_favicon_error', ''),
            'preview' => $preview,
            'assets' => $assets,
        ];
    }

    public static function upload(array $file): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('上传错误: ' . (string)($file['error'] ?? 'unknown'));
        }

        $maxSize = (int)Config::get('upload.max_size', 5 * 1024 * 1024);
        if ((int)($file['size'] ?? 0) > $maxSize) {
            throw new \RuntimeException('图片太大，最大允许 ' . Helper::bytesToHuman($maxSize));
        }

        $tmp = (string)($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_file($tmp)) {
            throw new \RuntimeException('上传临时文件不存在');
        }

        self::ensureDir();
        $mime = self::detectMime($tmp);

        if (self::isSvg($mime, $file, $tmp)) {
            self::saveSvg($tmp);
            $version = (string)time();
            self::writeManifest((string)Config::get('site.title', 'LiteNote'), true);
            Setting::set('site_favicon_mode', 'svg');
            Setting::set('site_favicon_source', (string)($file['name'] ?? 'favicon.svg'));
            Setting::set('site_favicon_error', 'SVG 已保存；当前服务器没有 SVG 栅格化组件，未生成 PNG/ICO 全套尺寸。');
            Setting::set('site_favicon_updated_at', date('Y-m-d H:i:s'));
            Setting::set('site_favicon_version', $version);
            return self::status();
        }

        if (!in_array($mime, self::RASTER_MIMES, true)) {
            throw new \RuntimeException('只允许上传 JPG、PNG、GIF、WebP 或 SVG 图片');
        }

        $source = self::loadRaster($tmp, $mime);
        self::clearGenerated();
        foreach (self::PNG_FILES as $size => $filename) {
            self::writePng(self::resizeSquare($source, $size), self::abs($filename));
        }
        self::writeIco();
        imagedestroy($source);

        $version = (string)time();
        self::writeManifest((string)Config::get('site.title', 'LiteNote'), false);
        Setting::set('site_favicon_mode', 'raster');
        Setting::set('site_favicon_source', (string)($file['name'] ?? 'favicon'));
        Setting::set('site_favicon_error', '');
        Setting::set('site_favicon_updated_at', date('Y-m-d H:i:s'));
        Setting::set('site_favicon_version', $version);

        return self::status();
    }

    private static function writeManifest(string $siteTitle, bool $svgOnly): void
    {
        $title = trim($siteTitle) !== '' ? trim($siteTitle) : 'LiteNote';
        $icons = $svgOnly ? [
            ['src' => self::DIR . '/favicon.svg', 'sizes' => 'any', 'type' => 'image/svg+xml'],
        ] : [
            ['src' => '/android-chrome-192x192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any maskable'],
            ['src' => '/android-chrome-512x512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable'],
        ];

        $json = [
            'name' => $title,
            'short_name' => mb_strlen($title) > 12 ? mb_substr($title, 0, 12) : $title,
            'icons' => $icons,
            'theme_color' => '#0052d9',
            'background_color' => '#ffffff',
            'display' => 'standalone',
        ];
        file_put_contents(self::abs('site.webmanifest'), json_encode($json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    }

    private static function writeIco(): void
    {
        $images = [];
        foreach (self::ICO_SIZES as $size) {
            $data = (string)file_get_contents(self::abs(self::PNG_FILES[$size]));
            $images[] = ['size' => $size, 'data' => $data];
        }

        $count = count($images);
        $header = pack('vvv', 0, 1, $count);
        $entries = '';
        $payload = '';
        $offset = 6 + (16 * $count);
        foreach ($images as $image) {
            $length = strlen($image['data']);
            $entries .= pack('CCCCvvVV', $image['size'], $image['size'], 0, 0, 1, 32, $length, $offset);
            $payload .= $image['data'];
            $offset += $length;
        }
        file_put_contents(self::abs('favicon.ico'), $header . $entries . $payload);
    }

    private static function saveSvg(string $tmp): void
    {
        $svg = (string)file_get_contents($tmp);
        if (!preg_match('/<svg[\s>]/i', $svg)) {
            throw new \RuntimeException('SVG 文件内容不合法');
        }
        if (preg_match('/<script|on[a-z]+\s*=|javascript:/i', $svg)) {
            throw new \RuntimeException('SVG 含有脚本或事件属性，已拒绝保存');
        }
        self::clearGenerated();
        file_put_contents(self::abs('favicon.svg'), $svg);
    }

    private static function loadRaster(string $tmp, string $mime): \GdImage
    {
        $image = match ($mime) {
            'image/jpeg' => imagecreatefromjpeg($tmp),
            'image/png' => imagecreatefrompng($tmp),
            'image/gif' => imagecreatefromgif($tmp),
            'image/webp' => imagecreatefromwebp($tmp),
            default => false,
        };
        if (!$image instanceof \GdImage) {
            throw new \RuntimeException('图片读取失败');
        }
        if (function_exists('imagepalettetotruecolor')) {
            imagepalettetotruecolor($image);
        }
        imagealphablending($image, true);
        imagesavealpha($image, true);
        return $image;
    }

    private static function resizeSquare(\GdImage $source, int $size): \GdImage
    {
        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $side = min($sourceWidth, $sourceHeight);
        $srcX = (int)(($sourceWidth - $side) / 2);
        $srcY = (int)(($sourceHeight - $side) / 2);

        $target = imagecreatetruecolor($size, $size);
        imagealphablending($target, false);
        imagesavealpha($target, true);
        $transparent = imagecolorallocatealpha($target, 0, 0, 0, 127);
        imagefilledrectangle($target, 0, 0, $size, $size, $transparent);
        imagecopyresampled($target, $source, 0, 0, $srcX, $srcY, $size, $size, $side, $side);
        return $target;
    }

    private static function writePng(\GdImage $image, string $path): void
    {
        imagealphablending($image, false);
        imagesavealpha($image, true);
        if (!imagepng($image, $path, 6)) {
            imagedestroy($image);
            throw new \RuntimeException('PNG 图标生成失败');
        }
        imagedestroy($image);
    }

    private static function detectMime(string $path): string
    {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $mime = finfo_file($finfo, $path);
                finfo_close($finfo);
                if (is_string($mime) && $mime !== '') {
                    return $mime;
                }
            }
        }
        return mime_content_type($path) ?: '';
    }

    private static function isSvg(string $mime, array $file, string $tmp): bool
    {
        $ext = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
        if ($mime === 'image/svg+xml' || $ext === 'svg') {
            return true;
        }
        $head = (string)file_get_contents($tmp, false, null, 0, 512);
        return str_contains(strtolower($head), '<svg');
    }

    private static function clearGenerated(): void
    {
        foreach (array_merge(['favicon.ico', 'favicon.svg', 'site.webmanifest'], array_values(self::PNG_FILES)) as $file) {
            $path = self::abs($file);
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    private static function ensureDir(): void
    {
        $dir = self::dir();
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Favicon 目录创建失败');
        }
    }

    private static function dir(): string
    {
        return self::basePath() . self::DIR;
    }

    private static function abs(string $file): string
    {
        return self::dir() . '/' . ltrim($file, '/');
    }

    private static function rootUrl(string $file, string $version): string
    {
        return match ($file) {
            'favicon.ico' => '/favicon.ico?v=' . $version,
            'site.webmanifest' => '/site.webmanifest?v=' . $version,
            'apple-touch-icon.png' => '/apple-touch-icon.png?v=' . $version,
            'android-chrome-192x192.png' => '/android-chrome-192x192.png?v=' . $version,
            'android-chrome-512x512.png' => '/android-chrome-512x512.png?v=' . $version,
            'favicon-16x16.png', 'favicon-32x32.png', 'favicon-48x48.png', 'favicon-96x96.png' => '/' . $file . '?v=' . $version,
            default => self::url($file, $version),
        };
    }

    private static function url(string $file, string $version): string
    {
        return self::DIR . '/' . ltrim($file, '/') . '?v=' . $version;
    }

    private static function version(): string
    {
        $version = (string)Setting::get('site_favicon_version', '');
        if ($version !== '') {
            return $version;
        }
        foreach (['favicon.ico', 'favicon.svg', 'apple-touch-icon.png'] as $file) {
            $path = self::abs($file);
            if (is_file($path)) {
                return (string)((int)filemtime($path));
            }
        }
        return '1';
    }

    private static function basePath(): string
    {
        return defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 3);
    }

    private static function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
