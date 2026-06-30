<?php
declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\Helper;
use App\Core\FrontCsrf;
use App\Core\Http;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Enums\CommentStatus;
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
        $this->attachMusicComments($list);

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

    /**
     * @param Music[] $list
     */
    private function attachMusicComments(array $list, int $limitPerMusic = 20): void
    {
        $ids = array_map(static fn($item): int => (int)$item->id, $list);
        $commentsByMusic = Comment::recentGroupedByTarget('music_id', $ids, $limitPerMusic);
        foreach ($list as $item) {
            $comments = $commentsByMusic[(int)$item->id] ?? [];
            $item->comments_count = (int)($item->comments_count ?? count($comments));
            $item->setRelation('comments', $comments);
        }
    }

    public function like(Request $request, array $params): never
    {
        FrontCsrf::verify($request);
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
        FrontCsrf::verify($request);

        $id = (int)($params['id'] ?? 0);
        $item = Music::find($id);
        if (!$item) {
            Response::json(['code' => 1, 'msg' => '音乐不存在'], 404);
        }

        $count = Music::trackPlay($id);
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

    public function fetchLyrics(Request $request): never
    {
        $url = trim((string)$request->input('url', ''));
        if (!$this->isSafeRemoteLyricsUrl($url)) {
            Response::text('', 400, 'text/plain; charset=utf-8');
        }

        try {
            $response = Http::request('GET', $url, [
                'headers' => ['Accept: text/plain,*/*'],
                'timeout' => 8,
                'connect_timeout' => 4,
                'follow' => false,
            ]);
            $body = (string)($response['body'] ?? '');
            if (empty($response['ok']) || $body === '') {
                Response::text('', 404, 'text/plain; charset=utf-8');
            }
            if (strlen($body) > 262144) {
                Response::text('', 413, 'text/plain; charset=utf-8');
            }
            Response::text($body, 200, 'text/plain; charset=utf-8');
        } catch (\Throwable) {
            Response::text('', 404, 'text/plain; charset=utf-8');
        }
    }

    private function isSafeRemoteLyricsUrl(string $url): bool
    {
        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        $host = strtolower(trim((string)parse_url($url, PHP_URL_HOST), '[]'));
        if ($host === '' || in_array($host, ['localhost', '127.0.0.1', '0.0.0.0'], true) || str_ends_with($host, '.local')) {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
        }

        $records = @gethostbynamel($host);
        if (!is_array($records) || $records === []) {
            return false;
        }

        foreach ($records as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return false;
            }
        }
        return true;
    }
}
