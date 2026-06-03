<?php
declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\Helper;
use App\Core\View;
use App\Models\Category;
use App\Models\Post;

class SearchController
{
    public function index(): string
    {
        $keyword = trim((string)($_GET['q'] ?? ''));
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = (int) \App\Core\Config::get('pagination.front_per_page', 10);

        $items = [];
        $total = 0;
        if ($keyword !== '') {
            ['items' => $items, 'total' => $total] = Post::search($keyword, $page, $perPage);
        }

        return View::render('search.index', [
            'keyword' => $keyword,
            'posts'   => $items,
            'total'   => $total,
            'page'    => $page,
            'perPage' => $perPage,
            'paginator' => Helper::paginate($page, $total, $perPage, Helper::url('/search') . '?q=' . urlencode($keyword)),
            'pageTitle' => '搜索: ' . $keyword,
            'categories' => Category::allEnabled(),
            'recentPosts' => Post::recent(5),
        ], 'layouts.front');
    }
}
