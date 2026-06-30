<?php
declare(strict_types=1);

namespace App\Services;

use App\Enums\CommentStatus;
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
     * @return array{items: array<int, array{type:string,time:int,item:object,fixed:bool}>, hasMore: bool}
     */
    public static function homeFeedPage(int $offset = 0, ?int $limit = null): array
    {
        $limit = $limit ?? 10;
        $offset = max(0, $offset);
        $wanted = min($offset + $limit + 1, 200);

        $latestPosts = self::latestPosts($wanted, []);
        $latestTalks = self::latestTalks($wanted, []);
        self::attachTalkRelations($latestTalks);

        $rest = [];
        foreach ($latestPosts as $post) {
            $rest[] = self::feedItem('post', $post, false);
        }
        foreach ($latestTalks as $talk) {
            $rest[] = self::feedItem('talk', $talk, false);
        }
        foreach (\App\Services\Plugins\Registry::collectHomeFeedItems() as $pluginItem) {
            $rest[] = $pluginItem;
        }
        usort($rest, static fn(array $a, array $b): int => $b['time'] <=> $a['time']);

        $slice = array_slice($rest, $offset, $limit + 1);
        $hasMore = count($slice) > $limit;
        if ($hasMore) {
            array_pop($slice);
        }

        return ['items' => $slice, 'hasMore' => $hasMore];
    }

    /**
     * @return array<int, array{type:string,time:int,item:object,fixed:bool}>
     */
    public static function homeFeedItems(int $offset = 0, ?int $limit = null): array
    {
        return self::homeFeedPage($offset, $limit)['items'];
    }

    public static function homeFeedHasMore(int $offset, int $limit): bool
    {
        return self::homeFeedPage($offset, $limit)['hasMore'];
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
                    COALESCE(cc.total, 0) AS __comments_count,
                    c.name AS __category_name, c.slug AS __category_slug
             FROM posts p
             LEFT JOIN categories c ON p.category_id = c.id
             LEFT JOIN (
                 SELECT post_id, COUNT(*) AS total
                 FROM comments
                 WHERE status = 'approved'
                 GROUP BY post_id
             ) cc ON cc.post_id = p.id
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

        $talkIds = [];
        $musicIds = [];
        foreach ($talks as $item) {
            $music = $musicMap[(int)($item->music_id ?? 0)] ?? null;
            if ($music && (int)$music->is_public === Toggle::On->value) {
                $item->setRelation('music', $music);
                $musicIds[] = (int)$music->id;
            } else {
                $talkIds[] = (int)$item->id;
            }
        }

        $commentsByTalk = self::batchComments('talk_id', $talkIds);
        $commentsByMusic = self::batchComments('music_id', $musicIds);

        foreach ($talks as $item) {
            $music = $musicMap[(int)($item->music_id ?? 0)] ?? null;
            if ($music && (int)$music->is_public === Toggle::On->value) {
                $comments = $commentsByMusic[(int)$music->id] ?? [];
                $music->comments_count = count($comments);
                $item->setRelation('comments', $comments);
            } else {
                $comments = $commentsByTalk[(int)$item->id] ?? [];
                $item->comments_count = count($comments);
                $item->setRelation('comments', $comments);
            }
        }
    }

    /**
     * @return array<int, Comment[]>
     */
    private static function batchComments(string $column, array $ids): array
    {
        $result = [];
        if ($ids === []) {
            return $result;
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $rows = Comment::db()->fetchAll(
            "SELECT * FROM comments WHERE {$column} IN ({$placeholders}) AND status = ? ORDER BY id ASC",
            [...$ids, CommentStatus::Approved->value]
        );
        foreach ($rows as $row) {
            $key = (int)$row[$column];
            $result[$key][] = new Comment($row);
        }
        return $result;
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
