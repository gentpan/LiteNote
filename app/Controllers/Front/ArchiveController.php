<?php
declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\Helper;
use App\Core\View;
use App\Models\Category;
use App\Models\Post;

class ArchiveController
{
    public function index(): string
    {
        $posts = Post::archives();
        // 按月分组
        $grouped = [];
        foreach ($posts as $p) {
            $month = substr($p['published_at'] ?? '', 0, 7);
            $grouped[$month][] = $p;
        }

        return View::render('archive.index', [
            'grouped' => $grouped,
            'total'   => count($posts),
            'pageTitle' => '归档',
            'categories' => Category::allEnabled(),
            'recentPosts' => Post::recent(5),
        ], 'layouts.front');
    }
}
