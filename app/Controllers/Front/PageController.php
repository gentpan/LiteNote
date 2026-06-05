<?php
declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\Response;
use App\Core\View;
use App\Models\Category;
use App\Models\Page;
use App\Models\Post;

class PageController
{
    public function show($request, array $params): string
    {
        if (str_ends_with((string) $request->path, '.html')) {
            Response::notFound('404 - 页面不存在');
        }

        $slug = $params['slug'] ?? '';
        $page = Page::findBySlug($slug);
        if (!$page) {
            return View::render('errors.404', ['message' => "页面不存在: {$slug}"], 'layouts.front');
        }
        if ($page->isSystem()) {
            Response::redirect($page->getUrl());
        }
        $page->views = (int)$page->views + 1;
        $page->save();

        return View::render('page.show', [
            'page'  => $page,
            'pageTitle' => $page->title,
            'activeNav' => (string) $page->slug,
            'categories' => Category::allEnabled(),
            'recentPosts' => Post::recent(5),
        ], 'layouts.front');
    }
}
