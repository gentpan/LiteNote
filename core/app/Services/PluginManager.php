<?php
declare(strict_types=1);

namespace App\Services;

final class PluginManager
{
    private const PLUGIN_ROOT = '/plugins';

    public static function all(): array
    {
        $root = self::pluginDirectory();
        if (!is_dir($root)) {
            @mkdir($root, 0775, true);
            return [];
        }

        $plugins = [];
        foreach (glob($root . '/*/plugin.json') ?: [] as $manifestFile) {
            $dir = dirname($manifestFile);
            $key = basename($dir);
            if (!preg_match('/^[a-z0-9_-]+$/', $key)) {
                continue;
            }
            $manifest = json_decode((string)file_get_contents($manifestFile), true);
            if (!is_array($manifest)) {
                $manifest = [];
            }
            $plugins[$key] = [
                'key' => $key,
                'name' => (string)($manifest['name'] ?? ucfirst($key)),
                'description' => (string)($manifest['description'] ?? ''),
                'version' => (string)($manifest['version'] ?? ''),
                'author' => (string)($manifest['author'] ?? ''),
                'screenshot' => self::screenshotUrl($key, $manifest),
            ];
        }
        ksort($plugins);
        return $plugins;
    }

    private static function screenshotUrl(string $key, array $manifest): string
    {
        $candidates = [];
        $declared = trim((string)($manifest['screenshot'] ?? ''));
        if ($declared !== '') {
            $candidates[] = ltrim($declared, '/');
        }
        foreach (['screenshot.png', 'screenshot.jpg', 'screenshot.jpeg', 'screenshot.webp', 'screenshot.gif', 'screenshot.svg'] as $name) {
            $candidates[] = $name;
        }

        $dir = self::pluginDirectory() . '/' . $key;
        foreach ($candidates as $candidate) {
            if ($candidate === '' || str_contains($candidate, '..')) {
                continue;
            }
            if (is_file($dir . '/' . $candidate)) {
                return self::PLUGIN_ROOT . '/' . rawurlencode($key) . '/' . str_replace('%2F', '/', rawurlencode($candidate));
            }
        }

        return '';
    }

    private static function pluginDirectory(): string
    {
        return self::basePath() . self::PLUGIN_ROOT;
    }

    private static function basePath(): string
    {
        return defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 3);
    }
}
