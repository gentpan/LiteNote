<?php
declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\Helper;
use App\Core\View;
use App\Models\Category;
use App\Models\Post;
use App\Models\Page;
use App\Models\Shuoshuo;
use App\Services\Installer;

/**
 * 首页（改进版）
 * 变更点：
 * 1. 使用 View Composer 注入共享侧边栏数据，消除每个 Controller 重复传递 categories / recentPosts。
 * 2. 使用 Post::paginatePublished() 的预加载能力，消除 N+1。
 * 3. 安装检测提前返回，避免无意义查询。
 */
class HomeController
{
    public function __construct()
    {
        // 注册前台布局的 View Composer，自动注入共享数据
        View::composer('layouts.front', function (array $data): array {
            return array_merge($data, [
                'categories'  => Category::allEnabled(),
                'recentPosts' => Post::recent(5),
            ]);
        });
    }

    public function index(): string
    {
        if (!Installer::isInstalled()) {
            return View::render('install.prompt', [
                'installUrl' => Helper::url('/install'),
                'pageTitle'  => '需要安装',
            ]);
        }

        $perPage = (int) \App\Core\Config::get('pagination.front_per_page', 10);
        $page = max(1, (int)($_GET['page'] ?? 1));
        ['items' => $posts, 'total' => $total] = Post::paginatePublished($page, $perPage);

        return View::render('home.index', [
            'posts'     => $posts,
            'total'     => $total,
            'page'      => $page,
            'perPage'   => $perPage,
            'paginator' => Helper::paginate($page, $total, $perPage, Helper::url('/')),
            'recentShuoshuo' => Shuoshuo::paginate(1, 5)['items'],
            'pageTitle' => null,
        ]);
    }
}
