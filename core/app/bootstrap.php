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

require __DIR__ . '/Core/Config.php';
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

// Load local environment variables before config.php reads env values.
// This keeps secrets out of SQLite/admin settings while preserving zero dependencies.
$__envPath = dirname(__DIR__, 2) . '/.env';
if (is_file($__envPath) && is_readable($__envPath)) {
    foreach (file($__envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $__line) {
        $__line = trim($__line);
        if ($__line === '' || str_starts_with($__line, '#') || !str_contains($__line, '=')) {
            continue;
        }
        [$__key, $__value] = array_map('trim', explode('=', $__line, 2));
        if ($__key === '' || getenv($__key) !== false || isset($_ENV[$__key]) || isset($_SERVER[$__key])) {
            continue;
        }
        if (
            (str_starts_with($__value, '"') && str_ends_with($__value, '"')) ||
            (str_starts_with($__value, "'") && str_ends_with($__value, "'"))
        ) {
            $__value = substr($__value, 1, -1);
        }
        if (function_exists('putenv')) {
            putenv($__key . '=' . $__value);
        }
        $_ENV[$__key] = $__value;
        $_SERVER[$__key] = $__value;
    }
}
unset($__envPath, $__line, $__key, $__value);

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
// - 部署后直接打开,用 admin / admin123 登录后自行修改即可。
$__dbPath = (string) Config::get('database.sqlite');
$__dbSeed = dirname($__dbPath) . '/database.default.sqlite';
if (!is_file($__dbPath) && is_file($__dbSeed)) {
    if (!is_dir(dirname($__dbPath))) {
        @mkdir(dirname($__dbPath), 0775, true);
    }
    @copy($__dbSeed, $__dbPath);
}
unset($__dbPath, $__dbSeed);

// 自动创建默认管理员（如果不存在）
if (is_file(Config::get('database.sqlite'))) {
    try {
        $userCount = \App\Models\User::count();
        if ($userCount === 0) {
            $defaultUser = new \App\Models\User([
                'username' => 'admin',
                'password' => password_hash('admin123', PASSWORD_DEFAULT),
                'nickname' => 'Administrator',
                'email'    => 'admin@litenote.local',
                'role'     => 'admin',
                'status'   => 1,
            ]);
            $defaultUser->save();
        }
    } catch (\Throwable $e) {
        // 忽略错误，避免影响启动
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
        View::share('site', Config::get('site'));
    } catch (\Throwable) {
        // 首次安装时数据库还没建好
    }
}
View::share('site', Config::get('site'));

$currentAdmin = null;
try {
    $adminId = (int) Session::get('admin_user.id', 0);
    $currentAdmin = $adminId > 0 ? \App\Models\User::find($adminId) : null;
} catch (\Throwable) {
    $currentAdmin = null;
}
View::share('currentAdmin', $currentAdmin);

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
            $navCategories = [];
            foreach (\App\Models\Category::navList() as $cat) {
                $navCategories[] = ['name' => $cat->name, 'slug' => $cat->slug, 'count' => $cat->postCount(), 'icon' => $cat->iconClass(), 'color' => $cat->colorIndex(), 'desc' => (string) ($cat->description ?? '')];
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
