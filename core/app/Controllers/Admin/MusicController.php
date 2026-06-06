<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Helper;
use App\Core\ApiResponse;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Enums\Toggle;
use App\Models\Music;
use App\Services\MusicAssetCacheService;
use App\Services\MetingService;

class MusicController
{
    public function index(): string
    {
        Music::ensurePublishedAtColumn();
        $page = max(1, (int)($_GET['page'] ?? 1));
        $keyword = trim((string)($_GET['q'] ?? ''));
        $perPage = 20;
        $where = null;
        $params = [];
        if ($keyword !== '') {
            $where = '(title LIKE ? OR artist LIKE ? OR album LIKE ?)';
            $like = '%' . $keyword . '%';
            $params = [$like, $like, $like];
        }

        $result = Music::paginate($page, $perPage, 'sort ASC, id DESC', $where, $params);
        $baseUrl = $keyword !== '' ? Helper::buildUrl('/admin/music', ['q' => $keyword]) : '/admin/music';

        return View::render('music.index', [
            'list' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'perPage' => $perPage,
            'keyword' => $keyword,
            'paginator' => Helper::paginate($page, $result['total'], $perPage, $baseUrl),
            'csrf' => Session::csrfToken(),
            'pageTitle' => '音乐管理',
        ], 'layouts.admin');
    }

    public function create(): string
    {
        return View::render('music.form', [
            'item' => null,
            'csrf' => Session::csrfToken(),
            'pageTitle' => '添加本地音乐',
        ], 'layouts.admin');
    }

    public function online(): string
    {
        return View::render('music.online', [
            'csrf' => Session::csrfToken(),
            'pageTitle' => '添加线上音乐',
        ], 'layouts.admin');
    }

    public function edit(Request $request, array $params): string
    {
        $id = (int)($params['id'] ?? 0);
        $item = Music::find($id);
        if (!$item) {
            Session::flash('error', '音乐不存在');
            Response::redirect('/admin/music');
        }
        $linkedTalkCount = (int) Music::db()->fetchColumn('SELECT COUNT(*) FROM talk WHERE music_id = ?', [$id]);

        return View::render('music.form', [
            'item' => $item,
            'linkedTalkCount' => $linkedTalkCount,
            'csrf' => Session::csrfToken(),
            'pageTitle' => '编辑音乐',
        ], 'layouts.admin');
    }

    public function store(Request $request): never
    {
        $this->save($request, null);
    }

    public function update(Request $request, array $params): never
    {
        $this->save($request, (int)($params['id'] ?? 0));
    }

    public function metingSearch(Request $request): never
    {
        try {
            $provider = trim((string)$request->input('provider', 'netease'));
            $keyword = trim((string)$request->input('q', $request->input('keyword', '')));
            $page = max(1, (int)$request->input('page', 1));
            $limit = max(1, min(20, (int)$request->input('limit', 10)));
            ApiResponse::ok((new MetingService())->search($provider, $keyword, $page, $limit));
        } catch (\Throwable $e) {
            ApiResponse::error($e->getMessage(), 400, 'meting_search_failed');
        }
    }

    public function metingSong(Request $request): never
    {
        try {
            $provider = trim((string)$request->input('provider', 'netease'));
            $id = trim((string)$request->input('id', ''));
            ApiResponse::ok((new MetingService())->songPayload($provider, $id));
        } catch (\Throwable $e) {
            ApiResponse::error($e->getMessage(), 400, 'meting_song_failed');
        }
    }

    private function save(Request $request, ?int $id): never
    {
        Music::ensurePublishedAtColumn();
        $title = trim((string)$request->input('title', ''));
        $audioUrl = trim((string)$request->input('audio_url', ''));

        if ($title === '' || $audioUrl === '') {
            Session::flash('error', '歌名和音频 URL 不能为空');
            Response::redirect($id ? "/admin/music/{$id}/edit" : '/admin/music/create');
        }

        $now = date('Y-m-d H:i:s');
        $fields = [
            'title' => $title,
            'artist' => trim((string)$request->input('artist', '')),
            'album' => trim((string)$request->input('album', '')),
            'audio_url' => $audioUrl,
            'cover_url' => trim((string)$request->input('cover_url', '')),
            'lyrics' => Music::normalizeLyricsText((string)$request->input('lyrics', '')),
            'lyrics_url' => trim((string)$request->input('lyrics_url', '')),
            'description' => '',
            'mood' => '',
            'duration' => trim((string)$request->input('duration', '')),
            'sort' => (int)$request->input('sort', 0),
            'is_public' => Toggle::fromInput($request->input('is_public', 1))->value,
            'published_at' => Music::normalizePublishedAt(
                (string)$request->input('published_at', ''),
                $id ? (string)(Music::find((int)$id)?->published_at ?? '') : $now
            ),
            'updated_at' => $now,
        ];
        $cachedAssets = [];

        [$fields, $cachedAssets] = $this->cacheMetingAssets($request, $fields);

        if ($id) {
            $item = Music::find($id);
            if ($item) {
                $item->fill($fields);
                $item->save();
            }
        } else {
            $item = new Music($fields + [
                'play_count' => 0,
                'likes_count' => 0,
                'created_at' => $now,
            ]);
            $item->save();
        }

        $message = $id ? '音乐已更新' : '音乐已添加';
        if ($cachedAssets !== []) {
            $message .= '，' . implode('、', $cachedAssets) . '已保存到本地';
        }
        Session::flash('success', $message);
        Response::redirect('/admin/music');
    }

    private function cacheMetingAssets(Request $request, array $fields): array
    {
        $source = $this->metingSourceFromRequest($request, (string)($fields['lyrics_url'] ?? ''));
        if ($source === null) {
            return [$fields, []];
        }

        $cached = [];
        $assetCache = new MusicAssetCacheService();

        try {
            $coverUrl = $assetCache->cacheRemoteCover((string)($fields['cover_url'] ?? ''), 'meting-' . $source['provider'], $source['id']);
            if ($coverUrl) {
                $fields['cover_url'] = $coverUrl;
                $cached[] = '封面';
            }
        } catch (\Throwable) {
            // Keep the original remote cover URL if local caching fails.
        }

        try {
            $lyrics = (new MetingService())->lyricText($source['provider'], $source['id']);
            $lyricsUrl = $assetCache->cacheLyricsText($lyrics, 'meting-' . $source['provider'], $source['id']);
            if ($lyricsUrl) {
                $fields['lyrics_url'] = $lyricsUrl;
                $fields['lyrics'] = '';
                $cached[] = '歌词';
            }
        } catch (\Throwable) {
            // Keep the original lyric URL or text if local caching fails.
        }

        return [$fields, $cached];
    }

    private function metingSourceFromRequest(Request $request, string $lyricsUrl): ?array
    {
        $source = trim((string)$request->input('source', ''));
        $provider = trim((string)$request->input('source_provider', ''));
        $id = trim((string)$request->input('source_id', ''));
        if ($source === 'meting' && $provider !== '' && $id !== '') {
            return ['provider' => $provider, 'id' => $id];
        }

        $parts = parse_url($lyricsUrl);
        $path = (string)($parts['path'] ?? '');
        if ($path !== '/music/lyrics/meting') {
            return null;
        }

        $query = [];
        parse_str((string)($parts['query'] ?? ''), $query);
        $provider = trim((string)($query['provider'] ?? ''));
        $id = trim((string)($query['id'] ?? ''));
        return $provider !== '' && $id !== '' ? ['provider' => $provider, 'id' => $id] : null;
    }

    public function destroy(Request $request): never
    {
        $id = (int)$request->input('id', 0);
        if (!$id) {
            Session::flash('error', '音乐不存在');
            Response::redirect('/admin/music');
        }

        $db = Music::db();
        $music = Music::find($id);
        if (!$music) {
            Session::flash('error', '音乐不存在');
            Response::redirect('/admin/music');
        }

        $deleteTalks = (string)$request->input('delete_talks', '') === '1';
        $talkRows = $db->fetchAll('SELECT id FROM talk WHERE music_id = ?', [$id]);
        $talkIds = array_map(static fn(array $row): int => (int)$row['id'], $talkRows);

        try {
            $db->beginTransaction();

            if ($talkIds !== []) {
                if ($deleteTalks) {
                    $placeholders = implode(',', array_fill(0, count($talkIds), '?'));
                    $db->delete('comments', 'talk_id IN (' . $placeholders . ')', $talkIds);
                    $db->delete('talk', 'id IN (' . $placeholders . ')', $talkIds);
                } else {
                    $db->query("UPDATE talk SET music_id = 0, post_type = 'talk' WHERE music_id = ?", [$id]);
                }
            }

            $db->delete('comments', 'music_id = ?', [$id]);
            $db->delete('music', 'id = ?', [$id]);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            Session::flash('error', '删除失败，请稍后重试');
            Response::redirect("/admin/music/{$id}/edit");
        }

        if ($talkIds !== [] && $deleteTalks) {
            Session::flash('success', '音乐已删除，相关音乐说说已一起删除');
        } elseif ($talkIds !== []) {
            Session::flash('success', '音乐已删除，相关说说已保留并取消音乐关联');
        } else {
            Session::flash('success', '音乐已删除');
        }
        Response::redirect('/admin/music');
    }
}
