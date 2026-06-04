<?php
declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\View;
use App\Models\Category;
use App\Models\Page;
use App\Models\Post;

class PageController
{
    public function show($request, array $params): string
    {
        $slug = $params['slug'] ?? '';
        $page = Page::findBySlug($slug);
        if (!$page) {
            return View::render('errors.404', ['message' => "页面不存在: {$slug}"], 'layouts.front');
        }
        $page->views = (int)$page->views + 1;
        $page->save();

        return View::render('page.show', [
            'page'  => $page,
            'pageTitle' => $page->title,
            'categories' => Category::allEnabled(),
            'recentPosts' => Post::recent(5),
        ], 'layouts.front');
    }
}
