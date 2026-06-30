<?php
declare(strict_types=1);

namespace LiteNotePlugin\X\Controllers;

use App\Core\Helper;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\AttachmentCleanupService;
use App\Services\PublicCacheService;
use App\Services\SearchIndexService;
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

        $existing = $this->findExistingTweet($data, $url);
        if ($existing) {
            XTweet::db()->update('x_tweets', $this->fieldsFromFetchedTweet($data, $url), 'id = :id', [':id' => (int)$existing->id]);
            SearchIndexService::syncXTweet((int)$existing->id);
            PublicCacheService::forgetAll();
            Session::flash('success', '推文已存在，已刷新缓存内容');
            Response::redirect('/admin/x/tweets');
        }

        $tweet = new XTweet(array_merge(
            $this->fieldsFromFetchedTweet($data, $url),
            [
                'is_public' => 1,
                'likes_count' => 0,
                'comments_count' => 0,
                'created_at' => date('Y-m-d H:i:s'),
            ]
        ));
        $tweet->save();

        SearchIndexService::syncXTweet((int)$tweet->id);
        PublicCacheService::forgetAll();
        Session::flash('success', '推文已发布');
        Response::redirect('/admin/x/tweets');
    }

    public function refresh(Request $request): never
    {
        $id = (int)$request->input('id', 0);
        $tweet = $id > 0 ? XTweet::find($id) : null;
        if (!$tweet) {
            Session::flash('error', '推文不存在');
            Response::redirect('/admin/x/tweets');
        }

        $source = $tweet->tweetUrl() ?: $tweet->tweetId();
        if ($source === '') {
            Session::flash('error', '这条推文缺少可刷新的 X 链接');
            Response::redirect('/admin/x/tweets');
        }

        try {
            $data = (new TweetFetchService())->fetch($source, true);
        } catch (\Throwable $e) {
            Session::flash('error', '推文刷新失败：' . $e->getMessage());
            Response::redirect('/admin/x/tweets');
        }

        XTweet::db()->update('x_tweets', $this->fieldsFromFetchedTweet($data, $source), 'id = :id', [':id' => $id]);
        SearchIndexService::syncXTweet($id);
        PublicCacheService::forgetAll();
        Session::flash('success', '推文内容和图片缓存已刷新');
        Response::redirect('/admin/x/tweets');
    }

    public function destroy(Request $request): never
    {
        $id = (int)$request->input('id', 0);
        if ($id > 0) {
            $tweet = XTweet::find($id);
            $attachmentValues = $tweet ? [
                (string)($tweet->images ?? ''),
                (string)($tweet->content ?? ''),
                (string)($tweet->tweet_data ?? ''),
                (string)($tweet->tweet_author_avatar ?? ''),
            ] : [];
            $db = XTweet::db();
            try {
                $db->beginTransaction();
                $db->delete('comments', 'x_tweet_id = ?', [$id]);
                $db->delete('x_tweets', 'id = ?', [$id]);
                $db->commit();
            } catch (\Throwable) {
                $db->rollBack();
                Session::flash('error', '推文删除失败，请稍后重试');
                Response::redirect('/admin/x/tweets');
            }
            AttachmentCleanupService::deleteUnusedFromValues($attachmentValues);
            SearchIndexService::remove('x', $id);
        }
        PublicCacheService::forgetAll();
        Session::flash('success', '推文已删除');
        Response::redirect('/admin/x/tweets');
    }

    private function findExistingTweet(array $data, string $fallbackUrl): ?XTweet
    {
        $tweetId = trim((string)($data['id'] ?? XTweet::extractTweetId($fallbackUrl)));
        if ($tweetId !== '') {
            $tweet = XTweet::findBy('tweet_id', $tweetId);
            if ($tweet) {
                return $tweet;
            }
        }

        $url = trim((string)($data['url'] ?? $fallbackUrl));
        return $url !== '' ? XTweet::findBy('tweet_url', $url) : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function fieldsFromFetchedTweet(array $data, string $fallbackUrl): array
    {
        $content = trim((string)($data['text'] ?? ''));
        $url = (string)($data['url'] ?? $fallbackUrl);
        if ($content === '') {
            $content = 'X 原帖 ' . $url;
        }

        $postedAt = $data['posted_at'] ?? null;
        $now = date('Y-m-d H:i:s');

        return [
            'tweet_id' => (string)($data['id'] ?? XTweet::extractTweetId($fallbackUrl)),
            'tweet_url' => $url,
            'tweet_author_name' => (string)($data['author_name'] ?? ''),
            'tweet_author_handle' => (string)($data['author_handle'] ?? ''),
            'tweet_author_avatar' => (string)($data['author_avatar'] ?? ''),
            'tweet_author_verified' => !empty($data['author_verified']) ? 1 : 0,
            'tweet_posted_at' => $postedAt,
            'tweet_likes_count' => (int)($data['likes_count'] ?? 0),
            'tweet_reposts_count' => (int)($data['reposts_count'] ?? 0),
            'tweet_data' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'content' => $content,
            'images' => implode(',', array_slice($data['images'] ?? [], 0, 4)),
            'published_at' => (string)($postedAt ?? $now),
        ];
    }
}
