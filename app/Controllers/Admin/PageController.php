<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Helper;
use App\Core\Markdown;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Enums\Toggle;
use App\Models\Page;

class PageController
{
    public function index(): string
    {
        $pages = Page::query("SELECT * FROM pages ORDER BY sort ASC, id ASC");
        return View::render('page.index', [
            'pages' => $pages,
            'csrf' => Session::csrfToken(),
            'pageTitle' => '页面管理',
        ], 'layouts.admin');
    }

    public function create(): string
    {
        return $this->form(null);
    }

    public function edit($request, array $params): string
    {
        $id = (int)($params['id'] ?? 0);
        $page = Page::find($id);
        if (!$page) {
            Session::flash('error', '页面不存在');
            Response::redirect('/admin/pages');
        }
        return $this->form($page);
    }

    private function form(?Page $page): string
    {
        return View::render('page.form', [
            'page' => $page,
            'csrf' => Session::csrfToken(),
            'pageTitle' => $page ? '编辑页面' : '新建页面',
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
        $title   = trim((string) $request->input('title', ''));
        $slug    = trim((string) $request->input('slug', ''));
        $content = (string) $request->input('content', '');
        $md      = (string) $request->input('markdown_content', '');
        $isNav   = Toggle::fromInput($request->input('is_nav', 0))->value;
        $sort    = (int) $request->input('sort', 0);

        if ($title === '') {
            Session::flash('error', '标题不能为空');
            Response::redirect($id ? "/admin/pages/{$id}/edit" : '/admin/pages/create');
        }
        if ($slug === '') $slug = Helper::slugify($title);
        $base = $slug;
        $i = 1;
        while (true) {
            $existing = Page::findBySlug($slug);
            if (!$existing || ($id && (int)$existing->id === $id)) break;
            $slug = $base . '-' . $i++;
        }
        if ($md !== '') {
            $content = Markdown::parse($md);
        }
        $now = date('Y-m-d H:i:s');
        if ($id) {
            $p = Page::find($id);
            if ($p) {
                $p->fill([
                    'title'   => $title,
                    'slug'    => $slug,
                    'content' => $content,
                    'markdown_content' => $md,
                    'is_nav'  => $isNav,
                    'sort'    => $sort,
                    'updated_at' => $now,
                ]);
                $p->save();
            }
        } else {
            $p = new Page([
                'title'   => $title,
                'slug'    => $slug,
                'content' => $content,
                'markdown_content' => $md,
                'is_nav'  => $isNav,
                'sort'    => $sort,
            ]);
            $p->save();
        }
        Session::flash('success', $id ? '页面已更新' : '页面已创建');
        Response::redirect('/admin/pages');
    }

    public function destroy(Request $request): never
    {
        $id = (int) $request->input('id', 0);
        if ($id) {
            Page::db()->delete('pages', 'id = ?', [$id]);
        }
        Session::flash('success', '页面已删除');
        Response::redirect('/admin/pages');
    }
}
