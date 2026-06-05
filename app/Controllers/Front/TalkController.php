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
use App\Models\Music;
use App\Models\Talk;

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
        $musicId = $this->normalizeMusicId((int)$request->input('music_id', 0));
        $public = Toggle::fromInput($request->input('is_public', 0))->value;

        if ($content === '') {
            Session::flash('talk_publish_error', '滔客内容不能为空');
            $this->back();
        }

        $item = new Talk([
            'content' => $content,
            'images' => $images,
            'mood' => $mood,
            'music_id' => $musicId,
            'is_public' => $public,
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
