<?php
declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\Helper;
use App\Core\View;
use App\Models\Category;
use App\Models\Post;

/**
 * 分类页（改进版）
 * 变更点：
 * 1. 使用 View Composer 注入共享侧边栏数据。
 * 2. 复用 Post::paginatePublished 预加载能力。
 * 3. 404 响应使用 Response::notFound 统一处理。
 */
class CategoryController
{
    public function __construct()
    {
        View::composer('layouts.front', function (array $data): array {
            return array_merge($data, [
                'categories'  => Category::allEnabled(),
                'recentPosts' => Post::recent(5),
            ]);
        });
    }

    public function show($request, array $params): string
    {
        $slug = $params['slug'] ?? '';
        $cat = Category::findBySlug($slug);
        if (!$cat) {
            \App\Core\Response::notFound("分类不存在: {$slug}");
        }

        $perPage = 5;
        $page = max(1, (int)($_GET['page'] ?? 1));
        ['items' => $posts, 'total' => $total] = Post::paginatePublished($page, $perPage, (int)$cat->id);

        return View::render('category.show', [
            'category'  => $cat,
            'posts'     => $posts,
            'total'     => $total,
            'page'      => $page,
            'perPage'   => $perPage,
            'paginator' => Helper::loadMore($page, $total, $perPage, Helper::url('/category/' . $slug)),
            'pageTitle' => $cat->name,
        ]);
    }
}
