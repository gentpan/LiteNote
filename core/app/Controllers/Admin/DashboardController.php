<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Session;
use App\Core\View;
use App\Enums\CommentStatus;
use App\Models\Comment;
use App\Models\Post;

class DashboardController
{
    public function index(): string
    {
        $pending = CommentStatus::Pending->value;
        $stats = [
            'posts'    => Post::count(),
            'comments' => Post::db()->fetchColumn('SELECT COUNT(*) FROM comments') ?: 0,
            'pending'  => Comment::count(['status' => $pending]),
        ];
        $latestPosts    = Post::query("SELECT * FROM posts ORDER BY id DESC LIMIT 5");
        $pendingComments = Post::db()->fetchAll(
            "SELECT c.*, p.id AS target_post_id, p.slug AS target_slug, pc.slug AS target_category_slug, COALESCE(p.title, pg.title) AS target_title
             FROM comments c
             LEFT JOIN posts p ON c.post_id = p.id
             LEFT JOIN categories pc ON p.category_id = pc.id
             LEFT JOIN pages pg ON c.page_id = pg.id
             WHERE c.status = '{$pending}' ORDER BY c.id DESC LIMIT 5"
        );

        return View::render('dashboard.index', [
            'stats' => $stats,
            'latestPosts' => $latestPosts,
            'pendingComments' => $pendingComments,
            'admin' => Session::get('admin_user'),
            'pageTitle' => '仪表盘',
        ], 'layouts.admin');
    }
}
