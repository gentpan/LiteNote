<?php
declare(strict_types=1);

namespace LiteNotePlugin\X\Models;

use App\Models\Comment;
use App\Models\Model;

/**
 * 独立的推文模型(线B 彻底拆表后承接原 talk 表 post_type=tweet 的数据)。
 *
 * 表 x_tweets 与核心 talk 表平级(同在主库),字段对应原 talk 的 tweet_* + 通用
 * content/images/is_public/published_at/likes_count/comments_count。本地评论复用核心
 * comments 表的 x_tweet_id 列。提供与原 Talk 推文一致的视图接口,供 x-card 渲染。
 */
final class XTweet extends Model
{
    protected static string $table = 'x_tweets';
    protected static array $sortable = ['id', 'created_at', 'published_at', 'likes_count', 'comments_count'];

    public static function paginate(int $page = 1, int $perPage = 20, string $orderBy = 'published_at DESC, created_at DESC, id DESC', ?string $whereSql = null, array $params = []): array
    {
        if ($whereSql === null) {
            $whereSql = 'is_public = 1';
        }
        return parent::paginate($page, $perPage, $orderBy, $whereSql, $params);
    }

    /** 供主题 x-card 复用 Talk 的判定接口。 */
    public function isTweet(): bool
    {
        return true;
    }

    public function publishedAt(): string
    {
        return (string)($this->published_at ?: $this->created_at ?: date('Y-m-d H:i:s'));
    }

    public function tweetUrl(): string
    {
        $url = trim((string)($this->tweet_url ?? ''));
        if ($url !== '') {
            return $url;
        }
        $id = $this->tweetId();
        return $id !== '' ? 'https://x.com/i/web/status/' . $id : '';
    }

    public function tweetId(): string
    {
        $id = trim((string)($this->tweet_id ?? ''));
        if ($id !== '') {
            return $id;
        }
        $url = trim((string)($this->tweet_url ?? ''));
        if ($url !== '' && preg_match('~/status(?:es)?/([0-9]+)~', $url, $m)) {
            return $m[1];
        }
        return '';
    }

    public function tweetHandle(): string
    {
        $data = $this->tweetData();
        if (!empty($data['author_handle'])) {
            return ltrim((string)$data['author_handle'], '@');
        }
        return ltrim(trim((string)($this->tweet_author_handle ?? '')), '@');
    }

    public function tweetData(): array
    {
        $json = trim((string)($this->tweet_data ?? ''));
        if ($json === '') {
            return [];
        }
        $data = json_decode($json, true);
        return is_array($data) ? $data : [];
    }

    public function getImages(): array
    {
        if (empty($this->images)) {
            return [];
        }
        return array_values(array_filter(array_map('trim', explode(',', (string)$this->images))));
    }

    /** 推文无 #话题关键词,返回空数组(供 talk-local-engagement 复用接口)。 */
    public function getKeywords(): array
    {
        return [];
    }

    public function contentWithoutKeywords(): string
    {
        return trim((string)($this->content ?? ''));
    }

    /** 加载本地评论(复用核心 comments 表的 x_tweet_id 维度)。 */
    public function loadComments(): void
    {
        $this->setRelation('comments', Comment::forXTweet((int)$this->id));
    }

    public static function extractTweetId(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (preg_match('/^[0-9]{8,40}$/', $value)) {
            return $value;
        }
        if (preg_match('~/status(?:es)?/([0-9]+)~', $value, $m)) {
            return $m[1];
        }
        return '';
    }

    public static function like(int $id): int
    {
        if ($id <= 0) {
            return 0;
        }
        self::db()->query('UPDATE x_tweets SET likes_count = COALESCE(likes_count, 0) + 1 WHERE id = ?', [$id]);
        return (int) self::db()->fetchColumn('SELECT COALESCE(likes_count, 0) FROM x_tweets WHERE id = ?', [$id]);
    }
}
