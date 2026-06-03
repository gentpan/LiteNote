<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Helper;
use App\Core\Markdown;
use App\Core\Request;
use App\Core\Session;
use App\Core\Validator;
use App\Core\View;
use App\Enums\PostStatus;
use App\Enums\Toggle;
use App\Models\Category;
use App\Models\Post;
use App\Traits\HasFlashRedirect;
use App\Traits\HasSlug;

/**
 * 文章管理（改进版）
 * 变更点：
 * 1. 使用 HasSlug + HasFlashRedirect trait 消除重复代码。
 * 2. 使用 Validator 统一校验逻辑。
 * 3. save() 方法提取为单一入口，store/update 仅负责解析 ID。
 * 4. 批量操作增加参数校验，防止空数组导致 SQL 语法错误。
 * 5. 删除时同步清理关联表（事务化）。
 */
class PostController
{
    use HasSlug, HasFlashRedirect;

    public function index(): string
    {
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = (int) \App\Core\Config::get('pagination.admin_per_page', 20);
        $keyword = trim((string)($_GET['q'] ?? ''));
        $status = $_GET['status'] ?? '';

        $where = [];
        $params = [];
        if ($keyword !== '') {
            $where[] = '(title LIKE ? OR summary LIKE ?)';
            $params[] = "%{$keyword}%";
            $params[] = "%{$keyword}%";
        }
        if ($status !== '') {
            $where[] = 'status = ?';
            $params[] = $status;
        }
        $whereSql = $where ? implode(' AND ', $where) : null;

        $result = Post::paginate($page, $perPage, 'id DESC', $whereSql, $params);

        return View::render('post.index', [
            'posts'     => $result['items'],
            'total'     => $result['total'],
            'page'      => $page,
            'perPage'   => $perPage,
            'paginator' => Helper::paginate($page, $result['total'], $perPage, '/admin/posts'),
            'keyword'   => $keyword,
            'status'    => $status,
            'csrf'      => Session::csrfToken(),
            'pageTitle' => '文章管理',
        ], 'layouts.admin');
    }

    public function create(): string
    {
        return $this->form(null);
    }

    public function edit(Request $request, array $params): string
    {
        $id = (int)($params['id'] ?? 0);
        $post = Post::find($id);
        if (!$post) {
            $this->flashError('文章不存在');
            $this->redirect('/admin/posts');
        }
        return $this->form($post);
    }

    private function form(?Post $post): string
    {
        return View::render('post.form', [
            'post'       => $post,
            'categories' => Category::allEnabled(),
            'csrf'       => Session::csrfToken(),
            'pageTitle'  => $post ? '编辑文章' : '写文章',
        ], 'layouts.admin');
    }

    public function store(Request $request): never
    {
        $this->persist($request, null);
    }

    public function update(Request $request, array $params): never
    {
        $this->persist($request, (int)($params['id'] ?? 0));
    }

    private function persist(Request $request, ?int $id): never
    {
        $data = [
            'title'    => $request->input('title', ''),
            'slug'     => $request->input('slug', ''),
            'summary'  => $request->input('summary', ''),
            'content'  => $request->input('content', ''),
            'markdown' => $request->input('markdown_content', ''),
            'cover'    => $request->input('cover', ''),
            'category_id' => $request->input('category_id', 0),
            'status'   => $request->input('status', PostStatus::Published->value),
            'is_top'   => $request->input('is_top', 0),
            'is_recommend' => $request->input('is_recommend', 0),
            'tags'     => $request->input('tags', ''),
        ];

        $validator = Validator::make($data, [
            'title'   => 'required|string|min:1|max:200',
            'content' => 'required_if:markdown,',
            'status'  => 'in:' . implode(',', PostStatus::values()),
        ]);

        if (!$validator->validate()) {
            $this->flashError($validator->firstError() ?? '校验失败');
            $this->redirect($id ? "/admin/posts/{$id}/edit" : '/admin/posts/create');
        }

        // Markdown 优先
        $content = trim((string)$data['content']);
        $markdown = trim((string)$data['markdown']);
        if ($markdown !== '') {
            $content = Markdown::parse($markdown);
        }
        if ($content === '' && $markdown === '') {
            $this->flashError('内容和 Markdown 至少填一个');
            $this->redirect($id ? "/admin/posts/{$id}/edit" : '/admin/posts/create');
        }

        $slug = Post::resolveSlug((string)$data['slug'], (string)$data['title'], $id);
        $now = date('Y-m-d H:i:s');

        if ($id) {
            $post = Post::find($id);
            if (!$post) {
                $this->flashError('文章不存在');
                $this->redirect('/admin/posts');
            }
            $post->fill([
                'title'            => trim((string)$data['title']),
                'slug'             => $slug,
                'summary'          => trim((string)$data['summary']),
                'content'          => $content,
                'markdown_content' => $markdown,
                'cover'            => trim((string)$data['cover']),
                'category_id'      => (int)$data['category_id'],
                'is_top'           => Toggle::fromInput($data['is_top'])->value,
                'is_recommend'     => Toggle::fromInput($data['is_recommend'])->value,
                'status'           => $data['status'],
                'updated_at'       => $now,
            ]);
            $post->save();
        } else {
            $post = new Post([
                'title'            => trim((string)$data['title']),
                'slug'             => $slug,
                'summary'          => trim((string)$data['summary']),
                'content'          => $content,
                'markdown_content' => $markdown,
                'cover'            => trim((string)$data['cover']),
                'category_id'      => (int)$data['category_id'],
                'user_id'          => Session::get('admin_user.id', 1),
                'is_top'           => Toggle::fromInput($data['is_top'])->value,
                'is_recommend'     => Toggle::fromInput($data['is_recommend'])->value,
                'status'           => $data['status'],
                'published_at'     => $now,
                'created_at'       => $now,
                'updated_at'       => $now,
            ]);
            $post->save();
        }

        $this->flashSuccess($id ? '文章已更新' : '文章已发布');
        $this->redirect('/admin/posts');
    }

    public function destroy(Request $request, array $params): never
    {
        $id = (int)($params['id'] ?? 0);
        if ($id) {
            $db = Post::db();
            try {
                $db->beginTransaction();
                $db->delete('comments', 'post_id = ?', [$id]);
                $db->delete('posts', 'id = ?', [$id]);
                $db->commit();
            } catch (\Throwable $e) {
                $db->rollBack();
                $this->flashError('删除失败: ' . $e->getMessage());
                $this->redirect('/admin/posts');
            }
        }
        $this->flashSuccess('文章已删除');
        $this->redirect('/admin/posts');
    }

    public function bulk(Request $request): never
    {
        $action = $request->input('bulk_action', '');
        $ids = (array) $request->input('ids', []);
        $ids = array_filter(array_map('intval', $ids));
        if (empty($ids)) {
            $this->flashError('请选择文章');
            $this->redirect('/admin/posts');
        }

        // 注意:这里的 'publish' / 'draft' 是**批量操作的动作名**(动词),
        // 不是 PostStatus::Published / Draft 的值。两者同名是历史遗留。
        // 修改时不要和 App\Enums\PostStatus 混为一谈。
        $allowedActions = ['delete', 'publish', 'draft', 'top', 'untop'];
        if (!in_array($action, $allowedActions, true)) {
            $this->flashError('非法操作');
            $this->redirect('/admin/posts');
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $db = Post::db();

        switch ($action) {
            case 'delete':
                $db->beginTransaction();
                $db->query("DELETE FROM comments WHERE post_id IN ({$placeholders})", $ids);
                $db->query("DELETE FROM posts WHERE id IN ({$placeholders})", $ids);
                $db->commit();
                $this->flashSuccess('已删除 ' . count($ids) . ' 篇文章');
                break;
            case 'publish':
                $db->query(
                    "UPDATE posts SET status='" . PostStatus::Published->value . "' WHERE id IN ({$placeholders})",
                    $ids
                );
                $this->flashSuccess('已发布 ' . count($ids) . ' 篇文章');
                break;
            case 'draft':
                $db->query(
                    "UPDATE posts SET status='" . PostStatus::Draft->value . "' WHERE id IN ({$placeholders})",
                    $ids
                );
                $this->flashSuccess('已转为草稿');
                break;
            case 'top':
                $db->query(
                    "UPDATE posts SET is_top=" . Toggle::On->value . " WHERE id IN ({$placeholders})",
                    $ids
                );
                $this->flashSuccess('已置顶');
                break;
            case 'untop':
                $db->query(
                    "UPDATE posts SET is_top=" . Toggle::Off->value . " WHERE id IN ({$placeholders})",
                    $ids
                );
                $this->flashSuccess('已取消置顶');
                break;
        }

        $this->redirect('/admin/posts');
    }
}
