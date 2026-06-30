<?php
declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\FrontCsrf;
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
use App\Core\Helper;

class PostController
{
    public function like(Request $request, array $params): never
    {
        FrontCsrf::verify($request);

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
        $post->trackView();
        $category = $post->getCategory();
        $commentsPerPage = 50;
        $comments = Comment::forPost((int)$post->id, CommentStatus::Approved, $commentsPerPage);
        $commentsTotal = Comment::count(['post_id' => (int)$post->id, 'status' => CommentStatus::Approved->value]);

        return View::render('post.show', [
            'post'    => $post,
            'category'=> $category,
            'comments'=> $comments,
            'commentsTotal' => $commentsTotal,
            'commentsHasMore' => $commentsTotal > $commentsPerPage,
            'commentsPerPage' => $commentsPerPage,
            'pageTitle' => $post->title,
            'recentPosts' => Post::recent(5),
            'categories' => Category::allEnabled(),
        ], 'layouts.front');
    }

    public function comments(Request $request, array $params): never
    {
        $id = (int)($params['id'] ?? 0);
        $offset = max(0, (int)$request->input('offset', 0));
        $limit = max(1, min(50, (int)$request->input('limit', 50)));
        Post::ensurePublishingOptionsSchema();
        $post = Post::find($id);
        if (!$post || $post->status !== PostStatus::Published->value || (int)($post->is_private ?? 0) === 1) {
            Response::json(['code' => 1, 'msg' => '文章不存在'], 404);
        }

        $total = Comment::count(['post_id' => $id, 'status' => CommentStatus::Approved->value]);
        $comments = Comment::forPost($id, CommentStatus::Approved, $limit, $offset);
        $threads = Helper::nestComments($comments);
        $html = View::render('partials.post-comment-threads', [
            'threads' => $threads,
            'commentsOpen' => (int)($post->allow_comments ?? 1) === 1,
            'currentAdmin' => null,
        ]);

        Response::json([
            'code' => 0,
            'html' => $html,
            'count' => count($comments),
            'nextOffset' => $offset + count($comments),
            'hasMore' => ($offset + count($comments)) < $total,
            'total' => $total,
        ]);
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
