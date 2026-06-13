<?php
declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\Response;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Enums\CommentStatus;
use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\Category;
use App\Models\Comment;
use App\Services\PermalinkService;

class PostController
{
    public function like(Request $request, array $params): never
    {
        $id = (int)($params['id'] ?? 0);
        Post::ensurePublishingOptionsSchema();
        $post = Post::find($id);
        if (!$post || $post->status !== PostStatus::Published->value || (int)($post->is_private ?? 0) === 1) {
            Response::json(['code' => 1, 'msg' => '文章不存在'], 404);
        }

        $liked = Session::get('liked_post', []);
        $liked = is_array($liked) ? $liked : [];
        if (!empty($liked[$id])) {
            Response::json([
                'code' => 2,
                'msg' => '已经点赞过了',
                'likes' => (int)($post->likes_count ?? 0),
                'liked' => true,
            ]);
        }

        $count = Post::like($id);
        $liked[$id] = 1;
        Session::set('liked_post', $liked);
        Response::json(['code' => 0, 'likes' => $count, 'liked' => true]);
    }

    public function show(Request $request, array $params): string
    {
        $slug = $params['slug'] ?? '';
        Post::ensurePublishingOptionsSchema();
        $post = PermalinkService::resolve((string)$request->path) ?: PermalinkService::resolveLegacyDefault($slug);
        if (!$post || $post->status !== PostStatus::Published->value || (int)($post->is_private ?? 0) === 1) {
            return $this->notFound($slug);
        }
        $post->incrementViews();
        $category = $post->getCategory();
        $comments = Comment::forPost((int)$post->id, CommentStatus::Approved, 500);

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
