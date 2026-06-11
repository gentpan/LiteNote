<?php
declare(strict_types=1);

namespace App\Services;

use App\Enums\PostStatus;
use App\Enums\Toggle;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Music;
use App\Models\Post;
use App\Models\Talk;

final class ReadingSettingsService
{
    private const POSTS_PER_PAGE = 10;

    public static function ensureDefaults(): void
    {
    }

    public static function settings(): array
    {
        return [
            'posts_per_page' => self::POSTS_PER_PAGE,
        ];
    }

    public static function postsPerPage(): int
    {
        return (int) self::settings()['posts_per_page'];
    }

    /**
     * @return array<int, array{type:string,time:int,item:object,fixed:bool}>
     */
    public static function homeFeedItems(int $offset = 0, ?int $limit = null): array
    {
        $settings = self::settings();
        $total = $limit ?? 10;
        $offset = max(0, $offset);
        $wanted = $offset + $total + 10;

        $latestPosts = [];
        $latestPosts = self::latestPosts($wanted, []);

        $latestTalks = [];
        $latestTalks = self::latestTalks($wanted, []);

        self::attachTalkRelations($latestTalks);

        $rest = [];
        foreach ($latestPosts as $post) {
            $rest[] = self::feedItem('post', $post, false);
        }
        foreach ($latestTalks as $talk) {
            $rest[] = self::feedItem('talk', $talk, false);
        }
        // 合并插件贡献的时间线条目(如 X 推文),与核心 post/talk 一起按时间排序。
        foreach (\App\Services\Plugins\Registry::collectHomeFeedItems() as $pluginItem) {
            $rest[] = $pluginItem;
        }
        usort($rest, static fn(array $a, array $b): int => $b['time'] <=> $a['time']);

        return array_slice($rest, $offset, $total);
    }

    public static function homeFeedHasMore(int $offset, int $limit): bool
    {
        return count(self::homeFeedItems($offset + $limit, 1)) > 0;
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
            "SELECT p.*,
                    (
                        SELECT COUNT(*)
                        FROM comments cm
                        WHERE cm.post_id = p.id AND cm.status = 'approved'
                    ) AS __comments_count,
                    c.name AS __category_name, c.slug AS __category_slug
             FROM posts p
             LEFT JOIN categories c ON p.category_id = c.id
             WHERE {$where}
             ORDER BY p.is_top DESC, p.published_at DESC, p.id DESC
             LIMIT " . max(1, $limit),
            $params
        );

        $posts = [];
        foreach ($rows as $row) {
            $row['comments_count'] = (int)($row['__comments_count'] ?? 0);
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
        return Talk::withRealCommentCounts(array_map(static fn(array $row): Talk => new Talk($row), $rows));
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
                $comments = Comment::forMusic((int)$music->id);
                $music->comments_count = count($comments);
                $item->setRelation('comments', $comments);
            } else {
                $comments = Comment::forTalk((int)$item->id);
                $item->comments_count = count($comments);
                $item->setRelation('comments', $comments);
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
