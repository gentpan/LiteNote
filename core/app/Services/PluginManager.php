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
                'screenshot' => ExtensionManifest::screenshotUrl(self::pluginDirectory(), self::PLUGIN_ROOT, $key, $manifest),
            ];
        }
        ksort($plugins);
        return $plugins;
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
