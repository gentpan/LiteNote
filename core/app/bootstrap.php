<?php
declare(strict_types=1);

/**
 * 应用启动
 * - 加载自动加载
 * - 初始化核心
 * - 共享站点数据
 */

use App\Core\Config;
use App\Core\Database;
use App\Core\Session;
use App\Core\View;
use App\Models\Setting;

require_once __DIR__ . '/Core/Config.php';
require __DIR__ . '/Core/Database.php';
require __DIR__ . '/Core/Request.php';
require __DIR__ . '/Core/Response.php';
require __DIR__ . '/Core/ApiResponse.php';
require __DIR__ . '/Core/FileCache.php';
require __DIR__ . '/Core/Router.php';
require __DIR__ . '/Core/TemplateMap.php';
require __DIR__ . '/Core/View.php';
require_once __DIR__ . '/Core/StaticAssetServer.php';
require __DIR__ . '/Core/Session.php';
require __DIR__ . '/Core/Helper.php';
require __DIR__ . '/Core/Validator.php';
require __DIR__ . '/Core/Markdown.php';
require __DIR__ . '/Core/Rss.php';

// 简易自动加载（按命名空间映射目录）
spl_autoload_register(function (string $class) {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $relative = str_replace('\\', '/', $relative);
    $file = __DIR__ . '/' . $relative . '.php';
    if (is_file($file)) {
        require $file;
    }
});

// 加载配置
Config::load('config');

\App\Services\RuntimeRequirements::verify();

// 时区
date_default_timezone_set(Config::get('app.timezone', 'UTC'));

// 错误处理
if (Config::get('app.debug', false)) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

// Session
Session::start();

// 首次部署:本地还没有数据库时,从模板库复制一份默认数据,实现"上传即用"。
// - 模板库 database.default.sqlite 随仓库分发(含表结构 + 默认管理员 + 示例数据);
// - live 库 database.sqlite 被 git 忽略,每个部署各自独立;
// - 部署后请查看 runtime/storage/.initial-admin-password 获取随机初始密码。
$__dbPath = (string) Config::get('database.sqlite');
$__dbSeed = dirname($__dbPath) . '/database.default.sqlite';
if (!is_file($__dbPath) && is_file($__dbSeed)) {
    $__dbDir = dirname($__dbPath);
    if (!is_dir($__dbDir) && !mkdir($__dbDir, 0775, true) && !is_dir($__dbDir)) {
        error_log('LiteNote bootstrap: 无法创建数据库目录 ' . $__dbDir);
    } elseif (!copy($__dbSeed, $__dbPath)) {
        error_log('LiteNote bootstrap: 无法复制种子数据库 ' . $__dbSeed . ' -> ' . $__dbPath);
    }
}
unset($__dbPath, $__dbSeed, $__dbDir);

// 自动创建默认管理员（如果不存在）
if (is_file(Config::get('database.sqlite'))) {
    try {
        \App\Services\Installer::ensureUserAuthColumns();
        \App\Services\Installer::ensureDefaultAdmin();
        \App\Services\Installer::ensureWelcomePostContent();
        \App\Services\MigrationRunner::run();
        \App\Services\SearchIndexService::install();
        \App\Services\ArticleFontService::ensureDefaults();
    } catch (\Throwable $e) {
        error_log('LiteNote bootstrap: 默认管理员创建失败: ' . $e->getMessage());
    }
}

// 共享站点设置到视图
if (is_file(Config::get('database.sqlite'))) {
    try {
        $settings = Setting::allAsArray();
        foreach ($settings as $k => $v) {
            View::share($k, $v);
            Config::set("site.{$k}", $v);
        }
        $mapboxToken = getenv('SITE_MAPBOX_TOKEN');
        if ($mapboxToken !== false && trim((string)$mapboxToken) !== '') {
            Config::set('site.site_mapbox_token', trim((string)$mapboxToken));
            View::share('site_mapbox_token', trim((string)$mapboxToken));
        }
        View::share('site', Config::get('site'));
    } catch (\Throwable) {
        // 首次安装时数据库还没建好，静默忽略
    }
}
View::share('site', Config::get('site'));

$currentAdmin = null;
$currentMember = null;
try {
    $sessionUser = Session::get('admin_user');
    $userId = is_array($sessionUser) ? (int) ($sessionUser['id'] ?? 0) : 0;
    if ($userId > 0) {
        $sessionRole = (string) ($sessionUser['role'] ?? '');
        $loaded = \App\Models\User::find($userId);
        if ($loaded) {
            $role = (string) ($loaded->role ?: $sessionRole);
            if ($role === 'admin') {
                $currentAdmin = $loaded;
            } else {
                // 前台不再提供普通用户账号；旧读者会话按访客评论身份处理。
                Session::forget('admin_user');
            }
        }
    }
} catch (\Throwable) {
    // 数据库未就绪时静默忽略
    $currentAdmin = null;
    $currentMember = null;
}
View::share('currentAdmin', $currentAdmin);
View::share('currentMember', $currentMember);

// 全局 View Composer:任意前台模板渲染时,自动注入 site author
//   - $author  : App\Models\User(站点主理人,id=1)
//   - $socials : 解析后的社交链接数组
//
// 注意:模板渲染时实际传的是完整路径,如 "front.talk.index",
// pattern 必须用 "*.xxx.*" 才能跨 front/admin 前缀匹配。
View::composer(['*layouts.front', '*layouts.admin', '*front.*', '*home.*', '*post.*', '*page.*', '*category.*', '*archive.*', '*search.*', '*talk.*', '*music.*', '*friend.*'], function (array $data): array {
    static $cached = null;
    try {
        if ($cached === null) {
            $author = \App\Models\User::find(1);
            $categoryPostCounts = [];
            try {
                $counts = \App\Models\Post::db()->fetchAll(
                    "SELECT category_id, COUNT(*) AS total FROM posts WHERE status = ? AND COALESCE(is_private, 0) = 0 GROUP BY category_id",
                    [\App\Enums\PostStatus::Published->value]
                );
                foreach ($counts as $c) {
                    $categoryPostCounts[(int)$c['category_id']] = (int)$c['total'];
                }
            } catch (\Throwable) {
            }
            $navCategories = [];
            foreach (\App\Models\Category::navList() as $cat) {
                $navCategories[] = [
                    'name' => $cat->name,
                    'slug' => $cat->slug,
                    'count' => $categoryPostCounts[(int)$cat->id] ?? 0,
                    'icon' => $cat->iconClass(),
                    'color' => $cat->colorIndex(),
                    'desc' => (string) ($cat->description ?? ''),
                ];
            }
            $navItems = [];
            foreach (\App\Models\Page::navItems() as $page) {
                $navItems[] = [
                    'title' => (string) $page->title,
                    'slug' => (string) $page->slug,
                    'url' => $page->getUrl(),
                    'icon' => $page->iconClass(),
                    'is_system' => $page->isSystem() ? 1 : 0,
                ];
            }
            $cached = [
                'author'        => $author,
                'socials'       => $author ? $author->getSocialLinks() : [],
                'navCategories' => $navCategories,
                'navItems'      => $navItems,
            ];
        }
        $data['author']        = $cached['author'];
        $data['socials']       = $cached['socials'];
        $data['navCategories'] = $cached['navCategories'];
        $data['navItems']      = $cached['navItems'];
    } catch (\Throwable) {
        // 数据库未就绪
    }
    return $data;
});

// 前台布局侧边栏：categories / recentPosts 只注册一次，避免 Controller 构造时重复追加 Composer。
View::composer(['*layouts.front'], function (array $data): array {
    static $sidebar = null;
    try {
        if ($sidebar === null) {
            $sidebar = [
                'categories'  => \App\Models\Category::allEnabled(),
                'recentPosts' => \App\Models\Post::recent(5),
            ];
        }
        $data['categories']  = $data['categories']  ?? $sidebar['categories'];
        $data['recentPosts'] = $data['recentPosts'] ?? $sidebar['recentPosts'];
    } catch (\Throwable) {
    }
    return $data;
});

// 加载已启用的插件:注册插件 autoloader,并让每个启用插件通过 register(PluginContext)
// 把路由回调 / Activity 适配器 / 后台菜单 / 导航页 / 视图目录 / head 片段登记进 Registry。
// 路由回调此刻只是被收集,真正 apply 到 $router 发生在 routes 文件内(见 web.php / admin.php 末尾)。
try {
    \App\Services\PluginManager::boot();
    View::share('__pluginMenus', \App\Services\Plugins\Registry::adminMenus());
    View::share('__pluginFrontHead', \App\Services\Plugins\Registry::frontHeadHtml());
} catch (\Throwable $e) {
    error_log('LiteNote bootstrap: 插件启动失败: ' . $e->getMessage());
}
