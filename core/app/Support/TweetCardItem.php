<?php
declare(strict_types=1);

namespace App\Support;

use App\Models\Activity;

final class TweetCardItem
{
    private Activity $activity;
    private array $tweetData;

    public function __construct(Activity $activity)
    {
        $this->activity = $activity;
        $metadata = $activity->metadata();
        $tweet = is_array($metadata['tweet'] ?? null) ? $metadata['tweet'] : [];
        $tweet['likes_count'] = (int)($tweet['likes_count'] ?? $metadata['likes'] ?? 0);
        $tweet['reposts_count'] = (int)($tweet['reposts_count'] ?? $metadata['reposts'] ?? 0);
        $tweet['replies_count'] = (int)($tweet['replies_count'] ?? $metadata['replies'] ?? 0);
        $tweet['views_count'] = (int)($tweet['views_count'] ?? $metadata['views'] ?? 0);
        if (empty($tweet['images']) && is_array($metadata['images'] ?? null)) {
            $tweet['images'] = $metadata['images'];
        }
        if (empty($tweet['text'])) {
            $tweet['text'] = (string)($activity->content ?? '');
        }
        if (empty($tweet['url'])) {
            $tweet['url'] = (string)($activity->url ?? '');
        }
        if (empty($tweet['posted_at'])) {
            $tweet['posted_at'] = (string)($activity->happened_at ?? '');
        }
        $this->tweetData = $tweet;
    }

    public function __get(string $name): mixed
    {
        return match ($name) {
            'id' => 'activity-' . (string)($this->activity->id ?? ''),
            'content' => (string)($this->activity->content ?? ''),
            'tweet_url' => $this->tweetUrl(),
            'tweet_id' => $this->tweetId(),
            'tweet_author_name' => (string)($this->tweetData['author_name'] ?? ''),
            'tweet_author_handle' => (string)($this->tweetData['author_handle'] ?? ''),
            'tweet_author_avatar' => (string)($this->tweetData['author_avatar'] ?? ''),
            'tweet_author_verified' => !empty($this->tweetData['author_verified']) ? 1 : 0,
            'tweet_posted_at' => (string)($this->tweetData['posted_at'] ?? ''),
            'tweet_likes_count' => (int)($this->tweetData['likes_count'] ?? 0),
            'tweet_reposts_count' => (int)($this->tweetData['reposts_count'] ?? 0),
            default => $this->activity->{$name} ?? null,
        };
    }

    public function tweetData(): array
    {
        return $this->tweetData;
    }

    public function tweetUrl(): string
    {
        $url = trim((string)($this->tweetData['url'] ?? $this->activity->url ?? ''));
        if ($url !== '') {
            return $url;
        }
        $id = $this->tweetId();
        return $id !== '' ? 'https://x.com/i/web/status/' . $id : '';
    }

    public function tweetId(): string
    {
        $id = trim((string)($this->tweetData['id'] ?? ''));
        if ($id !== '') {
            return $id;
        }
        $externalId = (string)($this->activity->external_id ?? '');
        return str_starts_with($externalId, 'x:tweet:') ? substr($externalId, 8) : '';
    }

    public function tweetHandle(): string
    {
        return ltrim(trim((string)($this->tweetData['author_handle'] ?? '')), '@');
    }

    public function getImages(): array
    {
        $images = $this->tweetData['images'] ?? [];
        return is_array($images) ? array_values(array_filter(array_map('trim', $images))) : [];
    }

    public function publishedAt(): string
    {
        return (string)($this->tweetData['posted_at'] ?? $this->activity->happened_at ?? date('Y-m-d H:i:s'));
    }
}
