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
use App\Models\Talk;

class TalkController
{
    public function index(): string
    {
        $perPage = 10;
        $page = max(1, (int)($_GET['page'] ?? 1));
        ['items' => $list, 'total' => $total] = Talk::paginate($page, $perPage);
        foreach ($list as $item) {
            $item->setRelation('comments', Comment::forTalk((int)$item->id));
        }

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

    public function like(Request $request, array $params): never
    {
        $id = (int)($params['id'] ?? 0);
        $item = Talk::find((int)$id);
        if (!$item || (int)$item->is_public !== 1) {
            Response::json(['code' => 1, 'msg' => '滔客不存在'], 404);
        }

        $count = Talk::like((int)$id);
        Response::json(['code' => 0, 'likes' => $count]);
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
        $music = trim((string)$request->input('music', ''));
        $musicCover = trim((string)$request->input('music_cover', ''));
        $musicTitle = trim((string)$request->input('music_title', ''));
        $musicArtist = trim((string)$request->input('music_artist', ''));
        $mood = trim((string)$request->input('mood', ''));
        $public = Toggle::fromInput($request->input('is_public', 1))->value;

        if ($content === '') {
            Session::flash('talk_publish_error', '滔客内容不能为空');
            $this->back();
        }

        $item = new Talk([
            'content' => $content,
            'images' => $images,
            'music' => $music,
            'music_cover' => $musicCover,
            'music_title' => $musicTitle,
            'music_artist' => $musicArtist,
            'mood' => $mood,
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
}
