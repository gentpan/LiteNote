<?php
declare(strict_types=1);

namespace App\Services\ActivityAdapters;

use App\Models\ActivityIntegration;
use App\Services\TweetFetchService;

final class XAdapter extends BaseAdapter
{
    public function provider(): string
    {
        return 'x';
    }

    public function sync(ActivityIntegration $integration): array
    {
        $urls = $this->manualUrls($integration);
        $token = trim((string)$integration->access_token);
        $username = ltrim($this->meta($integration, 'username'), '@');
        $profile = null;
        if ($token !== '' && $username !== '') {
            $profile = $this->fetchUserProfile($token, $username);
            if ($profile) {
                $integration->updateMetadata(['x_profile' => $profile]);
            }
            $urls = array_merge($urls, $this->latestTweetUrls(
                $token,
                (string)($profile['id'] ?? ''),
                $username,
                max(5, min(100, (int)$this->meta($integration, 'limit', '100'))),
                max(0, min(100, (int)$this->meta($integration, 'pages', '10'))),
                $this->boolMeta($integration, 'include_retweets', false)
            ));
        }
        $urls = array_values(array_unique(array_filter($urls)));
        if (!$urls) {
            throw new \RuntimeException('请填写 X Bearer Token + 用户名，或在 Tweet URLs 中填写链接');
        }

        $fetcher = new TweetFetchService($token);
        $created = $updated = $skipped = 0;
        foreach ($urls as $url) {
            try {
                $tweet = $fetcher->fetch($url);
            } catch (\Throwable) {
                $skipped++;
                continue;
            }
            $id = trim((string)($tweet['id'] ?? ''));
            $text = trim((string)($tweet['text'] ?? ''));
            if ($id === '' || $text === '') {
                $skipped++;
                continue;
            }
            $externalId = 'x:tweet:' . $id;
            $before = $this->exists('x', $externalId);
            $this->activities->record([
                'type' => 'social',
                'action' => 'posted',
                'source' => 'x',
                'external_id' => $externalId,
                'title' => '发布了 X：' . mb_strimwidth($text, 0, 64, '...', 'UTF-8'),
                'content' => $text,
                'url' => (string)($tweet['url'] ?? $url),
                'happened_at' => (string)($tweet['posted_at'] ?? date('Y-m-d H:i:s')),
                'metadata' => [
                    'tweet' => $tweet,
                    'likes' => (int)($tweet['likes_count'] ?? 0),
                    'reposts' => (int)($tweet['reposts_count'] ?? 0),
                    'replies' => (int)($tweet['replies_count'] ?? 0),
                    'views' => (int)($tweet['views_count'] ?? 0),
                    'images' => $tweet['images'] ?? [],
                ],
            ]);
            $before ? $updated++ : $created++;
        }

        return $this->result($created, $updated, $skipped, 'X 同步完成');
    }

    private function manualUrls(ActivityIntegration $integration): array
    {
        $raw = $this->meta($integration, 'tweet_urls');
        if ($raw === '') {
            return [];
        }
        return preg_split('/[\s,]+/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    private function fetchUserProfile(string $token, string $username): ?array
    {
        $user = $this->http->getJson(
            'https://api.x.com/2/users/by/username/' . rawurlencode($username) . '?' . http_build_query([
                'user.fields' => 'description,profile_image_url,public_metrics,url,verified,verified_type',
            ]),
            ['Authorization: Bearer ' . $token],
            15
        );
        $data = is_array($user['data'] ?? null) ? $user['data'] : [];
        $userId = (string)($data['id'] ?? '');
        if ($userId === '') {
            return null;
        }

        $metrics = is_array($data['public_metrics'] ?? null) ? $data['public_metrics'] : [];
        $handle = ltrim((string)($data['username'] ?? $username), '@');
        return [
            'id' => $userId,
            'name' => (string)($data['name'] ?? ''),
            'username' => $handle,
            'avatar' => (string)($data['profile_image_url'] ?? ''),
            'description' => (string)($data['description'] ?? ''),
            'url' => (string)($data['url'] ?? ''),
            'profile_url' => $handle !== '' ? 'https://x.com/' . rawurlencode($handle) : '',
            'verified' => !empty($data['verified']),
            'verified_type' => (string)($data['verified_type'] ?? ''),
            'followers_count' => (int)($metrics['followers_count'] ?? 0),
            'following_count' => (int)($metrics['following_count'] ?? 0),
            'tweet_count' => (int)($metrics['tweet_count'] ?? 0),
            'listed_count' => (int)($metrics['listed_count'] ?? 0),
            'fetched_at' => date('Y-m-d H:i:s'),
        ];
    }

    private function latestTweetUrls(string $token, string $userId, string $username, int $limit, int $maxPages, bool $includeRetweets): array
    {
        if ($userId === '') {
            $profile = $this->fetchUserProfile($token, $username);
            $userId = (string)($profile['id'] ?? '');
        }
        if ($userId === '') {
            return [];
        }

        $urls = [];
        $paginationToken = '';
        $page = 0;
        do {
            $page++;
            $params = [
                'max_results' => $limit,
                'tweet.fields' => 'created_at,public_metrics,attachments,entities,referenced_tweets',
                'expansions' => 'attachments.media_keys,author_id',
                'media.fields' => 'url,preview_image_url,type',
                'user.fields' => 'name,username,profile_image_url,verified',
            ];
            if ($paginationToken !== '') {
                $params['pagination_token'] = $paginationToken;
            }
            $tweets = $this->http->getJson(
                'https://api.x.com/2/users/' . rawurlencode($userId) . '/tweets?' . http_build_query($params),
                ['Authorization: Bearer ' . $token],
                20
            );
            foreach ((array)($tweets['data'] ?? []) as $tweet) {
                if (!$includeRetweets && $this->isRetweetOnly($tweet)) {
                    continue;
                }
                if (is_array($tweet) && !empty($tweet['id'])) {
                    $urls[] = 'https://x.com/' . rawurlencode($username) . '/status/' . rawurlencode((string)$tweet['id']);
                }
            }
            $meta = is_array($tweets['meta'] ?? null) ? $tweets['meta'] : [];
            $paginationToken = (string)($meta['next_token'] ?? '');
        } while ($paginationToken !== '' && ($maxPages === 0 || $page < $maxPages));

        return $urls;
    }

    private function isRetweetOnly(mixed $tweet): bool
    {
        if (!is_array($tweet)) {
            return false;
        }

        $references = is_array($tweet['referenced_tweets'] ?? null) ? $tweet['referenced_tweets'] : [];
        if ($references === []) {
            return false;
        }

        $types = [];
        foreach ($references as $reference) {
            if (is_array($reference) && isset($reference['type'])) {
                $types[] = (string)$reference['type'];
            }
        }

        return $types !== [] && in_array('retweeted', $types, true) && !in_array('quoted', $types, true);
    }

    private function exists(string $source, string $externalId): bool
    {
        return (bool)\App\Models\Activity::db()->fetchOne(
            'SELECT id FROM activities WHERE source = ? AND external_id = ? LIMIT 1',
            [$source, $externalId]
        );
    }
}
