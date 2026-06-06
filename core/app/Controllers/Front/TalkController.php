<?php
declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\Helper;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Enums\Toggle;
use App\Models\Comment;
use App\Models\Activity;
use App\Models\Talk;
use App\Support\TweetCardItem;

class TalkController
{
    public function index(): string
    {
        $perPage = 10;
        $page = max(1, (int)($_GET['page'] ?? 1));
        $whereSql = "is_public = ? AND COALESCE(music_id, 0) = 0 AND (COALESCE(post_type, '') = '' OR post_type = 'talk') AND COALESCE(tweet_id, '') = '' AND COALESCE(tweet_url, '') = ''";
        ['items' => $list, 'total' => $total] = Talk::paginate(
            $page,
            $perPage,
            'published_at DESC, created_at DESC, id DESC',
            $whereSql,
            [Toggle::On->value]
        );
        $this->attachTalkComments($list);

        return View::render('front.talk.index', [
            'list' => $list,
            'total' => $total,
            'page'  => $page,
            'perPage' => $perPage,
            'paginator' => Helper::loadMore($page, $total, $perPage, Helper::url('/talk')),
            'pageTitle' => '滔客',
            'activeNav' => 'talk',
        ]);
    }

    public function x(): never
    {
        Response::notFound('404 - 页面不存在');
    }

    public function xmarks(): string
    {
        $perPage = 24;
        $page = max(1, (int)($_GET['page'] ?? 1));
        ['items' => $items, 'total' => $total] = Activity::paginate($page, $perPage, ['source' => 'x_bookmarks'], true);
        $list = array_map(static fn(Activity $activity): TweetCardItem => new TweetCardItem($activity), $items);

        return View::render('front.x.index', [
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'paginator' => Helper::loadMore($page, $total, $perPage, Helper::url('/xmarks')),
            'pageTitle' => 'Xmarks',
            'activeNav' => 'xmarks',
        ], 'layouts.front');
    }

    public function like(Request $request, array $params): never
    {
        $id = (int)($params['id'] ?? 0);
        $item = Talk::find((int)$id);
        if (!$item || (int)$item->is_public !== 1) {
            Response::json(['code' => 1, 'msg' => '滔客不存在'], 404);
        }

        $liked = Session::get('liked_talk', []);
        $liked = is_array($liked) ? $liked : [];
        if (!empty($liked[$id])) {
            Response::json([
                'code' => 2,
                'msg' => '已经点赞过了',
                'likes' => (int)($item->likes_count ?? 0),
                'liked' => true,
            ]);
        }

        $count = Talk::like((int)$id);
        $liked[$id] = 1;
        Session::set('liked_talk', $liked);
        Response::json(['code' => 0, 'likes' => $count, 'liked' => true]);
    }

    public function publish(Request $request): never
    {
        if (!Session::has('admin_user.id')) {
            Response::redirect('/admin/login');
        }

        if (!Session::verifyCsrf((string)$request->input('_csrf', ''))) {
            Session::flash('talk_publish_error', '会话已过期，请刷新页面后重试');
            $this->back();
        }

        $content = trim((string)$request->input('content', ''));
        $images = trim((string)$request->input('images', ''));
        $mood = trim((string)$request->input('mood', ''));
        $public = Toggle::fromInput($request->input('is_public', 0))->value;

        if ($content === '') {
            Session::flash('talk_publish_error', '滔客内容不能为空');
            $this->back();
        }

        $item = new Talk([
            'content' => $content,
            'images' => $images,
            'mood' => $mood,
            'music_id' => 0,
            'is_public' => $public,
            'post_type' => 'talk',
            'tweet_id' => '',
            'tweet_url' => '',
            'tweet_author_name' => '',
            'tweet_author_handle' => '',
            'tweet_author_avatar' => '',
            'tweet_author_verified' => 0,
            'tweet_posted_at' => null,
            'tweet_likes_count' => 0,
            'tweet_reposts_count' => 0,
            'tweet_data' => '',
            'published_at' => date('Y-m-d H:i:s'),
        ]);
        $item->save();

        Session::flash('talk_publish_success', '滔客已发布');
        Response::redirect('/talk#talk-' . $item->id);
    }

    private function back(): never
    {
        $ref = $_SERVER['HTTP_REFERER'] ?? '/talk';
        Response::redirect($ref);
    }

    /**
     * @param Talk[] $list
     */
    private function attachTalkComments(array $list): void
    {
        foreach ($list as $item) {
            $item->setRelation('comments', Comment::forTalk((int)$item->id));
        }
    }

}
