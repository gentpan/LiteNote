<?php
declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\Config;
use App\Core\Helper;
use App\Core\Response;
use App\Core\Rss;
use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\Setting;

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

    private function baseUrl(): string
    {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if ($host !== '') {
            return (Helper::isHttps() ? 'https://' : 'http://') . $host;
        }

        return rtrim(Config::get('app.url'), '/');
    }
}
