<?php
declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\Helper;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\Comment;
use App\Models\Music;
use App\Services\MetingService;

class MusicController
{
    public function index(): string
    {
        $perPage = 50;
        $page = max(1, (int)($_GET['page'] ?? 1));
        ['items' => $list, 'total' => $total] = Music::paginate($page, $perPage, 'published_at DESC, sort ASC, id DESC');
        foreach ($list as $item) {
            $item->setRelation('comments', Comment::forMusic((int)$item->id));
        }

        return View::render('front.music.index', [
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'paginator' => Helper::paginate($page, $total, $perPage, '/music'),
            'pageTitle' => '音乐',
            'activeNav' => 'music',
        ], 'layouts.front');
    }

    public function like(Request $request, array $params): never
    {
        $id = (int)($params['id'] ?? 0);
        $item = Music::find($id);
        if (!$item) {
            Response::json(['code' => 1, 'msg' => '音乐不存在'], 404);
        }

        $liked = Session::get('liked_music', []);
        $liked = is_array($liked) ? $liked : [];
        if (!empty($liked[$id])) {
            Response::json([
                'code' => 2,
                'msg' => '已经喜欢过这首音乐了',
                'likes' => (int)($item->likes_count ?? 0),
                'liked' => true,
            ]);
        }

        $count = Music::like($id);
        $liked[$id] = 1;
        Session::set('liked_music', $liked);
        Response::json(['code' => 0, 'likes' => $count, 'liked' => true]);
    }

    public function play(Request $request, array $params): never
    {
        $id = (int)($params['id'] ?? 0);
        $item = Music::find($id);
        if (!$item) {
            Response::json(['code' => 1, 'msg' => '音乐不存在'], 404);
        }

        $count = Music::recordPlay($id);
        Response::json(['code' => 0, 'plays' => $count]);
    }

    public function metingLyrics(Request $request): never
    {
        try {
            $provider = trim((string)$request->input('provider', 'netease'));
            $id = trim((string)$request->input('id', ''));
            $lyrics = (new MetingService())->lyricText($provider, $id);
            Response::text($lyrics, 200, 'text/plain; charset=utf-8');
        } catch (\Throwable) {
            Response::text('', 404, 'text/plain; charset=utf-8');
        }
    }
}
