<?php
declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\Config;
use App\Core\FileCache;
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
        $xml = (new FileCache())->remember('rss.xml', 300, function (): string {
            Post::ensurePublishingOptionsSchema();
            $siteTitle = Setting::get('title', 'LiteNote');
            $siteDesc  = Setting::get('description', '');
            $baseUrl   = $this->baseUrl();

            $published = PostStatus::Published->value;
            $rows = Post::db()->fetchAll(
                "SELECT id, title, slug, summary, markdown_content, category_id, published_at, created_at
                 FROM posts
                 WHERE status = ? AND COALESCE(is_private, 0) = 0 AND COALESCE(allow_rss, 1) = 1
                 ORDER BY published_at DESC LIMIT 30",
                [$published]
            );

            $items = [];
            foreach ($rows as $r) {
                $post = new Post($r);
                $items[] = [
                    'title'       => $post->title,
                    'link'        => $baseUrl . $post->getUrl(),
                    'description' => $post->summaryOrContent(300),
                    'content'     => $post->html(),
                    'pubDate'     => strtotime((string)$post->published_at) ?: time(),
                    'author'      => 'admin',
                    'category'    => $post->getCategory()?->name,
                ];
            }

            return Rss::feed([
                'title'       => $siteTitle,
                'link'        => $baseUrl,
                'description' => $siteDesc,
                'language'    => 'zh-CN',
                'atom_link'   => $baseUrl . '/rss.xml',
            ], $items);
        });

        Response::xml($xml);
    }

    /**
     * 旧 /feed 地址 301 重定向到 /rss.xml(兼容老订阅者)。
     */
    public function feedRedirect(): never
    {
        Response::redirect('/rss.xml', 301);
    }

    /**
     * 生成 llms.txt —— 面向 AI/LLM 收录的站点内容索引(Markdown 纯文本)。
     * 规范见 https://llmstxt.org/
     */
    public function llms(): never
    {
        $text = (new FileCache())->remember('llms.txt', 300, function (): string {
            Post::ensurePublishingOptionsSchema();
            $siteTitle = (string) Setting::get('title', 'LiteNote');
            $siteDesc  = (string) Setting::get('description', '');
            $baseUrl   = $this->baseUrl();

            $published = PostStatus::Published->value;
            $rows = Post::db()->fetchAll(
                "SELECT id, title, slug, summary, markdown_content, category_id, published_at, created_at
                 FROM posts
                 WHERE status = ? AND COALESCE(is_private, 0) = 0 AND COALESCE(allow_rss, 1) = 1
                 ORDER BY published_at DESC LIMIT 200",
                [$published]
            );

            $lines = [];
            $lines[] = '# ' . $siteTitle;
            $lines[] = '';
            if ($siteDesc !== '') {
                $lines[] = '> ' . $siteDesc;
                $lines[] = '';
            }
            $lines[] = '本文件用于帮助 AI / 大语言模型理解与收录本站内容。';
            $lines[] = '';
            $lines[] = '## 文章';
            $lines[] = '';
            foreach ($rows as $r) {
                $post = new Post($r);
                $url = $baseUrl . $post->getUrl();
                $summary = trim((string) $post->summaryOrContent(120));
                $summary = preg_replace('/\s+/u', ' ', $summary);
                $lines[] = '- [' . $post->title . '](' . $url . ')' . ($summary !== '' ? ': ' . $summary : '');
            }
            $lines[] = '';
            $lines[] = '## 订阅';
            $lines[] = '';
            $lines[] = '- [RSS](' . $baseUrl . '/rss.xml)';

            return implode("\n", $lines) . "\n";
        });

        Response::text($text, 200, 'text/plain; charset=utf-8');
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
