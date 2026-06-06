<?php
declare(strict_types=1);

namespace App\Services;

/**
 * 主题 / 插件清单的共用工具。
 *
 * 抽取 ThemeManager 与 PluginManager 中几乎逐字相同的 screenshot 解析逻辑,
 * 二者仅物理目录与 URL 根不同。
 */
final class ExtensionManifest
{
    private const SCREENSHOT_NAMES = [
        'screenshot.png', 'screenshot.jpg', 'screenshot.jpeg',
        'screenshot.webp', 'screenshot.gif', 'screenshot.svg',
    ];

    /**
     * 解析扩展(主题/插件)的封面图对外 URL,找不到返回 ''。
     *
     * @param string $baseDir  扩展所在的物理根目录(不含 key),如 BASE_PATH.'/themes'
     * @param string $urlRoot  对外 URL 根,如 '/themes'
     * @param string $key      扩展目录名
     * @param array  $manifest 已解析的清单数组(theme.json / plugin.json)
     */
    public static function screenshotUrl(string $baseDir, string $urlRoot, string $key, array $manifest): string
    {
        $candidates = [];
        $declared = trim((string)($manifest['screenshot'] ?? ''));
        if ($declared !== '') {
            $candidates[] = ltrim($declared, '/');
        }
        foreach (self::SCREENSHOT_NAMES as $name) {
            $candidates[] = $name;
        }

        $dir = rtrim($baseDir, '/') . '/' . $key;
        foreach ($candidates as $candidate) {
            if ($candidate === '' || str_contains($candidate, '..')) {
                continue;
            }
            if (is_file($dir . '/' . $candidate)) {
                return rtrim($urlRoot, '/') . '/' . rawurlencode($key) . '/' . str_replace('%2F', '/', rawurlencode($candidate));
            }
        }

        return '';
    }
}
