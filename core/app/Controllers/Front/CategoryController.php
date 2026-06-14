<?php
declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\Response;
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
    private const PER_PAGE = 10;

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
        $slug = Category::decodeSlug((string)($params['slug'] ?? ''));
        $cat = Category::findBySlug($slug);
        if (!$cat) {
            \App\Core\Response::notFound("分类不存在: {$slug}");
        }

        $perPage = self::PER_PAGE;
        $page = max(1, (int)($_GET['page'] ?? 1));
        ['items' => $posts, 'total' => $total] = Post::paginatePublished($page, $perPage, (int)$cat->id);
        $articleStats = $cat->getArticleStats();

        return View::render('category.show', [
            'category'  => $cat,
            'posts'     => $posts,
            'total'     => $total,
            'articleStats' => $articleStats,
            'page'      => $page,
            'perPage'   => $perPage,
            'categoryHasMore' => ($page * $perPage) < $total,
            'pageTitle' => $cat->name,
        ]);
    }

    public function feed($request, array $params): never
    {
        $slug = Category::decodeSlug((string)($params['slug'] ?? ''));
        $cat = Category::findBySlug($slug);
        if (!$cat) {
            Response::json(['code' => 404, 'msg' => '分类不存在'], 404);
        }

        $limit = self::PER_PAGE;
        $offset = max(0, (int)($_GET['offset'] ?? 0));
        $page = (int) floor($offset / $limit) + 1;
        ['items' => $posts, 'total' => $total] = Post::paginatePublished($page, $limit, (int)$cat->id);
        $html = View::render('partials.category-post-items', [
            'posts' => $posts,
            'offset' => $offset,
        ]);

        Response::json([
            'code' => 0,
            'html' => $html,
            'count' => count($posts),
            'nextOffset' => $offset + count($posts),
            'hasMore' => ($offset + count($posts)) < $total,
        ]);
    }
}
