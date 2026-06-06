<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Enums\Toggle;
use App\Models\Link;
use App\Services\FriendRssService;

class LinkController
{
    public function index(): string
    {
        $links = Link::all('sort ASC, id ASC');
        $rssStatus = [];
        foreach ($links as $l) {
            if (!empty($l->rss_url)) {
                $result = FriendRssService::cachedResult((string)$l->rss_url);
                $rssStatus[$l->id] = [
                    'ok' => $result['ok'],
                    'count' => count($result['items']),
                    'error' => $result['error'],
                    'from_cache' => $result['from_cache'],
                ];
            } else {
                $rssStatus[$l->id] = null;
            }
        }

        return View::render('link.index', [
            'links' => $links,
            'rssStatus' => $rssStatus,
            'csrf' => Session::csrfToken(),
            'pageTitle' => '友情链接',
        ], 'layouts.admin');
    }

    public function save(Request $request): never
    {
        $id   = (int) $request->input('id', 0);
        $name = trim((string) $request->input('name', ''));
        $url  = trim((string) $request->input('url', ''));
        $logo = trim((string) $request->input('logo', ''));
        $desc = trim((string) $request->input('description', ''));
        $rss  = trim((string) $request->input('rss_url', ''));
        $sort = (int) $request->input('sort', 0);
        $enabled = Toggle::fromInput($request->input('is_enabled', 1))->value;

        if ($name === '' || $url === '') {
            Session::flash('error', '名称和 URL 不能为空');
            Response::redirect('/admin/links');
        }

        if ($id) {
            $link = Link::find($id);
            if ($link) {
                $link->fill([
                    'name' => $name, 'url' => $url, 'logo' => $logo,
                    'description' => $desc, 'rss_url' => $rss,
                    'sort' => $sort, 'is_enabled' => $enabled,
                ]);
                $link->save();
            }
        } else {
            $link = new Link([
                'name' => $name, 'url' => $url, 'logo' => $logo,
                'description' => $desc, 'rss_url' => $rss,
                'sort' => $sort, 'is_enabled' => $enabled,
            ]);
            $link->save();
        }
        Session::flash('success', $id ? '友链已更新' : '友链已添加');
        Response::redirect('/admin/links');
    }

    public function destroy(Request $request): never
    {
        $id = (int) $request->input('id', 0);
        if ($id) {
            Link::db()->delete('links', 'id = ?', [$id]);
        }
        Session::flash('success', '友链已删除');
        Response::redirect('/admin/links');
    }

    public function bulkDelete(Request $request): never
    {
        $ids = $this->idsFromRequest($request);
        if (!$ids) {
            if ($request->isAjax()) {
                Response::json(['code' => 1, 'msg' => '请选择要删除的友链'], 422);
            }
            Session::flash('error', '请选择要删除的友链');
            Response::redirect('/admin/links');
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $deleted = Link::db()->delete('links', 'id IN (' . $placeholders . ')', $ids);
        $message = '已删除 ' . $deleted . ' 条友链';

        if ($request->isAjax()) {
            Response::json([
                'code' => 0,
                'msg' => $message,
                'deleted' => $deleted,
                'ids' => $ids,
            ]);
        }

        Session::flash('success', $message);
        Response::redirect('/admin/links');
    }

    public function refresh(Request $request): never
    {
        $isAjax = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
        $id = (int) $request->input('id', 0);
        $link = Link::find($id);
        if (!$link || empty($link->rss_url)) {
            if ($isAjax) {
                Response::json(['code' => 1, 'msg' => '友链未配置 RSS'], 422);
            }
            Session::flash('error', '友链未配置 RSS');
            Response::redirect('/admin/links');
        }
        // 删除缓存后重新抓取
        $key = md5($link->rss_url);
        @unlink(BASE_PATH . '/runtime/storage/cache/friend_' . $key . '.json');
        $result = FriendRssService::fetchResult((string)$link->rss_url, 5, 60, true);
        $items = $result['items'];
        $ok = $result['ok'];
        $message = $ok ? 'RSS 缓存已刷新' : ('RSS 刷新失败：' . ($result['error'] ?: '未知错误'));
        if ($isAjax) {
            Response::json([
                'code'  => $ok ? 0 : 1,
                'msg'   => $message,
                'error' => $result['error'],
                'count' => count($items),
                'id' => (int)$link->id,
                'name' => (string)$link->name,
                'rss_url' => (string)$link->rss_url,
            ], $ok ? 200 : 422);
        }
        Session::flash($ok ? 'success' : 'error', $message);
        Response::redirect('/admin/links');
    }

    public function refreshAggregate(Request $request): never
    {
        try {
            $items = FriendRssService::refreshAggregate(5, 50, false);
            Response::json([
                'code' => 0,
                'msg' => '订阅聚合缓存已更新',
                'count' => count($items),
            ]);
        } catch (\Throwable $e) {
            Response::json([
                'code' => 1,
                'msg' => '订阅聚合缓存更新失败：' . ($e->getMessage() ?: '未知错误'),
            ], 500);
        }
    }

    /**
     * @return int[]
     */
    private function idsFromRequest(Request $request): array
    {
        $raw = $request->input('ids', []);
        if (!is_array($raw)) {
            $raw = preg_split('/\s*,\s*/', (string)$raw) ?: [];
        }
        $ids = [];
        foreach ($raw as $id) {
            $id = (int)$id;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        return array_values($ids);
    }
}
