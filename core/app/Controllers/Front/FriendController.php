<?php
declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\Helper;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Link;
use App\Models\Page;
use App\Models\Post;
use App\Models\Setting;
use App\Models\User;
use App\Services\FriendRssService;

class FriendController
{
    public function index(): string
    {
        return $this->page('links');
    }

    public function links(): string
    {
        return $this->page('links');
    }

    public function subscribe(): string
    {
        return $this->page('feeds');
    }

    public function apply(Request $request): never
    {
        $this->submitLinkRequest($request, 'apply');
    }

    public function modify(Request $request): never
    {
        $this->submitLinkRequest($request, 'modify');
    }

    private function page(string $defaultTab): string
    {
        $activeTab = ($_GET['tab'] ?? '') === 'feeds' ? 'feeds' : $defaultTab;
        $links = Link::enabled();
        $friendPage = Page::findBySlug('friends');
        $comments = $friendPage ? Comment::forPage((int)$friendPage->id) : [];
        $rssPool = [];
        try {
            $rssPool = FriendRssService::aggregate(5, 200);
        } catch (\Throwable) {
            $rssPool = [];
        }
        $rssItems = array_slice($rssPool, 0, 50);
        $rssFreshByLink = $this->recentRssMap($links, $rssPool);

        return View::render('friend.index', [
            'links'    => $links,
            'friendPage' => $friendPage,
            'comments' => $comments,
            'rssItems' => $rssItems,
            'rssFreshByLink' => $rssFreshByLink,
            'lastUpdated' => FriendRssService::lastUpdated(),
            'siteCopyItems' => $this->siteCopyItems(),
            'activeFriendTab' => $activeTab,
            'pageTitle' => $activeTab === 'feeds' ? '订阅文章' : '友情链接',
            'activeNav' => $activeTab === 'feeds' ? 'feeds' : 'friends',
            'categories' => Category::allEnabled(),
            'recentPosts' => Post::recent(5),
        ], 'layouts.front');
    }

    private function recentRssMap(array $links, array $rssItems): array
    {
        $cutoff = time() - 7 * 86400;
        $recent = [];
        foreach ($rssItems as $item) {
            $timestamp = (int)($item['published_ts'] ?? 0);
            if ($timestamp <= 0) {
                $time = strtotime((string)($item['pubDate'] ?? ''));
                $timestamp = $time === false ? (int)($item['fetched_at'] ?? 0) : $time;
            }
            if ($timestamp < $cutoff) {
                continue;
            }
            $friendUrl = trim((string)($item['friend_url'] ?? ''));
            $friendName = trim((string)($item['friend_name'] ?? ''));
            if ($friendUrl !== '') {
                $recent['url:' . $friendUrl] = true;
            }
            if ($friendName !== '') {
                $recent['name:' . $friendName] = true;
            }
        }

        $map = [];
        foreach ($links as $link) {
            $map[(int)$link->id] = !empty($recent['url:' . (string)$link->url]) || !empty($recent['name:' . (string)$link->name]);
        }
        return $map;
    }

    private function submitLinkRequest(Request $request, string $type): never
    {
        Link::ensureRequestColumns();

        $csrfToken = (string)$request->input('_csrf', '');
        if ($csrfToken === '' || !Session::verifyCsrf($csrfToken)) {
            $this->redirectWithLinkMessage('error', '页面已过期，请刷新后再提交。');
        }

        $name = trim((string)$request->input('name', ''));
        $url = $this->normalizeUrl((string)$request->input('url', ''));
        $contactEmail = trim((string)$request->input('contact_email', ''));
        $logo = $this->normalizeUrl((string)$request->input('logo', ''), true);
        $rssUrl = $this->normalizeUrl((string)$request->input('rss_url', ''), true);
        $description = trim((string)$request->input('description', ''));
        $previousUrl = $this->normalizeUrl((string)$request->input('previous_url', ''), $type !== 'modify');

        if ($name === '' || $url === '' || $contactEmail === '') {
            $this->redirectWithLinkMessage('error', '站点名称、站点地址和联系邮箱都需要填写。');
        }

        if (!filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
            $this->redirectWithLinkMessage('error', '联系邮箱格式不正确。');
        }

        if (!$this->isHttpUrl($url)) {
            $this->redirectWithLinkMessage('error', '站点地址需要是完整的 http 或 https 链接。');
        }

        if ($logo !== '' && !$this->isHttpUrl($logo)) {
            $this->redirectWithLinkMessage('error', 'Logo 地址需要是完整的 http 或 https 链接。');
        }

        if ($rssUrl !== '' && !$this->isHttpUrl($rssUrl)) {
            $this->redirectWithLinkMessage('error', 'RSS 地址需要是完整的 http 或 https 链接。');
        }

        if ($type === 'apply') {
            if (Link::findEnabledByUrl($url)) {
                $this->redirectWithLinkMessage('error', '这个站点已经在友链里了，如果需要更新请使用修改链接。');
            }
        } else {
            if ($previousUrl === '' || !$this->isHttpUrl($previousUrl)) {
                $this->redirectWithLinkMessage('error', '修改链接需要填写当前已展示的原链接。');
            }
            if (!Link::findEnabledByUrl($previousUrl)) {
                $this->redirectWithLinkMessage('error', '没有找到这个原链接，请先申请链接。');
            }
        }

        $pendingKey = $type === 'modify' ? $previousUrl : $url;
        $link = Link::findPendingRequest($type, $pendingKey, $contactEmail) ?? new Link();
        $now = date('Y-m-d H:i:s');
        $link->fill([
            'name' => $name,
            'url' => $url,
            'logo' => $logo,
            'description' => mb_substr($description, 0, 255),
            'rss_url' => $rssUrl,
            'contact_email' => $contactEmail,
            'request_type' => $type,
            'previous_url' => $type === 'modify' ? $previousUrl : '',
            'sort' => 0,
            'is_enabled' => 0,
            'submitted_at' => $now,
            'updated_at' => $now,
        ]);
        $link->save();

        $message = $type === 'modify'
            ? '修改申请已提交，审核通过后会更新展示。'
            : '友链申请已提交，审核通过后会显示在友链页面。';
        $this->redirectWithLinkMessage('success', $message);
    }

    private function redirectWithLinkMessage(string $type, string $message): never
    {
        Session::flash($type === 'success' ? 'friend_link_success' : 'friend_link_error', $message);
        Response::redirect('/links#friend-link-request');
    }

    private function normalizeUrl(string $url, bool $allowEmpty = false): string
    {
        $url = trim($url);
        if ($url === '') {
            return $allowEmpty ? '' : $url;
        }
        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }
        return $url;
    }

    private function isHttpUrl(string $url): bool
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        $scheme = strtolower((string)(parse_url($url, PHP_URL_SCHEME) ?: ''));
        return $scheme === 'http' || $scheme === 'https';
    }

    private function siteCopyItems(): array
    {
        $title = trim((string) Setting::get('title', ''));
        $description = trim((string) Setting::get('description', ''));
        $avatar = trim((string) Setting::get('site_avatar_url', ''));
        if ($avatar === '') {
            $author = User::find(1);
            $avatar = $author ? $author->getAvatarUrl(160) : '';
        }

        return [
            ['label' => 'RSS 地址', 'value' => Helper::url('/rss.xml'), 'icon' => 'fa-solid fa-square-rss'],
            ['label' => '网址', 'value' => Helper::url('/'), 'icon' => 'fa-solid fa-link'],
            ['label' => '站点名称', 'value' => $title !== '' ? $title : 'LiteNote', 'icon' => 'fa-regular fa-id-card'],
            ['label' => '描述', 'value' => $description, 'icon' => 'fa-regular fa-note-sticky'],
            ['label' => '站点 Logo', 'value' => $this->absoluteAssetUrl($avatar), 'icon' => 'fa-regular fa-image'],
        ];
    }

    private function absoluteAssetUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '' || preg_match('#^https?://#i', $url)) {
            return $url;
        }
        return Helper::url('/' . ltrim($url, '/'));
    }
}
