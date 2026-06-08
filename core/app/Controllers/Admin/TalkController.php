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
use App\Models\Talk;
use App\Services\AttachmentCleanupService;

class TalkController
{
    public function index(): string
    {
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 20;
        $where = "(COALESCE(music_id, 0) = 0 AND COALESCE(post_type, 'talk') != 'music')";
        $result = Talk::db()->fetchAll(
            "SELECT * FROM talk WHERE {$where} ORDER BY id DESC LIMIT {$perPage} OFFSET " . (($page-1)*$perPage)
        );
        $total = (int) Talk::db()->fetchColumn("SELECT COUNT(*) FROM talk WHERE {$where}");
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

    public function edit($request, array $params): never
    {
        $id = (int)($params['id'] ?? 0);
        $item = Talk::find($id);
        if (!$item) {
            Response::json(['code' => 1, 'msg' => '滔客不存在'], 404);
        }
        $type = (int)($item->music_id ?? 0) > 0 ? 'music' : $this->normalizePostType((string)($item->post_type ?? 'talk'));
        Response::json([
            'code' => 0,
            'data' => [
                'id' => (int)$item->id,
                'post_type' => $type,
                'content' => (string)($item->content ?? ''),
                'images' => (string)($item->images ?? ''),
                'mood' => (string)($item->mood ?? ''),
                'music_id' => (int)($item->music_id ?? 0),
                'is_public' => (int)($item->is_public ?? 1),
                'published_at' => (string)($item->published_at ?? $item->created_at ?? ''),
            ],
        ]);
    }

    public function update(Request $request, array $params): never
    {
        $this->save($request, (int)($params['id'] ?? 0));
    }

    private function save(Request $request, ?int $id): never
    {
        $postType = $this->normalizePostType((string)$request->input('post_type', 'talk'));
        $content = trim((string) $request->input('content', ''));
        $images  = trim((string) $request->input('images', ''));
        $mood    = $postType === 'music' ? '' : trim((string) $request->input('mood', ''));
        $musicId = $postType === 'music' ? $this->normalizeMusicId((int)$request->input('music_id', 0)) : 0;
        $public  = $postType === 'talk' ? Toggle::fromInput($request->input('is_public', 0))->value : Toggle::On->value;
        $publishedAt = $postType === 'talk' ? trim((string)$request->input('published_at', '')) : '';
        $existingItem = $id ? Talk::find($id) : null;

        if ($postType === 'talk' && $content === '') {
            if ($this->wantsJson()) {
                Response::json(['code' => 1, 'msg' => '内容不能为空'], 422);
            }
            Session::flash('error', '内容不能为空');
            Response::redirect('/admin/talk');
        }
        if ($postType === 'music' && $musicId <= 0) {
            if ($this->wantsJson()) {
                Response::json(['code' => 1, 'msg' => '请选择要分享的音乐'], 422);
            }
            Session::flash('error', '请选择要分享的音乐');
            Response::redirect('/admin/talk');
        }
        if ($postType === 'music' && $content === '') {
            $content = '分享一首音乐';
        }
        if ($publishedAt === '' && $postType !== 'talk' && $existingItem) {
            $publishedAt = (string)($existingItem->published_at ?? $existingItem->created_at ?? '');
        }

        $fields = [
            'content' => $content,
            'images'  => $images,
            'mood'    => $mood,
            'music_id' => $musicId,
            'is_public' => $public,
            'post_type' => $postType,
            'published_at' => $publishedAt !== '' ? $publishedAt : date('Y-m-d H:i:s'),
        ];
        if ($id) {
            $item = $existingItem ?: Talk::find($id);
            if ($item) {
                $item->fill($fields);
                $item->save();
            }
        } else {
            $item = new Talk($fields);
            $item->save();
        }
        if ($this->wantsJson()) {
            Response::json([
                'code' => 0,
                'msg' => $this->typeLabel($postType) . '已更新',
                'data' => [
                    'id' => (int)($item->id ?? $id),
                    'type' => $this->typeLabel($postType),
                    'content' => $content,
                    'content_preview' => Helper::truncate($content, 100),
                    'keywords' => $this->extractContentKeywords($content),
                    'mood' => $mood,
                    'is_public' => $public,
                    'published_at' => $fields['published_at'],
                ],
            ]);
        }

        Session::flash('success', $id ? $this->typeLabel($postType) . '已更新' : $this->typeLabel($postType) . '已发布');
        Response::redirect('/admin/talk');
    }

    public function togglePublic(Request $request, array $params): never
    {
        $id = (int)($params['id'] ?? 0);
        $item = $id > 0 ? Talk::find($id) : null;
        if (!$item) {
            Response::json(['code' => 1, 'msg' => '滔客不存在'], 404);
        }

        $next = (int)($item->is_public ?? 1) === Toggle::On->value ? Toggle::Off->value : Toggle::On->value;
        Talk::db()->update('talk', ['is_public' => $next], 'id = :id', [':id' => $id]);

        Response::json([
            'code' => 0,
            'msg' => $next === Toggle::On->value ? '滔客已公开' : '滔客已隐藏',
            'data' => [
                'id' => $id,
                'is_public' => $next,
                'public_label' => $next === Toggle::On->value ? '公开' : '隐藏',
                'toggle_title' => $next === Toggle::On->value ? '设为隐藏' : '恢复公开',
            ],
        ]);
    }

    public function destroy(Request $request): never
    {
        $id = (int) $request->input('id', 0);
        if ($id) {
            $item = Talk::find($id);
            $attachmentValues = $item ? [
                (string)($item->images ?? ''),
                (string)($item->content ?? ''),
                (string)($item->music_cover ?? ''),
                (string)($item->music ?? ''),
            ] : [];
            $db = Talk::db();
            try {
                $db->beginTransaction();
                $db->delete('comments', 'talk_id = ?', [$id]);
                $db->delete('talk', 'id = ?', [$id]);
                $db->commit();
            } catch (\Throwable $e) {
                $db->rollBack();
                Session::flash('error', '删除失败，请稍后重试');
                Response::redirect('/admin/talk');
            }
            AttachmentCleanupService::deleteUnusedFromValues($attachmentValues);
        }
        Session::flash('success', '滔客已删除');
        Response::redirect('/admin/talk');
    }

    private function normalizeMusicId(int $musicId): int
    {
        if ($musicId <= 0) {
            return 0;
        }
        $music = Music::find($musicId);
        if (!$music || (int)$music->is_public !== Toggle::On->value) {
            return 0;
        }
        return (int)$music->id;
    }

    private function normalizePostType(string $type): string
    {
        return in_array($type, ['talk', 'music'], true) ? $type : 'talk';
    }

    private function typeLabel(string $type): string
    {
        return match ($type) {
            'music' => '音乐分享',
            default => '滔客',
        };
    }

    private function wantsJson(): bool
    {
        return strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
    }

    /**
     * @return array<int,string>
     */
    private function extractContentKeywords(string $content): array
    {
        preg_match_all('/#([\p{L}\p{N}_-]+)/u', $content, $matches);
        return array_values(array_unique(array_filter(array_map('trim', $matches[1] ?? []))));
    }
}
