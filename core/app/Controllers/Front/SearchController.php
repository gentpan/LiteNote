<?php
declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\Helper;
use App\Core\Request;
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
    private const MAX_EACH_TYPE = 1000;

    public function index(Request $request): string
    {
        $keyword = trim((string) $request->input('q', ''));
        $page = max(1, (int) $request->input('page', 1));
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

        $buckets = [
            $this->searchPosts($keyword),
            $this->searchPages($keyword),
            $this->searchTalks($keyword),
            $this->searchMusic($keyword),
            $this->searchXTweets($keyword),
        ];

        $all = [];
        $total = 0;
        foreach ($buckets as $bucket) {
            $total += $bucket['total'];
            foreach ($bucket['items'] as $item) {
                $all[] = $item;
            }
        }

        usort($all, static function (array $a, array $b): int {
            $ta = strtotime((string)($a['date'] ?? '')) ?: 0;
            $tb = strtotime((string)($b['date'] ?? '')) ?: 0;
            return $tb <=> $ta;
        });

        $offset = max(0, ($page - 1) * $perPage);
        $paged = array_slice($all, $offset, $perPage);

        // 只在最终需要展示的条目上才读取 Markdown / 生成摘要，避免搜索时全量读文件
        foreach ($paged as $i => $item) {
            $paged[$i]['excerpt'] = $this->excerptFor($item);
            unset($paged[$i]['entity']);
        }

        return [
            'items' => $paged,
            'total' => $total,
        ];
    }

    /**
     * @return array{items: array<int,array<string,mixed>>, total:int}
     */
    private function searchPosts(string $keyword): array
    {
        $found = Post::search($keyword, 1, self::MAX_EACH_TYPE);
        $items = [];
        foreach ($found['items'] as $post) {
            $items[] = [
                'type'   => 'post',
                'label'  => '文章',
                'title'  => (string) $post->title,
                'url'    => $post->getUrl(),
                'date'   => (string)($post->published_at ?? $post->created_at ?? ''),
                'icon'   => 'fa-regular fa-file-lines',
                'entity' => $post,
            ];
        }
        return ['items' => $items, 'total' => $found['total']];
    }

    /**
     * @return array{items: array<int,array<string,mixed>>, total:int}
     */
    private function searchPages(string $keyword): array
    {
        $like = '%' . $keyword . '%';
        $where = "title LIKE ? OR content LIKE ? OR markdown_content LIKE ?";
        $params = [$like, $like, $like];

        $total = (int) Page::db()->fetchColumn("SELECT COUNT(*) FROM pages WHERE {$where}", $params);
        $rows = Page::db()->fetchAll(
            "SELECT * FROM pages WHERE {$where} ORDER BY sort ASC, id DESC LIMIT " . self::MAX_EACH_TYPE,
            $params
        );

        $items = [];
        foreach ($rows as $row) {
            $page = new Page($row);
            $items[] = [
                'type'   => 'page',
                'label'  => '页面',
                'title'  => (string) $page->title,
                'url'    => $page->getUrl(),
                'date'   => (string)($page->updated_at ?? $page->created_at ?? ''),
                'icon'   => 'fa-regular fa-bookmark',
                'entity' => $page,
            ];
        }
        return ['items' => $items, 'total' => $total];
    }

    /**
     * @return array{items: array<int,array<string,mixed>>, total:int}
     */
    private function searchTalks(string $keyword): array
    {
        $like = '%' . $keyword . '%';
        $where = "is_public = ? AND (content LIKE ? OR mood LIKE ?)";
        $params = [Toggle::On->value, $like, $like];

        $total = (int) Talk::db()->fetchColumn("SELECT COUNT(*) FROM talk WHERE {$where}", $params);
        $rows = Talk::db()->fetchAll(
            "SELECT * FROM talk WHERE {$where} ORDER BY published_at DESC, created_at DESC, id DESC LIMIT " . self::MAX_EACH_TYPE,
            $params
        );

        $items = [];
        foreach ($rows as $row) {
            $content = (string)($row['content'] ?? '');
            $items[] = [
                'type'   => 'talk',
                'label'  => '滔客',
                'title'  => '滔客 #' . (int)$row['id'],
                'url'    => '/talk#talk-' . (int)$row['id'],
                'date'   => (string)($row['published_at'] ?? $row['created_at'] ?? ''),
                'icon'   => 'fa-regular fa-comments',
                'entity' => $content,
            ];
        }
        return ['items' => $items, 'total' => $total];
    }

    /**
     * @return array{items: array<int,array<string,mixed>>, total:int}
     */
    private function searchMusic(string $keyword): array
    {
        $like = '%' . $keyword . '%';
        $where = "title LIKE ? OR artist LIKE ? OR album LIKE ? OR lyrics LIKE ?";
        $params = [$like, $like, $like, $like];

        $total = (int) Music::db()->fetchColumn("SELECT COUNT(*) FROM music WHERE {$where}", $params);
        $rows = Music::db()->fetchAll(
            "SELECT * FROM music WHERE {$where} ORDER BY published_at DESC, sort ASC, id DESC LIMIT " . self::MAX_EACH_TYPE,
            $params
        );

        $items = [];
        foreach ($rows as $row) {
            $title = (string)($row['title'] ?? ('音乐 #' . (int)$row['id']));
            $artist = trim((string)($row['artist'] ?? ''));
            $album = (string)($row['album'] ?? '');
            $items[] = [
                'type'   => 'music',
                'label'  => '音乐',
                'title'  => $title,
                'url'    => '/music#music-' . (int)$row['id'],
                'date'   => (string)($row['published_at'] ?? $row['created_at'] ?? ''),
                'icon'   => 'fa-solid fa-music',
                'entity' => trim($artist . ($artist !== '' && $album !== '' ? ' · ' : '') . $album),
            ];
        }
        return ['items' => $items, 'total' => $total];
    }

    /**
     * @return array{items: array<int,array<string,mixed>>, total:int}
     */
    private function searchXTweets(string $keyword): array
    {
        $like = '%' . $keyword . '%';
        try {
            $total = (int) Post::db()->fetchColumn(
                'SELECT COUNT(*) FROM x_tweets WHERE is_public = ? AND (content LIKE ? OR tweet_author_name LIKE ? OR tweet_author_handle LIKE ?)',
                [Toggle::On->value, $like, $like, $like]
            );
            $rows = Post::db()->fetchAll(
                'SELECT * FROM x_tweets WHERE is_public = ? AND (content LIKE ? OR tweet_author_name LIKE ? OR tweet_author_handle LIKE ?) ORDER BY published_at DESC, created_at DESC, id DESC LIMIT ' . self::MAX_EACH_TYPE,
                [Toggle::On->value, $like, $like, $like]
            );
        } catch (\Throwable) {
            return ['items' => [], 'total' => 0];
        }

        $items = [];
        foreach ($rows as $row) {
            $content = (string)($row['content'] ?? '');
            $items[] = [
                'type'   => 'x',
                'label'  => 'X',
                'title'  => 'X #' . (int)$row['id'],
                'url'    => '/x#xmark-' . (int)$row['id'],
                'date'   => (string)($row['published_at'] ?? $row['created_at'] ?? ''),
                'icon'   => 'fa-brands fa-x-twitter',
                'entity' => $content,
            ];
        }
        return ['items' => $items, 'total' => $total];
    }

    private function excerptFor(array $item): string
    {
        $type = $item['type'] ?? '';
        $entity = $item['entity'] ?? null;

        if ($type === 'post' && $entity instanceof Post) {
            return $entity->summaryOrContent(180);
        }

        if ($type === 'page' && $entity instanceof Page) {
            $text = (string)($entity->content ?? '');
            if ($text === '') {
                $text = (string)($entity->markdown_content ?? '');
            }
            return $this->excerpt($text, 180);
        }

        if (is_string($entity)) {
            return $this->excerpt($entity, 180);
        }

        return '';
    }

    private function excerpt(string $text, int $length): string
    {
        $plain = strip_tags($text);
        $plain = html_entity_decode($plain, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $plain = preg_replace('/\s+/u', ' ', (string)$plain);
        return Helper::truncate(trim((string)$plain), $length);
    }
}
