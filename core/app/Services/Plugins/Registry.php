<?php
declare(strict_types=1);

namespace App\Services\Plugins;

use App\Core\Router;
use App\Middleware\AdminAuth;
use App\Services\ActivityAdapters\ActivityAdapter;
use App\Services\ThemeManager;

/**
 * 插件扩展点的中央注册表。
 *
 * 全静态:插件在启动时(PluginManager::boot)通过 PluginContext 把各类扩展登记进来,
 * 核心的各个"集成点"再从这里读取并合并进内置项。一次请求只 boot 一次,故无需 reset。
 */
final class Registry
{
    /** @var array<int,ActivityAdapter> */
    private static array $adapters = [];
    /** @var array<string,array<string,mixed>> provider => 定义 */
    private static array $providers = [];
    /** @var array<int,array<string,mixed>> 后台菜单项 */
    private static array $adminMenus = [];
    /** @var array<string,array<string,mixed>> slug => 前台导航页定义 */
    private static array $navPages = [];
    /** @var array<int,string> 插件视图目录(绝对路径) */
    private static array $viewDirs = [];
    /** @var array<int,callable> 前台路由注册回调 function(Router $r) */
    private static array $webRouteCbs = [];
    /** @var array<int,callable> 后台路由注册回调 function(Router $r),apply 时套 /admin + AdminAuth */
    private static array $adminRouteCbs = [];
    /** @var array<int,callable> 首页时间线贡献者 function(): array<int,array> */
    private static array $homeFeedContributors = [];
    /** @var array<int,string> 注入前台 <head> 的 HTML 片段 */
    private static array $frontHead = [];
    /** @var array<string,string> PSR-4 前缀 => 源码目录,供插件 autoloader 使用 */
    private static array $psr4 = [];

    // ---------- 注册(由 PluginContext / PluginManager 调用) ----------

    public static function addAdapter(ActivityAdapter $adapter): void
    {
        self::$adapters[] = $adapter;
    }

    public static function addProvider(string $provider, array $definition): void
    {
        self::$providers[$provider] = $definition;
    }

    public static function addAdminMenu(array $item): void
    {
        self::$adminMenus[] = $item;
    }

    public static function addNavPage(string $slug, array $definition): void
    {
        self::$navPages[$slug] = $definition;
    }

    public static function addViewDir(string $dir): void
    {
        $dir = rtrim($dir, '/');
        if ($dir !== '' && !in_array($dir, self::$viewDirs, true)) {
            self::$viewDirs[] = $dir;
        }
    }

    public static function addWebRoutes(callable $cb): void
    {
        self::$webRouteCbs[] = $cb;
    }

    public static function addAdminRoutes(callable $cb): void
    {
        self::$adminRouteCbs[] = $cb;
    }

    public static function addHomeFeedContributor(callable $cb): void
    {
        self::$homeFeedContributors[] = $cb;
    }

    public static function addFrontHead(string $html): void
    {
        if (trim($html) !== '') {
            self::$frontHead[] = $html;
        }
    }

    public static function addPsr4(string $prefix, string $baseDir): void
    {
        self::$psr4[$prefix] = rtrim($baseDir, '/');
    }

    // ---------- 读取(由核心集成点调用) ----------

    /** @return array<int,ActivityAdapter> */
    public static function adapters(): array
    {
        return self::$adapters;
    }

    /** @return array<string,array<string,mixed>> */
    public static function providers(): array
    {
        return self::$providers;
    }

    /** @return array<int,array<string,mixed>> 已按 sort 升序 */
    public static function adminMenus(): array
    {
        $items = self::$adminMenus;
        usort($items, static fn(array $a, array $b): int => ((int)($a['sort'] ?? 100)) <=> ((int)($b['sort'] ?? 100)));
        return $items;
    }

    /** @return array<string,array<string,mixed>> */
    public static function navPages(): array
    {
        return self::$navPages;
    }

    /** @return array<int,callable> */
    public static function homeFeedContributors(): array
    {
        return self::$homeFeedContributors;
    }

    public static function frontHeadHtml(): string
    {
        return implode("\n", self::$frontHead);
    }

    /** @return array<string,string> */
    public static function psr4Map(): array
    {
        return self::$psr4;
    }

    /**
     * 把首页时间线贡献者的产出汇总成一个扁平数组(每项形如
     * ['type'=>..., 'partial'=>..., 'time'=>int, 'item'=>obj, 'pinned'=>bool])。
     *
     * @return array<int,array<string,mixed>>
     */
    public static function collectHomeFeedItems(): array
    {
        $items = [];
        foreach (self::$homeFeedContributors as $cb) {
            $produced = $cb();
            if (is_array($produced)) {
                foreach ($produced as $entry) {
                    if (is_array($entry)) {
                        $items[] = $entry;
                    }
                }
            }
        }
        return $items;
    }

    /**
     * 在插件视图目录里解析前台模板。复用 ThemeManager 的"模板名→文件名"映射,
     * 命中第一个存在的文件即返回其物理路径,否则 null(交回 View 继续回落)。
     */
    public static function viewPath(string $template): ?string
    {
        if (self::$viewDirs === []) {
            return null;
        }
        // 前台模板用 ThemeManager 的文件名映射;其它(如插件后台页)用点→斜杠的直接映射。
        $candidates = [
            ThemeManager::templateFile($template),
            str_replace('.', '/', $template) . '.php',
        ];
        foreach (self::$viewDirs as $dir) {
            foreach ($candidates as $relative) {
                $path = $dir . '/' . $relative;
                if (is_file($path)) {
                    return $path;
                }
            }
        }
        return null;
    }

    /**
     * 把插件注册的路由应用到给定 Router。
     *  - 'web'  :直接执行回调(调用方负责把本次 apply 安排在核心 catch-all 路由之前);
     *  - 'admin':统一包进 group('/admin', ..., [AdminAuth]),与核心后台路由享有同样的前缀与鉴权。
     */
    public static function applyRoutes(Router $router, string $kind): void
    {
        if ($kind === 'web') {
            foreach (self::$webRouteCbs as $cb) {
                $cb($router);
            }
            return;
        }

        if ($kind === 'admin' && self::$adminRouteCbs !== []) {
            $router->group('/admin', static function (Router $r): void {
                foreach (self::$adminRouteCbs as $cb) {
                    $cb($r);
                }
            }, [AdminAuth::class]);
        }
    }
}
