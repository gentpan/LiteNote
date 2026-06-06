<?php
declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\Helper;
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
            ['label' => '头像地址', 'value' => $this->absoluteAssetUrl($avatar), 'icon' => 'fa-regular fa-image'],
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
