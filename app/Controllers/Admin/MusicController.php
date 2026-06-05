<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Helper;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Enums\Toggle;
use App\Models\Music;

class MusicController
{
    public function index(): string
    {
        Music::ensurePublishedAtColumn();
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 20;
        $result = Music::paginate($page, $perPage, 'published_at DESC, sort ASC, id DESC');

        return View::render('music.index', [
            'list' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'perPage' => $perPage,
            'paginator' => Helper::paginate($page, $result['total'], $perPage, '/admin/music'),
            'csrf' => Session::csrfToken(),
            'pageTitle' => '音乐管理',
        ], 'layouts.admin');
    }

    public function create(): string
    {
        return View::render('music.form', [
            'item' => null,
            'csrf' => Session::csrfToken(),
            'pageTitle' => '添加音乐',
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

        return View::render('music.form', [
            'item' => $item,
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
            'description' => trim((string)$request->input('description', '')),
            'mood' => trim((string)$request->input('mood', '')),
            'duration' => trim((string)$request->input('duration', '')),
            'sort' => (int)$request->input('sort', 0),
            'is_public' => Toggle::fromInput($request->input('is_public', 1))->value,
            'published_at' => Music::normalizePublishedAt(
                (string)$request->input('published_at', ''),
                $id ? (string)(Music::find((int)$id)?->published_at ?? '') : $now
            ),
            'updated_at' => $now,
        ];

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

        Session::flash('success', $id ? '音乐已更新' : '音乐已添加');
        Response::redirect('/admin/music');
    }

    public function destroy(Request $request): never
    {
        $id = (int)$request->input('id', 0);
        if ($id) {
            Music::db()->delete('music', 'id = ?', [$id]);
        }
        Session::flash('success', '音乐已删除');
        Response::redirect('/admin/music');
    }
}
