<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Helper;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Enums\Toggle;
use App\Models\Category;
use App\Models\Post;

class CategoryController
{
    public function index(): never
    {
        Response::redirect('/admin/posts');
    }

    public function save(Request $request): never
    {
        $id   = (int) $request->input('id', 0);
        $name = trim((string) $request->input('name', ''));
        $slug = trim((string) $request->input('slug', ''));
        $desc = trim((string) $request->input('description', ''));
        $sort = (int) $request->input('sort', 0);
        $icon = trim((string) $request->input('icon', ''));
        // 只保留合法的 fontawesome 类名字符,防注入
        if ($icon !== '' && !preg_match('/^[a-zA-Z0-9 _-]+$/', $icon)) {
            $icon = '';
        }
        // 颜色:空 = 自动(按分类取色);0-5 = 手动指定
        $colorRaw = $request->input('color', '');
        $color = ($colorRaw === '' || $colorRaw === null) ? null : max(0, min(5, (int) $colorRaw));

        // 「在菜单栏显示」开关已移到列表内联(toggleNav),编辑表单不再提交该字段:
        // 新建默认显示;编辑时保留原值(下方 fill 不含 show_in_nav)。

        if ($name === '') {
            Session::flash('error', '分类名不能为空');
            Response::redirect('/admin/posts');
        }
        if ($slug === '') {
            $slug = Helper::slugify($name);
        }
        // 唯一
        $base = $slug;
        $i = 1;
        while (true) {
            $existing = Category::findBySlug($slug);
            if (!$existing || ($id && (int)$existing->id === $id)) break;
            $slug = $base . '-' . $i++;
        }

        if ($id) {
            $cat = Category::find($id);
            if (!$cat) {
                Session::flash('error', '分类不存在');
                Response::redirect('/admin/posts');
            }
            $cat->fill(['name' => $name, 'slug' => $slug, 'description' => $desc, 'sort' => $sort, 'icon' => $icon, 'color' => $color]);
            $cat->save();
        } else {
            $cat = new Category([
                'name' => $name, 'slug' => $slug, 'description' => $desc, 'sort' => $sort, 'icon' => $icon, 'color' => $color, 'show_in_nav' => 1,
            ]);
            $cat->save();
        }
        Session::flash('success', $id ? '分类已更新' : '分类已创建');
        Response::redirect('/admin/posts');
    }

    /**
     * 快速切换「在菜单栏显示」。
     */
    public function toggleNav(Request $request): never
    {
        $id   = (int) $request->input('id', 0);
        $show = Toggle::fromInput($request->input('show_in_nav', 0))->value;
        $cat = $id > 0 ? Category::find($id) : null;

        if (!$cat) {
            if ($request->isAjax()) {
                Response::json(['code' => 1, 'msg' => '分类不存在'], 404);
            }
            Session::flash('error', '分类不存在');
            Response::redirect('/admin/posts');
        }

        Category::db()->update('categories', ['show_in_nav' => $show], 'id = :id', [':id' => $id]);
        $message = $show ? '已在菜单栏显示' : '已从菜单栏隐藏';

        if ($request->isAjax()) {
            Response::json([
                'code' => 0,
                'msg' => $message,
                'data' => [
                    'id' => $id,
                    'show_in_nav' => $show,
                ],
            ]);
        }

        Session::flash('success', $message);
        Response::redirect('/admin/posts');
    }

    public function destroy(Request $request): never
    {
        $id = (int) $request->input('id', 0);
        if ($id) {
            // 解除关联
            Post::db()->update('posts', ['category_id' => 0], 'category_id = :cid', [':cid' => $id]);
            Category::db()->delete('categories', 'id = ?', [$id]);
        }
        Session::flash('success', '分类已删除');
        Response::redirect('/admin/posts');
    }
}
