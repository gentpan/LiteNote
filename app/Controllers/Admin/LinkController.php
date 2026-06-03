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
        // 检测 RSS 可用性
        $rssStatus = [];
        foreach ($links as $l) {
            if (!empty($l->rss_url)) {
                $items = @FriendRssService::fetch($l->rss_url, 1, 60);
                $rssStatus[$l->id] = count($items) > 0;
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

    public function refresh(Request $request): never
    {
        $id = (int) $request->input('id', 0);
        $link = Link::find($id);
        if (!$link || empty($link->rss_url)) {
            Session::flash('error', '友链未配置 RSS');
            Response::redirect('/admin/links');
        }
        // 删除缓存
        $key = md5($link->rss_url);
        @unlink(__DIR__ . '/../../../storage/cache/friend_' . $key . '.json');
        FriendRssService::fetch($link->rss_url, 5, 60);
        Session::flash('success', 'RSS 缓存已刷新');
        Response::redirect('/admin/links');
    }
}
