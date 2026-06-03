<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Helper;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Enums\Toggle;
use App\Models\Shuoshuo;

class ShuoshuoController
{
    public function index(): string
    {
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 20;
        $result = Shuoshuo::db()->fetchAll(
            "SELECT * FROM shuoshuo ORDER BY id DESC LIMIT {$perPage} OFFSET " . (($page-1)*$perPage)
        );
        $total = (int) Shuoshuo::db()->fetchColumn("SELECT COUNT(*) FROM shuoshuo");
        return View::render('shuoshuo.index', [
            'list' => array_map(fn($r) => new Shuoshuo($r), $result),
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'paginator' => Helper::paginate($page, $total, $perPage, '/admin/shuoshuo'),
            'csrf' => Session::csrfToken(),
            'pageTitle' => '说说管理',
        ], 'layouts.admin');
    }

    public function create(): string
    {
        return View::render('shuoshuo.form', [
            'item' => null,
            'csrf' => Session::csrfToken(),
            'pageTitle' => '写说说',
        ], 'layouts.admin');
    }

    public function edit($request, array $params): string
    {
        $id = (int)($params['id'] ?? 0);
        $item = Shuoshuo::find($id);
        if (!$item) {
            Session::flash('error', '说说不存在');
            Response::redirect('/admin/shuoshuo');
        }
        return View::render('shuoshuo.form', [
            'item' => $item,
            'csrf' => Session::csrfToken(),
            'pageTitle' => '编辑说说',
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
        $mood    = trim((string) $request->input('mood', ''));
        $public  = Toggle::fromInput($request->input('is_public', 1))->value;

        if ($content === '') {
            Session::flash('error', '内容不能为空');
            Response::redirect($id ? "/admin/shuoshuo/{$id}/edit" : '/admin/shuoshuo/create');
        }
        if ($id) {
            $item = Shuoshuo::find($id);
            if ($item) {
                $item->fill([
                    'content' => $content,
                    'images'  => $images,
                    'music'   => $music,
                    'mood'    => $mood,
                    'is_public' => $public,
                ]);
                $item->save();
            }
        } else {
            $item = new Shuoshuo([
                'content' => $content,
                'images'  => $images,
                'music'   => $music,
                'mood'    => $mood,
                'is_public' => $public,
            ]);
            $item->save();
        }
        Session::flash('success', $id ? '说说已更新' : '说说已发布');
        Response::redirect('/admin/shuoshuo');
    }

    public function destroy(Request $request): never
    {
        $id = (int) $request->input('id', 0);
        if ($id) {
            Shuoshuo::db()->delete('shuoshuo', 'id = ?', [$id]);
        }
        Session::flash('success', '说说已删除');
        Response::redirect('/admin/shuoshuo');
    }
}
