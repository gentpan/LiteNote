<?php
declare(strict_types=1);

namespace App\Services;

use App\Enums\PostStatus;
use App\Enums\Toggle;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Music;
use App\Models\Post;
use App\Models\Setting;
use App\Models\Talk;

final class ReadingSettingsService
{
    public const MODE_MIXED = 'mixed';
    public const MODE_POSTS = 'posts';
    public const MODE_TALKS = 'talks';

    public static function ensureDefaults(): void
    {
        Setting::ensureDefaults([
            ['k' => 'home_feed_mode', 'v' => self::MODE_MIXED, 'type' => 'select', 'label' => '首页展示模式', 'group_name' => 'reading', 'sort' => 1],
            ['k' => 'home_total_limit', 'v' => '12', 'type' => 'number', 'label' => '首页总数量', 'group_name' => 'reading', 'sort' => 2],
            ['k' => 'home_post_limit', 'v' => '8', 'type' => 'number', 'label' => '首页文章读取数', 'group_name' => 'reading', 'sort' => 3],
            ['k' => 'home_talk_limit', 'v' => '8', 'type' => 'number', 'label' => '首页说说读取数', 'group_name' => 'reading', 'sort' => 4],
            ['k' => 'home_fixed_posts', 'v' => '', 'type' => 'textarea', 'label' => '固定显示文章', 'group_name' => 'reading', 'sort' => 5],
            ['k' => 'home_fixed_talks', 'v' => '', 'type' => 'textarea', 'label' => '固定显示说说', 'group_name' => 'reading', 'sort' => 6],
            ['k' => 'post_list_per_page', 'v' => '5', 'type' => 'number', 'label' => '文章列表每页数量', 'group_name' => 'reading', 'sort' => 7],
        ]);
    }

    public static function settings(): array
    {
        try {
            self::ensureDefaults();
            $mode = (string) Setting::get('home_feed_mode', self::MODE_MIXED);
            $total = (int) Setting::get('home_total_limit', 12);
            $postLimit = (int) Setting::get('home_post_limit', 8);
            $talkLimit = (int) Setting::get('home_talk_limit', 8);
            $fixedPosts = (string) Setting::get('home_fixed_posts', '');
            $fixedTalks = (string) Setting::get('home_fixed_talks', '');
            $postsPerPage = (int) Setting::get('post_list_per_page', 5);
        } catch (\Throwable) {
            $mode = self::MODE_MIXED;
            $total = 12;
            $postLimit = 8;
            $talkLimit = 8;
            $fixedPosts = '';
            $fixedTalks = '';
            $postsPerPage = 5;
        }

        if (!in_array($mode, [self::MODE_MIXED, self::MODE_POSTS, self::MODE_TALKS], true)) {
            $mode = self::MODE_MIXED;
        }

        return [
            'mode' => $mode,
            'total' => self::clamp($total, 1, 60, 12),
            'post_limit' => self::clamp($postLimit, 0, 60, 8),
            'talk_limit' => self::clamp($talkLimit, 0, 60, 8),
            'fixed_posts' => self::tokens($fixedPosts, 3),
            'fixed_talks' => self::tokens($fixedTalks, 3),
            'posts_per_page' => self::clamp($postsPerPage, 1, 50, 5),
        ];
    }

    public static function postsPerPage(): int
    {
        return (int) self::settings()['posts_per_page'];
    }

    /**
     * @return array<int, array{type:string,time:int,item:object,fixed:bool}>
     */
    public static function homeFeedItems(): array
    {
        $settings = self::settings();
        $mode = (string)$settings['mode'];
        $total = (int)$settings['total'];

        $fixedPosts = $mode !== self::MODE_TALKS ? self::fixedPosts($settings['fixed_posts']) : [];
        $fixedTalks = $mode !== self::MODE_POSTS ? self::fixedTalks($settings['fixed_talks']) : [];

        $fixedPostIds = array_map(static fn(Post $post): int => (int)$post->id, $fixedPosts);
        $fixedTalkIds = array_map(static fn(Talk $talk): int => (int)$talk->id, $fixedTalks);

        $latestPosts = [];
        if ($mode !== self::MODE_TALKS) {
            $limit = max(0, (int)$settings['post_limit'] - count($fixedPosts));
            $latestPosts = self::latestPosts($limit, $fixedPostIds);
        }

        $latestTalks = [];
        if ($mode !== self::MODE_POSTS) {
            $limit = max(0, (int)$settings['talk_limit'] - count($fixedTalks));
            $latestTalks = self::latestTalks($limit, $fixedTalkIds);
        }

        self::attachTalkRelations(array_merge($fixedTalks, $latestTalks));

        $pinned = [];
        foreach ($fixedPosts as $post) {
            $pinned[] = self::feedItem('post', $post, true);
        }
        foreach ($fixedTalks as $talk) {
            $pinned[] = self::feedItem('talk', $talk, true);
        }

        $rest = [];
        foreach ($latestPosts as $post) {
            $rest[] = self::feedItem('post', $post, false);
        }
        foreach ($latestTalks as $talk) {
            $rest[] = self::feedItem('talk', $talk, false);
        }
        // 合并插件贡献的时间线条目(如 X 推文),与核心 post/talk 一起按时间排序。
        // 归为"非文章"内容:仅在非纯文章模式下混入。
        if ($mode !== self::MODE_POSTS) {
            foreach (\App\Services\Plugins\Registry::collectHomeFeedItems() as $pluginItem) {
                $rest[] = $pluginItem;
            }
        }
        usort($rest, static fn(array $a, array $b): int => $b['time'] <=> $a['time']);

        return array_slice(array_merge($pinned, $rest), 0, $total);
    }

    private static function clamp(int $value, int $min, int $max, int $fallback): int
    {
        if ($value < $min) {
            return $fallback;
        }
        return min($max, $value);
    }

    /**
     * @return string[]
     */
    private static function tokens(string $value, int $limit): array
    {
        $items = preg_split('/[\s,，]+/u', trim($value)) ?: [];
        $items = array_values(array_unique(array_filter(array_map('trim', $items))));
        return array_slice($items, 0, $limit);
    }

    /**
     * @param string[] $tokens
     * @return Post[]
     */
    private static function fixedPosts(array $tokens): array
    {
        $posts = [];
        $seen = [];
        foreach ($tokens as $token) {
            $post = ctype_digit($token) ? Post::find((int)$token) : Post::findBySlug($token);
            if (!$post || (string)$post->status !== PostStatus::Published->value) {
                continue;
            }
            $id = (int)$post->id;
            if (isset($seen[$id])) {
                continue;
            }
            self::attachCategory($post);
            $seen[$id] = true;
            $posts[] = $post;
        }
        return $posts;
    }

    /**
     * @param string[] $tokens
     * @return Talk[]
     */
    private static function fixedTalks(array $tokens): array
    {
        $talks = [];
        $seen = [];
        foreach ($tokens as $token) {
            if (!ctype_digit($token)) {
                continue;
            }
            $talk = Talk::find((int)$token);
            if (!$talk || (int)$talk->is_public !== Toggle::On->value) {
                continue;
            }
            $id = (int)$talk->id;
            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $talks[] = $talk;
        }
        return $talks;
    }

    /**
     * @param int[] $excludeIds
     * @return Post[]
     */
    private static function latestPosts(int $limit, array $excludeIds): array
    {
        if ($limit <= 0) {
            return [];
        }
        $params = [PostStatus::Published->value];
        $where = 'p.status = ?';
        if ($excludeIds !== []) {
            $ids = array_values(array_unique(array_map('intval', $excludeIds)));
            $where .= ' AND p.id NOT IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
            $params = array_merge($params, $ids);
        }

        $rows = Post::db()->fetchAll(
            "SELECT p.*, c.name AS __category_name, c.slug AS __category_slug
             FROM posts p
             LEFT JOIN categories c ON p.category_id = c.id
             WHERE {$where}
             ORDER BY p.is_top DESC, p.published_at DESC, p.id DESC
             LIMIT " . max(1, $limit),
            $params
        );

        $posts = [];
        foreach ($rows as $row) {
            $post = new Post($row);
            if (!empty($row['__category_name'])) {
                $post->setRelation('category', new Category([
                    'id' => $row['category_id'] ?? 0,
                    'name' => $row['__category_name'],
                    'slug' => $row['__category_slug'],
                ]));
            }
            $posts[] = $post;
        }
        return $posts;
    }

    /**
     * @param int[] $excludeIds
     * @return Talk[]
     */
    private static function latestTalks(int $limit, array $excludeIds): array
    {
        if ($limit <= 0) {
            return [];
        }
        $params = [Toggle::On->value];
        $where = 'is_public = ?';
        if ($excludeIds !== []) {
            $ids = array_values(array_unique(array_map('intval', $excludeIds)));
            $where .= ' AND id NOT IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
            $params = array_merge($params, $ids);
        }
        $rows = Talk::db()->fetchAll(
            "SELECT * FROM talk
             WHERE {$where}
             ORDER BY published_at DESC, created_at DESC, id DESC
             LIMIT " . max(1, $limit),
            $params
        );
        return array_map(static fn(array $row): Talk => new Talk($row), $rows);
    }

    private static function attachCategory(Post $post): void
    {
        if (!$post->getRelation('category') && $post->category_id) {
            $category = Category::find((int)$post->category_id);
            if ($category) {
                $post->setRelation('category', $category);
            }
        }
    }

    /**
     * @param Talk[] $talks
     */
    private static function attachTalkRelations(array $talks): void
    {
        $musicMap = Music::mapByIds(array_map(static fn(Talk $item): int => (int)($item->music_id ?? 0), $talks));
        foreach ($talks as $item) {
            $music = $musicMap[(int)($item->music_id ?? 0)] ?? null;
            if ($music && (int)$music->is_public === Toggle::On->value) {
                $item->setRelation('music', $music);
                $item->setRelation('comments', Comment::forMusic((int)$music->id));
            } else {
                $item->setRelation('comments', Comment::forTalk((int)$item->id));
            }
        }
    }

    private static function feedItem(string $type, object $item, bool $fixed): array
    {
        $timeValue = $type === 'talk' && method_exists($item, 'publishedAt')
            ? $item->publishedAt()
            : (string)($item->published_at ?? $item->created_at ?? '');

        return [
            'type' => $type,
            'time' => strtotime($timeValue) ?: 0,
            'item' => $item,
            'fixed' => $fixed,
        ];
    }
}
