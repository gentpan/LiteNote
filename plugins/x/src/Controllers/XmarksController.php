<?php
declare(strict_types=1);

namespace LiteNotePlugin\X\Controllers;

use App\Core\Helper;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\Activity;
use LiteNotePlugin\X\Models\XTweet;
use LiteNotePlugin\X\Support\TweetCardItem;

/**
 * 前台书签页面(承接原 App\Controllers\Front\TalkController::xmarks)。
 * 数据来自核心 activities 表(source=x_bookmarks),用 TweetCardItem 适配给推文卡片视图。
 */
final class XmarksController
{
    public function index(): string
    {
        $perPage = 24;
        $page = max(1, (int)($_GET['page'] ?? 1));
        ['items' => $items, 'total' => $total] = Activity::paginate($page, $perPage, ['source' => 'x_bookmarks'], true);
        $list = array_map(static fn(Activity $activity): TweetCardItem => new TweetCardItem($activity), $items);
        $lastUpdatedAt = !empty($list) ? $list[0]->publishedAt() : '';

        return View::render('front.x.index', [
            'list' => $list,
            'total' => $total,
            'lastUpdatedAt' => $lastUpdatedAt,
            'page' => $page,
            'perPage' => $perPage,
            'paginator' => Helper::loadMore($page, $total, $perPage, Helper::url('/xmarks')),
            'pageTitle' => '书签',
            'activeNav' => 'xmarks',
        ], 'layouts.front');
    }

    /** 首页推文卡片的本地点赞(独立于核心 /talk/{id}/like)。 */
    public function like(Request $request, array $params): never
    {
        $id = (int)($params['id'] ?? 0);
        $row = XTweet::find($id);
        if (!$row || (int)$row->is_public !== 1) {
            Response::json(['code' => 1, 'msg' => '推文不存在'], 404);
        }

        $liked = Session::get('liked_x_tweet', []);
        $liked = is_array($liked) ? $liked : [];
        if (!empty($liked[$id])) {
            Response::json(['code' => 2, 'msg' => '已经点赞过了', 'likes' => (int)($row->likes_count ?? 0), 'liked' => true]);
        }

        $count = XTweet::like($id);
        $liked[$id] = 1;
        Session::set('liked_x_tweet', $liked);
        Response::json(['code' => 0, 'likes' => $count, 'liked' => true]);
    }

    /** 书签页的本地点赞(站点自己的,计数存 activity metadata.local_likes)。 */
    public function likeBookmark(Request $request, array $params): never
    {
        $id = (int)($params['id'] ?? 0);
        $activity = Activity::find($id);
        if (!$activity || (string)$activity->source !== 'x_bookmarks' || (string)$activity->visibility !== 'public') {
            Response::json(['code' => 1, 'msg' => '书签不存在'], 404);
        }

        $meta = $activity->metadata();
        $current = (int)($meta['local_likes'] ?? 0);

        $liked = Session::get('liked_xmark', []);
        $liked = is_array($liked) ? $liked : [];
        if (!empty($liked[$id])) {
            Response::json(['code' => 2, 'msg' => '已经点赞过了', 'likes' => $current, 'liked' => true]);
        }

        $meta['local_likes'] = $current + 1;
        $activity->metadata = json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $activity->save();

        $liked[$id] = 1;
        Session::set('liked_xmark', $liked);
        Response::json(['code' => 0, 'likes' => $current + 1, 'liked' => true]);
    }
}
