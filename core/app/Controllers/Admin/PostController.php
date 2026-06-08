<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Helper;
use App\Core\Markdown;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Core\View;
use App\Enums\PostStatus;
use App\Enums\Toggle;
use App\Models\Category;
use App\Models\Post;
use App\Services\AiSummaryService;
use App\Services\ActivityService;
use App\Services\AttachmentCleanupService;
use App\Services\ImageUploadService;
use App\Services\Notifications;
use App\Services\PostContentStorage;
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
        $categories = Category::allEnabled();
        $categoryCounts = [];
        foreach ($categories as $category) {
            $categoryCounts[$category->id] = Post::count(['category_id' => $category->id, 'status' => PostStatus::Published->value]);
        }

        return View::render('post.index', [
            'posts'     => $result['items'],
            'categories' => $categories,
            'categoryCounts' => $categoryCounts,
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

    public function importForm(): string
    {
        return View::render('post.import', [
            'categories' => Category::allEnabled(),
            'files'      => $this->availableImportFiles(),
            'csrf'       => Session::csrfToken(),
            'pageTitle'  => '导入 Markdown',
        ], 'layouts.admin');
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
        Post::ensurePublishingOptionsSchema();
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

    public function preview(Request $request): never
    {
        Response::json([
            'html' => Markdown::parse((string)$request->input('markdown', '')),
        ]);
    }

    public function uploadImage(Request $request): never
    {
        $file = $request->files['image'] ?? null;
        if (!is_array($file)) {
            Response::json(['code' => 1, 'msg' => ImageUploadService::missingUploadMessage('图片')]);
        }

        try {
            $data = ImageUploadService::upload($file, (string)$request->input('purpose', 'post'));
            Response::json(['code' => 0, 'msg' => 'ok', 'data' => $data]);
        } catch (\Throwable $e) {
            Response::json(['code' => 1, 'msg' => $e->getMessage()]);
        }
    }

    public function generateSummary(Request $request): never
    {
        try {
            $result = AiSummaryService::summarize(
                (string)$request->input('markdown', ''),
                (string)$request->input('title', '')
            );
            Response::json(['code' => 0, 'msg' => 'ok', 'data' => $result]);
        } catch (\Throwable $e) {
            Response::json(['code' => 1, 'msg' => $e->getMessage()]);
        }
    }

    public function importMarkdown(Request $request): never
    {
        [$markdown, $sourceName] = $this->readImportMarkdown($request);
        $markdown = trim($markdown);
        if ($markdown === '') {
            $this->flashError('请选择 Markdown 文件，或确认文件内容不为空');
            $this->redirect('/admin/posts/import');
        }

        $frontMatter = $this->extractFrontMatter($markdown);
        $body = $frontMatter['body'];
        $meta = $frontMatter['meta'];

        $title = trim((string)$request->input('title', ''));
        if ($title === '') {
            $title = (string)($meta['title'] ?? $this->inferTitle($body, $sourceName));
        }
        if ($title === '') {
            $this->flashError('无法识别标题，请手动填写标题');
            $this->redirect('/admin/posts/import');
        }

        $slug = Post::resolveSlug(
            (string)$request->input('slug', $meta['slug'] ?? ''),
            $title,
            null
        );
        $status = (string)$request->input('status', $meta['status'] ?? PostStatus::Draft->value);
        if (!in_array($status, PostStatus::values(), true)) {
            $status = PostStatus::Draft->value;
        }

        $summary = trim((string)$request->input('summary', $meta['summary'] ?? ''));
        $cover = trim((string)$request->input('cover', $meta['cover'] ?? ''));
        $publishedAt = trim((string)($meta['published_at'] ?? ''));
        if ($publishedAt === '') {
            $publishedAt = date('Y-m-d H:i:s');
        }
        $now = date('Y-m-d H:i:s');

        $post = new Post([
            'title'            => $title,
            'slug'             => $slug,
            'summary'          => $summary,
            'content'          => '',
            'markdown_content' => '',
            'cover'            => $cover,
            'category_id'      => (int)$request->input('category_id', 0),
            'user_id'          => Session::get('admin_user.id', 1),
            'is_top'           => Toggle::Off->value,
            'is_recommend'     => Toggle::Off->value,
            'status'           => $status,
            'published_at'     => $publishedAt,
            'created_at'       => $now,
            'updated_at'       => $now,
        ]);
        $post->save();
        PostContentStorage::writePost($slug, $title, $body);
        (new ActivityService())->recordPost($post, 'published_post');
        if ($status === PostStatus::Published->value) {
            Notifications::postPublished($post);
        }

        if ((string)$request->input('delete_source', '') === '1') {
            $this->deleteImportSource((string)$request->input('import_file', ''));
        }

        $this->flashSuccess('Markdown 已导入为文章：' . $title);
        $this->redirect('/admin/posts/' . $post->id . '/edit');
    }

    private function persist(Request $request, ?int $id): never
    {
        Post::ensurePublishingOptionsSchema();
        $submitAction = (string)$request->input('submit_action', '');
        $status = (string)$request->input('status', PostStatus::Draft->value);
        if ($submitAction === 'publish') {
            $status = PostStatus::Published->value;
        } elseif ($submitAction === 'save_draft') {
            $status = PostStatus::Draft->value;
        }

        $data = [
            'title'    => $request->input('title', ''),
            'slug'     => $request->input('slug', ''),
            'summary'  => $request->input('summary', ''),
            'markdown' => $request->input('markdown_content', ''),
            'cover'    => $request->input('cover', ''),
            'category_id' => $request->input('category_id', 0),
            'status'   => $status,
            'published_at' => $request->input('published_at', ''),
            'allow_comments' => $request->input('allow_comments', '0'),
            'allow_rss' => $request->input('allow_rss', '0'),
            'is_top' => $request->input('is_top', '0'),
            'is_private' => $request->input('is_private', '0'),
        ];

        $validator = Validator::make($data, [
            'title'   => 'required|string|min:1|max:200',
            'status'  => 'in:' . implode(',', PostStatus::values()),
        ]);

        if (!$validator->validate()) {
            $this->flashError($validator->firstError() ?? '校验失败');
            $this->redirect($id ? "/admin/posts/{$id}/edit" : '/admin/posts/create');
        }

        $markdown = trim((string)$data['markdown']);
        if ($markdown === '') {
            $this->flashError('Markdown 内容不能为空');
            $this->redirect($id ? "/admin/posts/{$id}/edit" : '/admin/posts/create');
        }

        $slug = Post::resolveSlug((string)$data['slug'], (string)$data['title'], $id);
        $now = date('Y-m-d H:i:s');
        $publishedAt = $this->normalizeDateTime((string)$data['published_at'], $now);
        $categoryId = $this->normalizeCategoryId((int)$data['category_id']);
        $allowComments = ((string)$data['allow_comments'] === '1') ? Toggle::On->value : Toggle::Off->value;
        $allowRss = ((string)$data['allow_rss'] === '1') ? Toggle::On->value : Toggle::Off->value;
        $isTop = ((string)$data['is_top'] === '1') ? Toggle::On->value : Toggle::Off->value;
        $isPrivate = ((string)$data['is_private'] === '1') ? Toggle::On->value : Toggle::Off->value;

        if ($id) {
            $post = Post::find($id);
            if (!$post) {
                $this->flashError('文章不存在');
                $this->redirect('/admin/posts');
            }
            $oldSlug = (string)$post->slug;
            $oldStatus = (string)($post->status ?? '');
            $post->fill([
                'title'            => trim((string)$data['title']),
                'slug'             => $slug,
                'summary'          => trim((string)$data['summary']),
                'content'          => '',
                'markdown_content' => '',
                'cover'            => trim((string)$data['cover']),
                'category_id'      => $categoryId,
                'status'           => $data['status'],
                'published_at'     => $publishedAt,
                'allow_comments'   => $allowComments,
                'allow_rss'        => $allowRss,
                'is_top'           => $isTop,
                'is_private'       => $isPrivate,
                'updated_at'       => $now,
            ]);
            $post->save();
            PostContentStorage::rename($oldSlug, $slug);
            PostContentStorage::writePost($slug, trim((string)$data['title']), $markdown);
            (new ActivityService())->recordPost($post, 'updated_post');
            if ($oldStatus !== PostStatus::Published->value && $data['status'] === PostStatus::Published->value) {
                Notifications::postPublished($post);
            }
        } else {
            $post = new Post([
                'title'            => trim((string)$data['title']),
                'slug'             => $slug,
                'summary'          => trim((string)$data['summary']),
                'content'          => '',
                'markdown_content' => '',
                'cover'            => trim((string)$data['cover']),
                'category_id'      => $categoryId,
                'user_id'          => Session::get('admin_user.id', 1),
                'is_top'           => $isTop,
                'is_recommend'     => Toggle::Off->value,
                'allow_comments'   => $allowComments,
                'allow_rss'        => $allowRss,
                'is_private'       => $isPrivate,
                'status'           => $data['status'],
                'published_at'     => $publishedAt,
                'created_at'       => $now,
                'updated_at'       => $now,
            ]);
            $post->save();
            PostContentStorage::writePost($slug, trim((string)$data['title']), $markdown);
            (new ActivityService())->recordPost($post, 'published_post');
            if ($data['status'] === PostStatus::Published->value) {
                Notifications::postPublished($post);
            }
        }

        $this->flashSuccess($data['status'] === PostStatus::Published->value ? ($id ? '文章已发布' : '文章已发布') : ($id ? '草稿已保存' : '草稿已保存'));
        $this->redirect('/admin/posts');
    }

    private function normalizeDateTime(string $value, string $fallback): string
    {
        $value = trim($value);
        if ($value === '') {
            return $fallback;
        }
        $value = str_replace('T', ' ', $value);
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $value)) {
            $value .= ':00';
        }
        $time = strtotime($value);
        return $time ? date('Y-m-d H:i:s', $time) : $fallback;
    }

    private function normalizeCategoryId(int $categoryId): int
    {
        if ($categoryId > 0) {
            return $categoryId;
        }
        return (int) (Category::allEnabled()[0]->id ?? 0);
    }

    public function toggleFlag(Request $request, array $params): never
    {
        $id = (int)($params['id'] ?? 0);
        $field = (string)$request->input('field', '');
        $allowed = [
            'is_top' => ['on' => '已置顶', 'off' => '已取消置顶'],
            'is_recommend' => ['on' => '已推荐', 'off' => '已取消推荐'],
        ];

        if (!isset($allowed[$field])) {
            Response::json(['code' => 1, 'msg' => '非法操作'], 400);
        }

        $post = $id > 0 ? Post::find($id) : null;
        if (!$post) {
            Response::json(['code' => 1, 'msg' => '文章不存在'], 404);
        }

        $current = (int)($post->{$field} ?? 0);
        $next = $current === Toggle::On->value ? Toggle::Off->value : Toggle::On->value;
        Post::db()->update('posts', [$field => $next, 'updated_at' => date('Y-m-d H:i:s')], 'id = :id', [':id' => $id]);

        Response::json([
            'code' => 0,
            'msg' => $next === Toggle::On->value ? $allowed[$field]['on'] : $allowed[$field]['off'],
            'data' => [
                'id' => $id,
                'field' => $field,
                'value' => $next,
            ],
        ]);
    }

    public function destroy(Request $request, array $params): never
    {
        $id = (int)($params['id'] ?? 0);
        if ($id) {
            $post = Post::find($id);
            $attachmentValues = $post ? [
                (string)($post->cover ?? ''),
                (string)($post->content ?? ''),
                (string)($post->markdown_content ?? ''),
            ] : [];
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
            if ($post) {
                PostContentStorage::delete((string)$post->slug);
            }
            AttachmentCleanupService::deleteUnusedFromValues($attachmentValues);
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
                $posts = Post::whereInIds($ids);
                $attachmentValues = [];
                foreach ($posts as $post) {
                    $attachmentValues[] = (string)($post->cover ?? '');
                    $attachmentValues[] = (string)($post->content ?? '');
                    $attachmentValues[] = (string)($post->markdown_content ?? '');
                }
                $db->beginTransaction();
                $db->query("DELETE FROM comments WHERE post_id IN ({$placeholders})", $ids);
                $db->query("DELETE FROM posts WHERE id IN ({$placeholders})", $ids);
                $db->commit();
                foreach ($posts as $post) {
                    PostContentStorage::delete((string)$post->slug);
                }
                AttachmentCleanupService::deleteUnusedFromValues($attachmentValues);
                $this->flashSuccess('已删除 ' . count($ids) . ' 篇文章');
                break;
            case 'publish':
                $notifyPosts = Post::query(
                    "SELECT * FROM posts WHERE status <> '" . PostStatus::Published->value . "' AND id IN ({$placeholders})",
                    $ids
                );
                $db->query(
                    "UPDATE posts SET status='" . PostStatus::Published->value . "' WHERE id IN ({$placeholders})",
                    $ids
                );
                foreach ($notifyPosts as $post) {
                    $post->status = PostStatus::Published->value;
                    Notifications::postPublished($post);
                }
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

    private function importDir(): string
    {
        return BASE_PATH . '/runtime/storage/imports';
    }

    private function availableImportFiles(): array
    {
        $dir = $this->importDir();
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $files = glob($dir . '/*.md') ?: [];
        sort($files);
        return array_map(fn($path) => [
            'name' => basename($path),
            'size' => is_file($path) ? filesize($path) : 0,
            'mtime' => is_file($path) ? filemtime($path) : 0,
        ], $files);
    }

    /**
     * @return array{0:string,1:string}
     */
    private function readImportMarkdown(Request $request): array
    {
        $upload = $request->files['md_file'] ?? null;
        if (is_array($upload) && ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $name = (string)($upload['name'] ?? 'import.md');
            if (!str_ends_with(strtolower($name), '.md')) {
                $this->flashError('只能导入 .md 文件');
                $this->redirect('/admin/posts/import');
            }
            return [(string)file_get_contents((string)$upload['tmp_name']), $name];
        }

        $selected = basename((string)$request->input('import_file', ''));
        if ($selected !== '') {
            $path = $this->importDir() . '/' . $selected;
            if (is_file($path) && str_ends_with(strtolower($path), '.md')) {
                return [(string)file_get_contents($path), $selected];
            }
        }

        return ['', ''];
    }

    /**
     * @return array{meta:array<string,string>, body:string}
     */
    private function extractFrontMatter(string $markdown): array
    {
        if (!preg_match('/^---\s*\n(.*?)\n---\s*\n(.*)$/s', $markdown, $matches)) {
            return ['meta' => [], 'body' => $markdown];
        }
        $meta = [];
        foreach (explode("\n", trim($matches[1])) as $line) {
            if (!str_contains($line, ':')) {
                continue;
            }
            [$key, $value] = explode(':', $line, 2);
            $meta[trim($key)] = trim($value, " \t\n\r\0\x0B\"'");
        }
        return ['meta' => $meta, 'body' => trim($matches[2])];
    }

    private function inferTitle(string $markdown, string $sourceName): string
    {
        if (preg_match('/^#\s+(.+)$/m', $markdown, $matches)) {
            return trim($matches[1]);
        }
        $name = pathinfo($sourceName, PATHINFO_FILENAME);
        return trim(str_replace(['-', '_'], ' ', $name));
    }

    private function deleteImportSource(string $filename): void
    {
        $filename = basename($filename);
        if ($filename === '') {
            return;
        }
        $path = $this->importDir() . '/' . $filename;
        if (is_file($path)) {
            @unlink($path);
        }
    }
}
