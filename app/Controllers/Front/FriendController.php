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
        $links = Link::enabled();
        $friendPage = Page::findBySlug('friends');
        $comments = $friendPage ? Comment::forPage((int)$friendPage->id) : [];

        return View::render('friend.index', [
            'links'    => $links,
            'friendPage' => $friendPage,
            'comments' => $comments,
            'siteCopyItems' => $this->siteCopyItems(),
            'pageTitle' => '友情链接',
            'activeNav' => 'friends',
            'categories' => Category::allEnabled(),
            'recentPosts' => Post::recent(5),
        ], 'layouts.front');
    }

    public function subscribe(): string
    {
        $rssItems = [];
        try {
            $rssItems = FriendRssService::aggregate(5, 50);
        } catch (\Throwable) {
            $rssItems = [];
        }

        return View::render('friend.subscribe', [
            'rssItems' => $rssItems,
            'lastUpdated' => FriendRssService::lastUpdated(),
            'pageTitle' => '订阅',
            'activeNav' => 'feeds',
            'categories' => Category::allEnabled(),
            'recentPosts' => Post::recent(5),
        ], 'layouts.front');
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
