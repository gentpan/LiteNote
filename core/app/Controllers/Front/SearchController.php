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
use App\Services\PluginManager;
use App\Services\SearchIndexService;

class SearchController
{
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

        if (SearchIndexService::available()) {
            return $this->searchWithFts($keyword, $page, $perPage);
        }

        return $this->searchLegacy($keyword, $page, $perPage);
    }

    /**
     * @return array{items: array<int,array<string,mixed>>, total:int}
     */
    private function searchWithFts(string $keyword, int $page, int $perPage): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        $found = SearchIndexService::search($keyword, $perPage, $offset);
        $items = $this->mapSearchHitsBatch($found['items']);

        foreach ($items as $i => $item) {
            $items[$i]['excerpt'] = $this->excerptFor($item);
            unset($items[$i]['entity']);
        }

        return ['items' => $items, 'total' => $found['total']];
    }

    /**
     * @param array<int, array{entity_type?:string, entity_id?:int|string, title?:string}> $rows
     * @return array<int, array<string, mixed>>
     */
    private function mapSearchHitsBatch(array $rows): array
    {
        $idsByType = [];
        foreach ($rows as $row) {
            $type = (string)($row['entity_type'] ?? '');
            $id = (int)($row['entity_id'] ?? 0);
            if ($type !== '' && $id > 0) {
                $idsByType[$type][] = $id;
            }
        }

        $posts = [];
        foreach (Post::whereInIds($idsByType['post'] ?? []) as $post) {
            $posts[(int)$post->id] = $post;
        }
        $pages = [];
        foreach (Page::whereInIds($idsByType['page'] ?? []) as $page) {
            $pages[(int)$page->id] = $page;
        }
        $talks = [];
        foreach (Talk::whereInIds($idsByType['talk'] ?? []) as $talk) {
            $talks[(int)$talk->id] = $talk;
        }
        $musicItems = [];
        foreach (Music::whereInIds($idsByType['music'] ?? []) as $music) {
            $musicItems[(int)$music->id] = $music;
        }

        $items = [];
        foreach ($rows as $row) {
            $type = (string)($row['entity_type'] ?? '');
            $id = (int)($row['entity_id'] ?? 0);
            $title = (string)($row['title'] ?? '');
            $item = match ($type) {
                'post' => isset($posts[$id]) ? $this->mapPostHit($id, $title, $posts[$id]) : null,
                'page' => isset($pages[$id]) ? $this->mapPageHit($id, $title, $pages[$id]) : null,
                'talk' => isset($talks[$id]) ? $this->mapTalkHit($id, $title, $talks[$id]) : null,
                'music' => isset($musicItems[$id]) ? $this->mapMusicHit($id, $title, $musicItems[$id]) : null,
                'x' => PluginManager::isEnabled('x') ? $this->mapXHit($id, $title) : null,
                default => null,
            };
            if ($item !== null) {
                $items[] = $item;
            }
        }
        return $items;
    }

    /**
     * @return array{items: array<int,array<string,mixed>>, total:int}
     */
    private function searchLegacy(string $keyword, int $page, int $perPage): array
    {
        $buckets = [
            $this->searchPosts($keyword, $page, $perPage),
            $this->searchPages($keyword, $page, $perPage),
            $this->searchTalks($keyword, $page, $perPage),
            $this->searchMusic($keyword, $page, $perPage),
        ];
        if (PluginManager::isEnabled('x')) {
            $buckets[] = $this->searchXTweets($keyword, $page, $perPage);
        }

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

        foreach ($paged as $i => $item) {
            $paged[$i]['excerpt'] = $this->excerptFor($item);
            unset($paged[$i]['entity']);
        }

        return ['items' => $paged, 'total' => $total];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function mapSearchHit(string $type, int $id, string $title): ?array
    {
        if ($id <= 0) {
            return null;
        }

        return match ($type) {
            'post' => $this->mapPostHit($id, $title),
            'page' => $this->mapPageHit($id, $title),
            'talk' => $this->mapTalkHit($id, $title),
            'music' => $this->mapMusicHit($id, $title),
            'x' => PluginManager::isEnabled('x') ? $this->mapXHit($id, $title) : null,
            default => null,
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    private function mapPostHit(int $id, string $title, ?Post $post = null): ?array
    {
        $post = $post ?? Post::find($id);
        if (!$post || (string)$post->status !== PostStatus::Published->value || (int)($post->is_private ?? 0) === 1) {
            return null;
        }
        return [
            'type' => 'post',
            'label' => '文章',
            'title' => (string)$post->title,
            'url' => $post->getUrl(),
            'date' => (string)($post->published_at ?? $post->created_at ?? ''),
            'icon' => 'fa-regular fa-file-lines',
            'entity' => $post,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function mapPageHit(int $id, string $title, ?Page $page = null): ?array
    {
        $page = $page ?? Page::find($id);
        if (!$page) {
            return null;
        }
        return [
            'type' => 'page',
            'label' => '页面',
            'title' => (string)$page->title,
            'url' => $page->getUrl(),
            'date' => (string)($page->updated_at ?? $page->created_at ?? ''),
            'icon' => 'fa-regular fa-bookmark',
            'entity' => $page,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function mapTalkHit(int $id, string $title, ?Talk $talk = null): ?array
    {
        $talk = $talk ?? Talk::find($id);
        if (!$talk || (int)($talk->is_public ?? 0) !== Toggle::On->value) {
            return null;
        }
        $content = (string)($talk->content ?? '');
        return [
            'type' => 'talk',
            'label' => '滔客',
            'title' => $title !== '' ? $title : '滔客 #' . $id,
            'url' => '/talk#talk-' . $id,
            'date' => (string)($talk->published_at ?? $talk->created_at ?? ''),
            'icon' => 'fa-regular fa-comments',
            'entity' => $content,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function mapMusicHit(int $id, string $title, ?Music $music = null): ?array
    {
        $music = $music ?? Music::find($id);
        if (!$music) {
            return null;
        }
        $artist = trim((string)($music->artist ?? ''));
        $album = (string)($music->album ?? '');
        return [
            'type' => 'music',
            'label' => '音乐',
            'title' => (string)$music->title,
            'url' => '/music#music-' . $id,
            'date' => (string)($music->published_at ?? $music->created_at ?? ''),
            'icon' => 'fa-solid fa-music',
            'entity' => trim($artist . ($artist !== '' && $album !== '' ? ' · ' : '') . $album),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function mapXHit(int $id, string $title): ?array
    {
        if (!PluginManager::isEnabled('x')) {
            return null;
        }
        try {
            $row = Post::db()->fetchOne('SELECT * FROM x_tweets WHERE id = ? AND is_public = ?', [$id, Toggle::On->value]);
        } catch (\Throwable) {
            return null;
        }
        if (!$row) {
            return null;
        }
        return [
            'type' => 'x',
            'label' => 'X',
            'title' => $title !== '' ? $title : 'X #' . $id,
            'url' => '/x#xmark-' . $id,
            'date' => (string)($row['published_at'] ?? $row['created_at'] ?? ''),
            'icon' => 'fa-brands fa-x-twitter',
            'entity' => (string)($row['content'] ?? ''),
        ];
    }

    /**
     * @return array{items: array<int,array<string,mixed>>, total:int}
     */
    private function searchPosts(string $keyword, int $page, int $perPage): array
    {
        $found = Post::search($keyword, $page, $perPage);
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
    private function searchPages(string $keyword, int $page, int $perPage): array
    {
        $like = '%' . $keyword . '%';
        $where = "title LIKE ? OR content LIKE ? OR markdown_content LIKE ?";
        $params = [$like, $like, $like];
        $offset = max(0, ($page - 1) * $perPage);

        $total = (int) Page::db()->fetchColumn("SELECT COUNT(*) FROM pages WHERE {$where}", $params);
        $rows = Page::db()->fetchAll(
            "SELECT * FROM pages WHERE {$where} ORDER BY sort ASC, id DESC LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        $items = [];
        foreach ($rows as $row) {
            $pageModel = new Page($row);
            $items[] = [
                'type'   => 'page',
                'label'  => '页面',
                'title'  => (string) $pageModel->title,
                'url'    => $pageModel->getUrl(),
                'date'   => (string)($pageModel->updated_at ?? $pageModel->created_at ?? ''),
                'icon'   => 'fa-regular fa-bookmark',
                'entity' => $pageModel,
            ];
        }
        return ['items' => $items, 'total' => $total];
    }

    /**
     * @return array{items: array<int,array<string,mixed>>, total:int}
     */
    private function searchTalks(string $keyword, int $page, int $perPage): array
    {
        $like = '%' . $keyword . '%';
        $where = "is_public = ? AND (content LIKE ? OR mood LIKE ?)";
        $params = [Toggle::On->value, $like, $like];
        $offset = max(0, ($page - 1) * $perPage);

        $total = (int) Talk::db()->fetchColumn("SELECT COUNT(*) FROM talk WHERE {$where}", $params);
        $rows = Talk::db()->fetchAll(
            "SELECT * FROM talk WHERE {$where} ORDER BY published_at DESC, created_at DESC, id DESC LIMIT {$perPage} OFFSET {$offset}",
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
    private function searchMusic(string $keyword, int $page, int $perPage): array
    {
        $like = '%' . $keyword . '%';
        $where = "title LIKE ? OR artist LIKE ? OR album LIKE ? OR lyrics LIKE ?";
        $params = [$like, $like, $like, $like];
        $offset = max(0, ($page - 1) * $perPage);

        $total = (int) Music::db()->fetchColumn("SELECT COUNT(*) FROM music WHERE {$where}", $params);
        $rows = Music::db()->fetchAll(
            "SELECT * FROM music WHERE {$where} ORDER BY published_at DESC, sort ASC, id DESC LIMIT {$perPage} OFFSET {$offset}",
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
    private function searchXTweets(string $keyword, int $page, int $perPage): array
    {
        if (!PluginManager::isEnabled('x')) {
            return ['items' => [], 'total' => 0];
        }
        $like = '%' . $keyword . '%';
        $offset = max(0, ($page - 1) * $perPage);
        try {
            $total = (int) Post::db()->fetchColumn(
                'SELECT COUNT(*) FROM x_tweets WHERE is_public = ? AND (content LIKE ? OR tweet_author_name LIKE ? OR tweet_author_handle LIKE ?)',
                [Toggle::On->value, $like, $like, $like]
            );
            $rows = Post::db()->fetchAll(
                'SELECT * FROM x_tweets WHERE is_public = ? AND (content LIKE ? OR tweet_author_name LIKE ? OR tweet_author_handle LIKE ?) ORDER BY published_at DESC, created_at DESC, id DESC LIMIT ' . $perPage . ' OFFSET ' . $offset,
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
