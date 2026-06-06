<?php
declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\Response;
use App\Core\View;
use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\Category;
use App\Models\Comment;
use App\Services\PermalinkService;

class PostController
{
    public function show($request, array $params): string
    {
        $slug = $params['slug'] ?? '';
        $post = PermalinkService::resolve((string)$request->path) ?: PermalinkService::resolveLegacyDefault($slug);
        if (!$post || $post->status !== PostStatus::Published->value) {
            return $this->notFound($slug);
        }
        $post->incrementViews();
        $category = $post->getCategory();
        $comments = Comment::forPost((int)$post->id);

        return View::render('post.show', [
            'post'    => $post,
            'category'=> $category,
            'comments'=> $comments,
            'pageTitle' => $post->title,
            'recentPosts' => Post::recent(5),
            'categories' => Category::allEnabled(),
        ], 'layouts.front');
    }

    private function notFound(string $slug): string
    {
        http_response_code(404);
        return View::render('errors.404', [
            'message' => "文章不存在: {$slug}",
            'pageTitle' => '404',
        ], 'layouts.front');
    }
}
