<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Helper;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Enums\PostStatus;
use App\Models\Category;
use App\Models\Post;

class CategoryController
{
    public function index(): string
    {
        $cats = Category::allEnabled();
        $counts = [];
        foreach ($cats as $c) {
            $counts[$c->id] = Post::count(['category_id' => $c->id, 'status' => PostStatus::Published->value]);
        }
        return View::render('category.index', [
            'categories' => $cats,
            'counts'     => $counts,
            'csrf'       => Session::csrfToken(),
            'pageTitle'  => '分类管理',
        ], 'layouts.admin');
    }

    public function save(Request $request): never
    {
        $id   = (int) $request->input('id', 0);
        $name = trim((string) $request->input('name', ''));
        $slug = trim((string) $request->input('slug', ''));
        $desc = trim((string) $request->input('description', ''));
        $sort = (int) $request->input('sort', 0);

        if ($name === '') {
            Session::flash('error', '分类名不能为空');
            Response::redirect('/admin/categories');
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
                Response::redirect('/admin/categories');
            }
            $cat->fill(['name' => $name, 'slug' => $slug, 'description' => $desc, 'sort' => $sort]);
            $cat->save();
        } else {
            $cat = new Category([
                'name' => $name, 'slug' => $slug, 'description' => $desc, 'sort' => $sort,
            ]);
            $cat->save();
        }
        Session::flash('success', $id ? '分类已更新' : '分类已创建');
        Response::redirect('/admin/categories');
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
        Response::redirect('/admin/categories');
    }
}
