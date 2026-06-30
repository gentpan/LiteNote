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
use App\Services\PublicCacheService;
use App\Services\SearchIndexService;

class PageController
{
    public function index(): string
    {
        Page::ensureSystemPages();
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
        Page::ensureSystemPages();
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
            'editorMarkdown' => $this->markdownForEditor($page),
            'pageTitle' => $page ? '编辑页面' : '新建页面',
        ], 'layouts.admin');
    }

    private function markdownForEditor(?Page $page): string
    {
        if (!$page) {
            return '';
        }

        $markdown = trim((string)($page->markdown_content ?? ''));
        if ($markdown !== '') {
            return $markdown;
        }

        $content = trim((string)($page->content ?? ''));
        return $content === '' ? '' : $this->htmlToMarkdownFallback($content);
    }

    private function htmlToMarkdownFallback(string $html): string
    {
        $toText = static function (string $value): string {
            return trim(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        };

        $text = $html;
        $text = preg_replace_callback(
            '/<pre><code[^>]*>(.*?)<\/code><\/pre>/is',
            static fn(array $m): string => "\n```\n" . trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8')) . "\n```\n",
            $text
        ) ?? $text;
        $text = preg_replace_callback(
            '/<h([1-6])[^>]*>(.*?)<\/h\1>/is',
            static fn(array $m): string => "\n" . str_repeat('#', (int)$m[1]) . ' ' . $toText($m[2]) . "\n\n",
            $text
        ) ?? $text;
        $text = preg_replace_callback(
            '/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i',
            static function (array $m): string {
                $alt = '';
                if (preg_match('/alt=["\']([^"\']*)["\']/i', $m[0], $altMatch)) {
                    $alt = html_entity_decode($altMatch[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                }
                return '![' . $alt . '](' . html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8') . ')';
            },
            $text
        ) ?? $text;
        $text = preg_replace_callback(
            '/<a[^>]+href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is',
            static fn(array $m): string => '[' . $toText($m[2]) . '](' . html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8') . ')',
            $text
        ) ?? $text;
        $text = preg_replace('/<strong[^>]*>(.*?)<\/strong>/is', '**$1**', $text) ?? $text;
        $text = preg_replace('/<em[^>]*>(.*?)<\/em>/is', '*$1*', $text) ?? $text;
        $text = preg_replace('/<blockquote[^>]*>(.*?)<\/blockquote>/is', "\n> $1\n", $text) ?? $text;
        $text = preg_replace('/<li[^>]*>(.*?)<\/li>/is', "\n- $1", $text) ?? $text;
        $text = preg_replace('/<br\s*\/?>/i', "\n", $text) ?? $text;
        $text = preg_replace('/<\/p>/i', "\n\n", $text) ?? $text;
        $text = preg_replace('/<p[^>]*>/i', '', $text) ?? $text;
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/[ \t]+\n/", "\n", $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text);
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
        Page::ensureSystemPages();
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

        $existingPage = $id ? Page::find($id) : null;
        if ($id && !$existingPage) {
            Session::flash('error', '页面不存在');
            Response::redirect('/admin/pages');
        }

        if ($existingPage && $existingPage->isSystem()) {
            $existingPage->fill([
                'title' => $title,
                'is_nav' => $isNav,
                'sort' => $sort,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $existingPage->save();
            Session::flash('success', '系统页面已更新');
            Response::redirect('/admin/pages');
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
        if (isset($p) && $p instanceof Page) {
            SearchIndexService::syncPage($p);
        }
        PublicCacheService::forgetAll();
        Response::redirect('/admin/pages');
    }

    /**
     * 快速切换「在菜单栏显示」。
     */
    public function toggleNav(Request $request): never
    {
        Page::ensureSystemPages();
        $id = (int) $request->input('id', 0);
        $show = Toggle::fromInput($request->input('is_nav', 0))->value;
        $page = $id > 0 ? Page::find($id) : null;

        if (!$page) {
            if ($request->isAjax()) {
                Response::json(['code' => 1, 'msg' => '页面不存在'], 404);
            }
            Session::flash('error', '页面不存在');
            Response::redirect('/admin/pages');
        }

        Page::db()->update(
            'pages',
            ['is_nav' => $show, 'updated_at' => date('Y-m-d H:i:s')],
            'id = :id',
            [':id' => $id]
        );
        $message = $show ? '已在菜单栏显示' : '已从菜单栏隐藏';

        if ($request->isAjax()) {
            Response::json([
                'code' => 0,
                'msg' => $message,
                'data' => [
                    'id' => $id,
                    'is_nav' => $show,
                    'show_in_nav' => $show,
                ],
            ]);
        }

        Session::flash('success', $message);
        Response::redirect('/admin/pages');
    }

    public function destroy(Request $request): never
    {
        Page::ensureSystemPages();
        $id = (int) $request->input('id', 0);
        if ($id) {
            $page = Page::find($id);
            if ($page && $page->isSystem()) {
                Session::flash('error', '系统页面不能删除，只能关闭菜单显示');
                Response::redirect('/admin/pages');
            }
            Page::db()->delete('pages', 'id = ?', [$id]);
            SearchIndexService::remove('page', $id);
        }
        PublicCacheService::forgetAll();
        Session::flash('success', '页面已删除');
        Response::redirect('/admin/pages');
    }
}
