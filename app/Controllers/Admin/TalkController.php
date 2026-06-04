<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Helper;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Enums\Toggle;
use App\Models\Talk;

class TalkController
{
    public function index(): string
    {
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 20;
        $result = Talk::db()->fetchAll(
            "SELECT * FROM talk ORDER BY id DESC LIMIT {$perPage} OFFSET " . (($page-1)*$perPage)
        );
        $total = (int) Talk::db()->fetchColumn("SELECT COUNT(*) FROM talk");
        return View::render('talk.index', [
            'list' => array_map(fn($r) => new Talk($r), $result),
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'paginator' => Helper::paginate($page, $total, $perPage, '/admin/talk'),
            'csrf' => Session::csrfToken(),
            'pageTitle' => '滔客管理',
        ], 'layouts.admin');
    }

    public function create(): string
    {
        return View::render('talk.form', [
            'item' => null,
            'csrf' => Session::csrfToken(),
            'pageTitle' => '写滔客',
        ], 'layouts.admin');
    }

    public function edit($request, array $params): string
    {
        $id = (int)($params['id'] ?? 0);
        $item = Talk::find($id);
        if (!$item) {
            Session::flash('error', '滔客不存在');
            Response::redirect('/admin/talk');
        }
        return View::render('talk.form', [
            'item' => $item,
            'csrf' => Session::csrfToken(),
            'pageTitle' => '编辑滔客',
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
        $content = trim((string) $request->input('content', ''));
        $images  = trim((string) $request->input('images', ''));
        $music   = trim((string) $request->input('music', ''));
        $musicCover  = trim((string) $request->input('music_cover', ''));
        $musicTitle  = trim((string) $request->input('music_title', ''));
        $musicArtist = trim((string) $request->input('music_artist', ''));
        $mood    = trim((string) $request->input('mood', ''));
        $public  = Toggle::fromInput($request->input('is_public', 1))->value;

        if ($content === '') {
            Session::flash('error', '内容不能为空');
            Response::redirect($id ? "/admin/talk/{$id}/edit" : '/admin/talk/create');
        }
        $fields = [
            'content' => $content,
            'images'  => $images,
            'music'   => $music,
            'music_cover'  => $musicCover,
            'music_title'  => $musicTitle,
            'music_artist' => $musicArtist,
            'mood'    => $mood,
            'is_public' => $public,
        ];
        if ($id) {
            $item = Talk::find($id);
            if ($item) {
                $item->fill($fields);
                $item->save();
            }
        } else {
            $item = new Talk($fields);
            $item->save();
        }
        Session::flash('success', $id ? '滔客已更新' : '滔客已发布');
        Response::redirect('/admin/talk');
    }

    public function destroy(Request $request): never
    {
        $id = (int) $request->input('id', 0);
        if ($id) {
            Talk::db()->delete('talk', 'id = ?', [$id]);
        }
        Session::flash('success', '滔客已删除');
        Response::redirect('/admin/talk');
    }
}
