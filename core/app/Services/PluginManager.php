<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;
use App\Services\Plugins\PluginContext;
use App\Services\Plugins\PluginInterface;
use App\Services\Plugins\Registry;

/**
 * 插件管理器。
 *
 * 职责:
 *  - all()        :扫描 plugins/ 下的 plugin.json,供后台列表展示(附带启用状态);
 *  - enable/disable:写入启用状态(存 Setting `plugins_enabled`),并跑 migrate()/uninstall();
 *  - boot()       :每次请求启动时由 bootstrap 调用,注册插件 autoloader 并加载已启用插件,
 *                   让它们通过 register(PluginContext) 把扩展点登记进 Registry。
 */
final class PluginManager
{
    private const PLUGIN_ROOT = '/plugins';
    private const ENABLED_KEY = 'plugins_enabled';

    private static bool $autoloaderRegistered = false;
    private static bool $booted = false;

    /**
     * 扫描所有插件包(含启用状态)。
     *
     * @return array<string,array<string,mixed>>
     */
    public static function all(): array
    {
        $root = self::pluginDirectory();
        if (!is_dir($root)) {
            @mkdir($root, 0775, true);
            return [];
        }

        $enabledSet = array_fill_keys(self::enabled(), true);

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
                'enabled' => isset($enabledSet[$key]),
                'screenshot' => ExtensionManifest::screenshotUrl(self::pluginDirectory(), self::PLUGIN_ROOT, $key, $manifest),
            ];
        }
        ksort($plugins);
        return $plugins;
    }

    /**
     * 已启用插件的 key 列表(去重 + 合法性过滤)。
     *
     * @return array<int,string>
     */
    public static function enabled(): array
    {
        try {
            $raw = Setting::get(self::ENABLED_KEY, '[]');
        } catch (\Throwable) {
            return [];
        }
        $list = is_array($raw) ? $raw : json_decode((string)$raw, true);
        if (!is_array($list)) {
            return [];
        }
        $out = [];
        foreach ($list as $key) {
            $key = (string)$key;
            if (preg_match('/^[a-z0-9_-]+$/', $key)) {
                $out[$key] = true;
            }
        }
        return array_keys($out);
    }

    public static function isEnabled(string $key): bool
    {
        return in_array($key, self::enabled(), true);
    }

    /**
     * 启用插件:跑迁移(建表/数据迁移),再写入启用状态。
     */
    public static function enable(string $key): void
    {
        $key = self::normalizeKey($key);
        if (!self::exists($key)) {
            throw new \RuntimeException('插件不存在：' . $key);
        }
        self::ensureAutoloader();
        $plugin = self::loadPlugin($key);
        if ($plugin) {
            $plugin->migrate();
        }
        $list = self::enabled();
        if (!in_array($key, $list, true)) {
            $list[] = $key;
            Setting::set(self::ENABLED_KEY, array_values($list));
        }
    }

    /**
     * 禁用插件:清理持久副作用(uninstall),再移出启用状态。
     */
    public static function disable(string $key): void
    {
        $key = self::normalizeKey($key);
        $list = self::enabled();
        if (!in_array($key, $list, true)) {
            return;
        }
        self::ensureAutoloader();
        $plugin = self::loadPlugin($key);
        if ($plugin) {
            $plugin->uninstall();
        }
        $list = array_values(array_filter($list, static fn(string $k): bool => $k !== $key));
        Setting::set(self::ENABLED_KEY, $list);
    }

    /**
     * 加载所有已启用插件并触发其 register()。每次请求只执行一次。
     */
    public static function boot(): void
    {
        if (self::$booted) {
            return;
        }
        self::$booted = true;
        self::ensureAutoloader();
        foreach (self::enabled() as $key) {
            if (!self::exists($key)) {
                continue;
            }
            $plugin = self::loadPlugin($key);
            if ($plugin) {
                $plugin->register(new PluginContext($key, self::pluginPath($key)));
            }
        }
    }

    /**
     * 注册一个通用插件 autoloader(只注册一次)。它按需读取 Registry 中登记的
     * PSR-4 前缀映射 —— 这些前缀在 loadPlugin() 里、require 入口文件之前就已登记,
     * 因此插件 register()/migrate() 内部引用的类都能被解析。与 `App\` 前缀互不重叠。
     */
    private static function ensureAutoloader(): void
    {
        if (self::$autoloaderRegistered) {
            return;
        }
        self::$autoloaderRegistered = true;
        spl_autoload_register(static function (string $class): void {
            foreach (Registry::psr4Map() as $prefix => $baseDir) {
                if (str_starts_with($class, $prefix)) {
                    $relative = substr($class, strlen($prefix));
                    $file = $baseDir . '/' . str_replace('\\', '/', $relative) . '.php';
                    if (is_file($file)) {
                        require $file;
                    }
                    return;
                }
            }
        });
    }

    /**
     * 登记该插件的 PSR-4 前缀、require 其入口文件,并返回入口类实例。
     */
    private static function loadPlugin(string $key): ?PluginInterface
    {
        $dir = self::pluginPath($key);
        $entry = $dir . '/Plugin.php';
        if (!is_file($entry)) {
            return null;
        }
        Registry::addPsr4(self::namespacePrefix($key), $dir . '/src');
        require_once $entry;
        $class = self::namespacePrefix($key) . 'Plugin';
        if (!class_exists($class)) {
            return null;
        }
        $instance = new $class();
        return $instance instanceof PluginInterface ? $instance : null;
    }

    private static function namespacePrefix(string $key): string
    {
        return 'LiteNotePlugin\\' . self::studly($key) . '\\';
    }

    private static function studly(string $key): string
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $key)));
    }

    private static function exists(string $key): bool
    {
        return is_file(self::pluginPath($key) . '/plugin.json');
    }

    private static function normalizeKey(string $key): string
    {
        $key = trim($key);
        if (!preg_match('/^[a-z0-9_-]+$/', $key)) {
            throw new \RuntimeException('插件标识不合法');
        }
        return $key;
    }

    private static function pluginPath(string $key): string
    {
        return self::pluginDirectory() . '/' . $key;
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
