<?php
declare(strict_types=1);

namespace App\Services;

final class ThemeManager
{
    private const THEME_ROOT = '/themes';
    private const VIEW_THEME_DIR = '/themes';

    public static function all(): array
    {
        $themes = self::customThemes();

        return $themes;
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
        try {
            $key = (string)\App\Models\Setting::get('site_theme', 'ember');
        } catch (\Throwable) {
            $key = 'ember';
        }
        return isset(self::all()[$key]) ? $key : 'ember';
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
                'builtin' => false,
                'stylesheet' => self::THEME_ROOT . '/' . rawurlencode($key) . '/assets/main.css',
            ];
        }

        ksort($out);
        return $out;
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

    private static function templateFile(string $template): string
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
            'friend.subscribe' => 'pages/friend-subscribe.php',
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
