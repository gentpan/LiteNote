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
use App\Models\Music;
use App\Models\Talk;
use App\Support\TweetCardItem;
use App\Services\TweetFetchService;

class TalkController
{
    public function index(): string
    {
        $perPage = 10;
        $page = max(1, (int)($_GET['page'] ?? 1));
        ['items' => $list, 'total' => $total] = Talk::paginate($page, $perPage);
        $this->attachTalkRelations($list);

        return View::render('front.talk.index', [
            'list' => $list,
            'total' => $total,
            'page'  => $page,
            'perPage' => $perPage,
            'paginator' => Helper::loadMore($page, $total, $perPage, Helper::url('/talk')),
            'musicOptions' => Music::publicOptions(80),
            'pageTitle' => '滔客',
            'activeNav' => 'talk',
        ]);
    }

    public function x(): string
    {
        $perPage = 24;
        $page = max(1, (int)($_GET['page'] ?? 1));
        ['items' => $items, 'total' => $total] = Activity::paginate($page, $perPage, ['source' => 'x'], true);
        $list = array_map(static fn(Activity $activity): TweetCardItem => new TweetCardItem($activity), $items);

        return View::render('front.x.index', [
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'paginator' => Helper::loadMore($page, $total, $perPage, Helper::url('/x')),
            'pageTitle' => '𝕏',
            'activeNav' => 'x',
        ]);
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

        $postType = (string)$request->input('post_type', 'talk') === 'tweet' ? 'tweet' : 'talk';
        $content = $postType === 'tweet' ? '' : trim((string)$request->input('content', ''));
        $images = $postType === 'tweet' ? '' : trim((string)$request->input('images', ''));
        $mood = trim((string)$request->input('mood', ''));
        $musicId = $postType === 'tweet' ? 0 : $this->normalizeMusicId((int)$request->input('music_id', 0));
        $public = Toggle::fromInput($request->input('is_public', 0))->value;
        $tweetUrl = trim((string)$request->input('tweet_url', ''));
        $tweetId = Talk::extractTweetId($tweetUrl);
        $tweetData = [];

        if ($postType === 'tweet' && $tweetUrl === '' && $tweetId !== '') {
            $tweetUrl = 'https://x.com/i/web/status/' . $tweetId;
        }

        if ($postType === 'tweet' && $tweetUrl === '' && $tweetId === '') {
            Session::flash('talk_publish_error', '请填写 X 链接');
            $this->back();
        }
        if ($postType !== 'tweet' && $content === '') {
            Session::flash('talk_publish_error', '滔客内容不能为空');
            $this->back();
        }
        if ($postType === 'tweet') {
            Talk::ensureTweetDataColumn();
            try {
                $tweetData = (new TweetFetchService())->fetch($tweetUrl);
                $tweetId = (string)($tweetData['id'] ?? $tweetId);
                $tweetUrl = (string)($tweetData['url'] ?? $tweetUrl);
                $content = trim((string)($tweetData['text'] ?? ''));
                $images = implode(',', array_slice($tweetData['images'] ?? [], 0, 4));
            } catch (\Throwable $e) {
                Session::flash('talk_publish_error', 'X 链接获取失败：' . $e->getMessage());
                $this->back();
            }
            if ($content === '') {
                $content = 'X 原帖 ' . ($tweetUrl ?: $tweetId);
            }
        }

        $item = new Talk([
            'content' => $content,
            'images' => $images,
            'mood' => $mood,
            'music_id' => $musicId,
            'is_public' => $public,
            'post_type' => $postType,
            'tweet_id' => $postType === 'tweet' ? $tweetId : '',
            'tweet_url' => $postType === 'tweet' ? $tweetUrl : '',
            'tweet_author_name' => $postType === 'tweet' ? (string)($tweetData['author_name'] ?? '') : '',
            'tweet_author_handle' => $postType === 'tweet' ? (string)($tweetData['author_handle'] ?? '') : '',
            'tweet_author_avatar' => $postType === 'tweet' ? (string)($tweetData['author_avatar'] ?? '') : '',
            'tweet_author_verified' => $postType === 'tweet' && !empty($tweetData['author_verified']) ? 1 : 0,
            'tweet_posted_at' => $postType === 'tweet' ? ($tweetData['posted_at'] ?? null) : null,
            'tweet_likes_count' => $postType === 'tweet' ? (int)($tweetData['likes_count'] ?? 0) : 0,
            'tweet_reposts_count' => $postType === 'tweet' ? (int)($tweetData['reposts_count'] ?? 0) : 0,
            'tweet_data' => $postType === 'tweet' ? json_encode($tweetData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '',
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
    private function attachTalkRelations(array $list): void
    {
        $musicMap = Music::mapByIds(array_map(static fn(Talk $item): int => (int)($item->music_id ?? 0), $list));
        foreach ($list as $item) {
            $music = $musicMap[(int)($item->music_id ?? 0)] ?? null;
            if ($music && (int)$music->is_public === Toggle::On->value) {
                $item->setRelation('music', $music);
                $item->setRelation('comments', Comment::forMusic((int)$music->id));
                continue;
            }
            $item->setRelation('comments', Comment::forTalk((int)$item->id));
        }
    }

    private function normalizeMusicId(int $musicId): int
    {
        if ($musicId <= 0) {
            return 0;
        }
        $music = Music::find($musicId);
        if (!$music || (int)$music->is_public !== Toggle::On->value) {
            return 0;
        }
        return (int)$music->id;
    }
}
