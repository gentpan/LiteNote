<?php
declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\Config;
use App\Core\Helper;
use App\Core\Response;
use App\Core\Rss;
use App\Enums\PostStatus;
use App\Models\Link;
use App\Models\Post;
use App\Models\Setting;
use App\Services\FriendRssService;

class FeedController
{
    public function feed(): never
    {
        $siteTitle = Setting::get('title', 'LiteNote');
        $siteDesc  = Setting::get('description', '');
        $baseUrl   = $this->baseUrl();
        $fullText  = (bool) Setting::get('rss_full_text', 1);

        $rows = Post::db()->fetchAll(
            "SELECT * FROM posts WHERE status='" . PostStatus::Published->value . "' ORDER BY published_at DESC LIMIT 30"
        );

        $items = [];
        foreach ($rows as $r) {
            $post = new Post($r);
            $content = $fullText ? $post->html() : $post->summaryOrContent(300);
            $items[] = [
                'title'       => $post->title,
                'link'        => $baseUrl . '/post/' . $post->slug . '.html',
                'description' => $post->summaryOrContent(300),
                'content'     => $content,
                'pubDate'     => strtotime((string)$post->published_at) ?: time(),
                'author'      => 'admin',
                'category'    => $post->getCategory()?->name,
            ];
        }

        $xml = Rss::feed([
            'title'       => $siteTitle,
            'link'        => $baseUrl,
            'description' => $siteDesc,
            'language'    => 'zh-CN',
            'atom_link'   => $baseUrl . '/feed',
        ], $items);

        Response::xml($xml);
    }

    public function friendsFeed(): never
    {
        $siteTitle = Setting::get('title', 'LiteNote');
        $siteDesc  = '友情链接最新文章聚合';
        $baseUrl   = $this->baseUrl();

        $items = [];
        try {
            $aggregated = FriendRssService::aggregate(5, 50);
            foreach ($aggregated as $it) {
                $items[] = [
                    'title'       => '[' . ($it['friend_name'] ?? '') . '] ' . ($it['title'] ?? ''),
                    'link'        => $it['link'] ?? '',
                    'description' => $it['description'] ?? '',
                    'content'     => $it['description'] ?? '',
                    'pubDate'     => strtotime($it['pubDate'] ?? '') ?: time(),
                ];
            }
        } catch (\Throwable) {
            // ignore
        }

        $xml = Rss::feed([
            'title'       => $siteTitle . ' - 友链聚合',
            'link'        => $baseUrl . '/friends',
            'description' => $siteDesc,
            'language'    => 'zh-CN',
            'atom_link'   => $baseUrl . '/friends/feed',
        ], $items);

        Response::xml($xml);
    }

    private function baseUrl(): string
    {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if ($host !== '') {
            return (Helper::isHttps() ? 'https://' : 'http://') . $host;
        }

        return rtrim(Config::get('app.url'), '/');
    }
}
