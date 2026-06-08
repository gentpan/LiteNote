<?php
declare(strict_types=1);

namespace App\Services;

final class ThemeManager
{
    private const THEME_ROOT = '/themes';
    private const VIEW_THEME_DIR = '/themes';
    private const PROTECTED_THEMES = ['ember'];

    public static function all(): array
    {
        return self::customThemes();
    }

    public static function keys(): array
    {
        return array_keys(self::all());
    }

    public static function stylesheets(): array
    {
        $active = self::active();
        $stylesheet = (string)($active['stylesheet'] ?? '');
        return $stylesheet !== '' ? [$stylesheet] : [];
    }

    public static function assetVersion(string $asset): int
    {
        $asset = '/' . ltrim($asset, '/');
        if (!str_starts_with($asset, self::THEME_ROOT . '/')) {
            return time();
        }

        $file = self::themeDirectory() . substr($asset, strlen(self::THEME_ROOT));
        return is_file($file) ? (int)(filemtime($file) ?: time()) : time();
    }

    public static function viewPath(string $template): ?string
    {
        $key = self::activeKey();
        if (!preg_match('/^[a-z0-9_-]+$/', $key)) {
            return null;
        }

        $path = self::viewThemeDirectory() . '/' . $key . '/' . self::templateFile($template);
        if (is_file($path)) {
            return $path;
        }

        return null;
    }

    public static function loadFunctions(): void
    {
        static $loaded = [];

        $key = self::activeKey();
        if (isset($loaded[$key]) || !preg_match('/^[a-z0-9_-]+$/', $key)) {
            return;
        }

        $file = self::themeDirectory() . '/' . $key . '/functions.php';
        if (is_file($file)) {
            require_once $file;
        }
        $loaded[$key] = true;
    }

    public static function active(): array
    {
        $themes = self::all();
        $key = self::activeKey();
        return $themes[$key] ?? ($themes['ember'] ?? [
            'key' => 'ember',
            'name' => 'Ember',
            'description' => '',
            'builtin' => false,
            'stylesheet' => self::THEME_ROOT . '/ember/assets/main.css',
        ]);
    }

    public static function activeKey(): string
    {
        $key = self::activeKeyRaw();
        return isset(self::all()[$key]) ? $key : 'ember';
    }

    public static function activate(string $key): void
    {
        if (!preg_match('/^[a-z0-9_-]+$/', $key) || !isset(self::all()[$key])) {
            throw new \RuntimeException('主题不存在');
        }
        \App\Models\Setting::set('site_theme', $key);
        \App\Core\Config::set('site.site_theme', $key);
        \App\Core\View::share('site_theme', $key);
        \App\Core\View::share('site', \App\Core\Config::get('site'));
    }

    public static function delete(string $key): void
    {
        if (!preg_match('/^[a-z0-9_-]+$/', $key)) {
            throw new \RuntimeException('主题标识不合法');
        }
        if ($key === self::activeKey()) {
            throw new \RuntimeException('当前启用的主题不能删除');
        }
        if (in_array($key, self::PROTECTED_THEMES, true)) {
            throw new \RuntimeException('默认主题不能删除');
        }

        $theme = self::all()[$key] ?? null;
        if (!$theme) {
            throw new \RuntimeException('主题不存在');
        }

        $root = realpath(self::themeDirectory());
        $dir = self::themeDirectory() . '/' . $key;
        $realDir = realpath($dir);
        if (!is_string($root) || !is_string($realDir) || !is_dir($dir) || !str_starts_with($realDir, $root . DIRECTORY_SEPARATOR)) {
            throw new \RuntimeException('主题目录不存在');
        }

        self::deleteDirectory($realDir);
    }

    private static function customThemes(): array
    {
        $root = self::themeDirectory();
        if (!is_dir($root)) {
            return [];
        }

        $out = [];
        foreach (glob($root . '/*/theme.json') ?: [] as $manifestFile) {
            $dir = dirname($manifestFile);
            $key = basename($dir);
            if (!preg_match('/^[a-z0-9_-]+$/', $key)) {
                continue;
            }

            $manifest = json_decode((string)file_get_contents($manifestFile), true);
            if (!is_array($manifest)) {
                $manifest = [];
            }
            $cssFile = self::themeDirectory() . '/' . $key . '/assets/main.css';
            if (!is_file($cssFile)) {
                continue;
            }

            $out[$key] = [
                'key' => $key,
                'name' => (string)($manifest['name'] ?? ucfirst($key)),
                'description' => (string)($manifest['description'] ?? ''),
                'version' => (string)($manifest['version'] ?? ''),
                'author' => (string)($manifest['author'] ?? ''),
                'builtin' => false,
                'protected' => in_array($key, self::PROTECTED_THEMES, true),
                'active' => $key === self::activeKeyRaw(),
                'screenshot' => ExtensionManifest::screenshotUrl(self::themeDirectory(), self::THEME_ROOT, $key, $manifest),
                'stylesheet' => self::THEME_ROOT . '/' . rawurlencode($key) . '/assets/main.css',
            ];
        }

        ksort($out);
        return $out;
    }

    private static function activeKeyRaw(): string
    {
        try {
            $key = (string)\App\Models\Setting::get('site_theme', '');
            if ($key !== '') {
                return $key;
            }
            return (string)\App\Models\Setting::get('theme', 'ember');
        } catch (\Throwable) {
            return 'ember';
        }
    }

    private static function deleteDirectory(string $dir): void
    {
        $items = scandir($dir);
        if ($items === false) {
            throw new \RuntimeException('无法读取主题目录');
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_dir($path) && !is_link($path)) {
                self::deleteDirectory($path);
            } else {
                if (!@unlink($path)) {
                    throw new \RuntimeException('无法删除主题文件: ' . $item);
                }
            }
        }
        if (!@rmdir($dir)) {
            throw new \RuntimeException('无法删除主题目录');
        }
    }

    private static function viewThemeDirectory(): string
    {
        return self::themeDirectory();
    }

    private static function themeDirectory(): string
    {
        return self::basePath() . self::VIEW_THEME_DIR;
    }

    private static function basePath(): string
    {
        return defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 3);
    }

    public static function templateFile(string $template): string
    {
        if ($template === 'layouts.front') {
            return 'layout.php';
        }
        if ($template === 'partials.header') {
            return 'header.php';
        }
        if ($template === 'partials.footer') {
            return 'footer.php';
        }
        if (str_starts_with($template, 'partials.')) {
            return 'inc/' . substr($template, strlen('partials.')) . '.php';
        }

        $map = [
            'home.index' => 'index.php',
            'post.show' => 'single.php',
            'page.show' => 'page.php',
            'archive.index' => 'pages/archive.php',
            'category.show' => 'pages/category.php',
            'friend.index' => 'pages/friend.php',
            'home.posts' => 'pages/home-posts.php',
            'home.readers' => 'pages/home-readers.php',
            'search.index' => 'pages/search.php',
            'front.activity.index' => 'pages/activity.php',
            'front.music.index' => 'pages/music.php',
            'front.talk.index' => 'pages/talk.php',
            'front.x.index' => 'pages/x.php',
        ];
        if (isset($map[$template])) {
            return $map[$template];
        }

        $relative = str_replace('.', '/', $template);
        if (str_starts_with($relative, 'front/')) {
            $relative = substr($relative, strlen('front/'));
        }

        $parts = array_values(array_filter(explode('/', $relative), static fn(string $part): bool => $part !== ''));
        if (end($parts) === 'index' || end($parts) === 'show') {
            array_pop($parts);
        }

        return 'pages/' . implode('-', $parts) . '.php';
    }
}
