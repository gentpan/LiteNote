<?php
declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\Helper;
use App\Core\View;
use App\Models\Category;
use App\Models\Music;
use App\Models\Page;
use App\Models\Post;
use App\Models\Talk;
use App\Enums\PostStatus;
use App\Enums\Toggle;

class SearchController
{
    public function index(): string
    {
        $keyword = trim((string)($_GET['q'] ?? ''));
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 10;

        $results = [];
        $total = 0;
        if ($keyword !== '') {
            ['items' => $results, 'total' => $total] = $this->searchSite($keyword, $page, $perPage);
        }

        return View::render('search.index', [
            'keyword' => $keyword,
            'results' => $results,
            'posts'   => array_values(array_filter($results, static fn(array $item): bool => ($item['type'] ?? '') === 'post')),
            'total'   => $total,
            'page'    => $page,
            'perPage' => $perPage,
            'paginator' => Helper::loadMore($page, $total, $perPage, Helper::url('/search') . '?q=' . urlencode($keyword)),
            'pageTitle' => $keyword !== '' ? '搜索: ' . $keyword : '搜索',
            'activeNav' => 'search',
            'categories' => Category::allEnabled(),
            'recentPosts' => Post::recent(5),
        ], 'layouts.front');
    }

    /**
     * @return array{items: array<int,array<string,mixed>>, total:int}
     */
    private function searchSite(string $keyword, int $page, int $perPage): array
    {
        $keyword = trim($keyword);
        if ($keyword === '' || mb_strlen($keyword) > 100) {
            return ['items' => [], 'total' => 0];
        }

        $results = array_merge(
            $this->searchPosts($keyword),
            $this->searchPages($keyword),
            $this->searchTalks($keyword),
            $this->searchMusic($keyword),
            $this->searchXTweets($keyword)
        );

        usort($results, static function (array $a, array $b): int {
            $ta = strtotime((string)($a['date'] ?? '')) ?: 0;
            $tb = strtotime((string)($b['date'] ?? '')) ?: 0;
            return $tb <=> $ta;
        });

        $offset = max(0, ($page - 1) * $perPage);
        return [
            'items' => array_slice($results, $offset, $perPage),
            'total' => count($results),
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function searchPosts(string $keyword): array
    {
        Post::ensurePublishingOptionsSchema();
        $rows = Post::db()->fetchAll(
            "SELECT p.*, c.name AS category_name
             FROM posts p
             LEFT JOIN categories c ON p.category_id = c.id
             WHERE p.status = ? AND COALESCE(p.is_private, 0) = 0
             ORDER BY p.published_at DESC, p.id DESC",
            [PostStatus::Published->value]
        );

        $results = [];
        foreach ($rows as $row) {
            $post = new Post($row);
            $text = implode("\n", [
                (string)$post->title,
                (string)($post->summary ?? ''),
                $post->markdown(),
                (string)($row['category_name'] ?? ''),
            ]);
            if (!$this->matches($text, $keyword)) {
                continue;
            }
            $results[] = $this->result(
                'post',
                '文章',
                (string)$post->title,
                $post->summaryOrContent(180),
                $post->getUrl(),
                (string)($post->published_at ?? $post->created_at ?? ''),
                'fa-regular fa-file-lines'
            );
        }
        return $results;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function searchPages(string $keyword): array
    {
        $rows = Page::db()->fetchAll('SELECT * FROM pages ORDER BY sort ASC, id DESC');
        $results = [];
        foreach ($rows as $row) {
            $page = new Page($row);
            $text = implode("\n", [(string)$page->title, (string)$page->content, (string)($page->markdown_content ?? '')]);
            if (!$this->matches($text, $keyword)) {
                continue;
            }
            $results[] = $this->result(
                'page',
                '页面',
                (string)$page->title,
                $this->excerpt((string)$page->content, 180),
                method_exists($page, 'getUrl') ? (string)$page->getUrl() : '/' . ltrim((string)$page->slug, '/'),
                (string)($page->updated_at ?? $page->created_at ?? ''),
                'fa-regular fa-bookmark'
            );
        }
        return $results;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function searchTalks(string $keyword): array
    {
        $rows = Talk::db()->fetchAll(
            "SELECT * FROM talk
             WHERE is_public = ? AND COALESCE(music_id, 0) = 0 AND COALESCE(post_type, 'talk') != 'music'
             ORDER BY published_at DESC, created_at DESC, id DESC",
            [Toggle::On->value]
        );
        $results = [];
        foreach ($rows as $row) {
            $content = (string)($row['content'] ?? '');
            if (!$this->matches($content . "\n" . (string)($row['mood'] ?? ''), $keyword)) {
                continue;
            }
            $results[] = $this->result(
                'talk',
                '滔客',
                '滔客 #' . (int)$row['id'],
                $this->excerpt($content, 180),
                '/talk#talk-' . (int)$row['id'],
                (string)($row['published_at'] ?? $row['created_at'] ?? ''),
                'fa-regular fa-comments'
            );
        }
        return $results;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function searchMusic(string $keyword): array
    {
        $rows = Music::db()->fetchAll('SELECT * FROM music ORDER BY published_at DESC, sort ASC, id DESC');
        $results = [];
        foreach ($rows as $row) {
            $text = implode("\n", [
                (string)($row['title'] ?? ''),
                (string)($row['artist'] ?? ''),
                (string)($row['album'] ?? ''),
                (string)($row['lyrics'] ?? ''),
            ]);
            if (!$this->matches($text, $keyword)) {
                continue;
            }
            $title = (string)($row['title'] ?? ('音乐 #' . (int)$row['id']));
            $artist = trim((string)($row['artist'] ?? ''));
            $results[] = $this->result(
                'music',
                '音乐',
                $title,
                trim($artist . ($artist !== '' && !empty($row['album']) ? ' · ' : '') . (string)($row['album'] ?? '')),
                '/music#music-' . (int)$row['id'],
                (string)($row['published_at'] ?? $row['created_at'] ?? ''),
                'fa-solid fa-music'
            );
        }
        return $results;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function searchXTweets(string $keyword): array
    {
        try {
            $rows = Post::db()->fetchAll(
                'SELECT * FROM x_tweets WHERE is_public = ? ORDER BY published_at DESC, created_at DESC, id DESC',
                [Toggle::On->value]
            );
        } catch (\Throwable) {
            return [];
        }

        $results = [];
        foreach ($rows as $row) {
            $content = (string)($row['content'] ?? '');
            $text = implode("\n", [
                $content,
                (string)($row['tweet_author_name'] ?? ''),
                (string)($row['tweet_author_handle'] ?? ''),
            ]);
            if (!$this->matches($text, $keyword)) {
                continue;
            }
            $results[] = $this->result(
                'x',
                'X',
                'X #' . (int)$row['id'],
                $this->excerpt($content, 180),
                '/x#xmark-' . (int)$row['id'],
                (string)($row['published_at'] ?? $row['created_at'] ?? ''),
                'fa-brands fa-x-twitter'
            );
        }
        return $results;
    }

    private function matches(string $text, string $keyword): bool
    {
        return mb_stripos($text, $keyword) !== false;
    }

    private function excerpt(string $text, int $length): string
    {
        $plain = strip_tags($text);
        $plain = html_entity_decode($plain, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $plain = preg_replace('/\s+/u', ' ', (string)$plain);
        return Helper::truncate(trim((string)$plain), $length);
    }

    /**
     * @return array<string,mixed>
     */
    private function result(string $type, string $label, string $title, string $excerpt, string $url, string $date, string $icon): array
    {
        return compact('type', 'label', 'title', 'excerpt', 'url', 'date', 'icon');
    }
}
