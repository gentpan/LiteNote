<?php
declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\Helper;
use App\Core\View;
use App\Models\Category;
use App\Models\Link;
use App\Models\Post;
use App\Services\FriendRssService;

class FriendController
{
    public function index(): string
    {
        $links = Link::enabled();
        $rssItems = [];
        $rssEnabled = \App\Core\Config::get('site.friends_rss_enabled', 1);
        if ($rssEnabled) {
            try {
                $rssItems = FriendRssService::aggregate(3, 30);
            } catch (\Throwable) {
                $rssItems = [];
            }
        }

        return View::render('friend.index', [
            'links'    => $links,
            'rssItems' => $rssItems,
            'pageTitle' => '友情链接',
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
            'pageTitle' => '订阅',
            'categories' => Category::allEnabled(),
            'recentPosts' => Post::recent(5),
        ], 'layouts.front');
    }
}
