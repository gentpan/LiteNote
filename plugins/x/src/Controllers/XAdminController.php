<?php
declare(strict_types=1);

namespace LiteNotePlugin\X\Controllers;

use App\Core\Helper;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use LiteNotePlugin\X\Models\XTweet;
use LiteNotePlugin\X\Services\TweetFetchService;

/**
 * 后台 X 推文管理:粘贴链接抓取发布、列表、删除。取代原 talk-form 的"分享 X"标签页。
 */
final class XAdminController
{
    public function index(): string
    {
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 20;
        ['items' => $list, 'total' => $total] = XTweet::paginate(
            $page,
            $perPage,
            'published_at DESC, created_at DESC, id DESC',
            '1 = 1'
        );

        return View::render('xadmin.tweets', [
            'list' => $list,
            'total' => $total,
            'paginator' => Helper::paginate($page, $total, $perPage, '/admin/x/tweets'),
            'csrf' => Session::csrfToken(),
            'pageTitle' => 'X 推文',
        ], 'layouts.admin');
    }

    public function store(Request $request): never
    {
        $url = trim((string)$request->input('tweet_url', ''));
        if ($url === '') {
            Session::flash('error', '请填写 X 链接');
            Response::redirect('/admin/x/tweets');
        }

        try {
            $data = (new TweetFetchService())->fetch($url);
        } catch (\Throwable $e) {
            Session::flash('error', 'X 链接获取失败：' . $e->getMessage());
            Response::redirect('/admin/x/tweets');
        }

        $content = trim((string)($data['text'] ?? ''));
        if ($content === '') {
            $content = 'X 原帖 ' . ((string)($data['url'] ?? $url));
        }
        $now = date('Y-m-d H:i:s');

        (new XTweet([
            'tweet_id' => (string)($data['id'] ?? XTweet::extractTweetId($url)),
            'tweet_url' => (string)($data['url'] ?? $url),
            'tweet_author_name' => (string)($data['author_name'] ?? ''),
            'tweet_author_handle' => (string)($data['author_handle'] ?? ''),
            'tweet_author_avatar' => (string)($data['author_avatar'] ?? ''),
            'tweet_author_verified' => !empty($data['author_verified']) ? 1 : 0,
            'tweet_posted_at' => $data['posted_at'] ?? null,
            'tweet_likes_count' => (int)($data['likes_count'] ?? 0),
            'tweet_reposts_count' => (int)($data['reposts_count'] ?? 0),
            'tweet_data' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'content' => $content,
            'images' => implode(',', array_slice($data['images'] ?? [], 0, 4)),
            'is_public' => 1,
            'likes_count' => 0,
            'comments_count' => 0,
            'published_at' => (string)($data['posted_at'] ?? $now),
            'created_at' => $now,
        ]))->save();

        Session::flash('success', '推文已发布');
        Response::redirect('/admin/x/tweets');
    }

    public function destroy(Request $request): never
    {
        $id = (int)$request->input('id', 0);
        if ($id > 0) {
            XTweet::db()->delete('x_tweets', 'id = ?', [$id]);
            XTweet::db()->delete('comments', 'x_tweet_id = ?', [$id]);
        }
        Session::flash('success', '推文已删除');
        Response::redirect('/admin/x/tweets');
    }
}
