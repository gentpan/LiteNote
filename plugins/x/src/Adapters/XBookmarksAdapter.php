<?php
declare(strict_types=1);

namespace LiteNotePlugin\X\Adapters;

use App\Core\Config;
use App\Core\Http;
use App\Models\ActivityIntegration;
use App\Services\ActivityAdapters\BaseAdapter;
use LiteNotePlugin\X\Services\TweetFetchService;

final class XBookmarksAdapter extends BaseAdapter
{
    private ?TweetFetchService $imageFetcher = null;

    public function provider(): string
    {
        return 'x_bookmarks';
    }

    private function imageFetcher(): TweetFetchService
    {
        return $this->imageFetcher ??= new TweetFetchService();
    }

    public function sync(ActivityIntegration $integration): array
    {
        $token = $this->validAccessToken($integration);
        if ($token === '') {
            throw new \RuntimeException('请填写 X OAuth 2.0 用户 Access Token，需要 bookmark.read 权限');
        }

        $userId = trim($this->meta($integration, 'user_id'));
        $username = ltrim($this->meta($integration, 'username'), '@');
        if ($userId === '' && $username !== '') {
            $userId = $this->lookupUserId($token, $username);
        }
        if ($userId === '') {
            throw new \RuntimeException('请填写 X 用户 ID，或填写用户名用于自动查询');
        }

        $limit = max(5, min(100, (int)$this->meta($integration, 'limit', '50')));
        $maxPages = max(1, min(50, (int)$this->meta($integration, 'pages', '1')));
        $syncNewOnly = $this->boolMeta($integration, 'sync_new_only', true);

        $created = $updated = $skipped = 0;
        $paginationToken = '';
        $page = 0;
        $stop = false;
        do {
            $page++;
            $payload = $this->fetchBookmarksPage($token, $userId, $limit, $paginationToken);
            $tweets = is_array($payload['data'] ?? null) ? $payload['data'] : [];
            $includes = is_array($payload['includes'] ?? null) ? $payload['includes'] : [];
            $users = $this->mapById(is_array($includes['users'] ?? null) ? $includes['users'] : []);
            $media = $this->mapByKey(is_array($includes['media'] ?? null) ? $includes['media'] : []);

            foreach ($tweets as $tweet) {
                if (!is_array($tweet)) {
                    $skipped++;
                    continue;
                }
                $id = trim((string)($tweet['id'] ?? ''));
                if ($id === '') {
                    $skipped++;
                    continue;
                }

                $externalId = 'x:bookmark:' . $id;
                if ($syncNewOnly && $this->activityExists($externalId)) {
                    $stop = true;
                    break;
                }

                $normalized = $this->normalizeTweet($tweet, $users, $media);
                $text = trim((string)($normalized['text'] ?? ''));
                if ($id === '' || $text === '') {
                    $skipped++;
                    continue;
                }

                $isNew = $this->ingest([
                    'type' => 'social',
                    'action' => 'saved',
                    'source' => 'x_bookmarks',
                    'external_id' => $externalId,
                    'title' => '收藏了 X：' . mb_strimwidth($text, 0, 64, '...', 'UTF-8'),
                    'content' => $text,
                    'url' => (string)($normalized['url'] ?? ''),
                    'happened_at' => (string)($normalized['posted_at'] ?? date('Y-m-d H:i:s')),
                    'metadata' => [
                        'tweet' => $normalized,
                        'likes' => (int)($normalized['likes_count'] ?? 0),
                        'reposts' => (int)($normalized['reposts_count'] ?? 0),
                        'replies' => (int)($normalized['replies_count'] ?? 0),
                        'views' => (int)($normalized['views_count'] ?? 0),
                        'images' => $normalized['images'] ?? [],
                        'bookmarked_by' => $username !== '' ? $username : $userId,
                    ],
                ]) ? $created++ : $updated++;
            }

            $meta = is_array($payload['meta'] ?? null) ? $payload['meta'] : [];
            $paginationToken = (string)($meta['next_token'] ?? '');
        } while (!$stop && $paginationToken !== '' && $page < $maxPages);

        return $this->result($created, $updated, $skipped, 'X 书签同步完成');
    }

    private function activityExists(string $externalId): bool
    {
        return (bool)\App\Models\Activity::db()->fetchColumn(
            'SELECT 1 FROM activities WHERE source = ? AND external_id = ? LIMIT 1',
            ['x_bookmarks', $externalId]
        );
    }

    private function validAccessToken(ActivityIntegration $integration): string
    {
        $token = trim((string)$integration->access_token);
        $expiresAt = trim((string)($integration->expires_at ?? ''));
        $refreshToken = trim((string)($integration->refresh_token ?? ''));
        if ($token !== '' && ($expiresAt === '' || strtotime($expiresAt) === false || strtotime($expiresAt) > time() + 120)) {
            return $token;
        }
        if ($refreshToken === '') {
            return $token;
        }

        $refreshed = $this->refreshAccessToken($refreshToken);
        $newToken = (string)($refreshed['access_token'] ?? '');
        if ($newToken === '') {
            return $token;
        }

        $newRefresh = (string)($refreshed['refresh_token'] ?? $refreshToken);
        $expiresIn = (int)($refreshed['expires_in'] ?? 0);
        \App\Models\ActivityIntegration::saveProvider('x_bookmarks', [
            'status' => (string)($integration->status ?? 'active'),
            'access_token' => $newToken,
            'refresh_token' => $newRefresh,
            'expires_at' => $expiresIn > 0 ? date('Y-m-d H:i:s', time() + $expiresIn) : null,
            'metadata' => $integration->metadata(),
        ]);

        return $newToken;
    }

    private function refreshAccessToken(string $refreshToken): array
    {
        $clientId = $this->env('X_OAUTH_CLIENT_ID');
        $clientSecret = $this->env('X_OAUTH_CLIENT_SECRET');
        if ($clientId === '') {
            return [];
        }

        $headers = ['Accept: application/json'];
        if ($clientSecret !== '') {
            $headers[] = 'Authorization: Basic ' . base64_encode($clientId . ':' . $clientSecret);
        }

        $res = Http::request('POST', 'https://api.x.com/2/oauth2/token', [
            'headers' => array_merge(['Content-Type: application/x-www-form-urlencoded'], $headers),
            'body' => http_build_query([
                'grant_type' => 'refresh_token',
                'refresh_token' => $refreshToken,
                'client_id' => $clientId,
            ]),
            'connect_timeout' => 15,
            'timeout' => 20,
            'default_headers' => false,
        ]);

        $data = $res['body'] !== '' ? json_decode($res['body'], true) : null;
        return $res['ok'] && is_array($data) ? $data : [];
    }

    private function lookupUserId(string $token, string $username): string
    {
        $payload = $this->http->getJson(
            'https://api.x.com/2/users/by/username/' . rawurlencode($username),
            ['Authorization: Bearer ' . $token],
            15
        );
        return (string)($payload['data']['id'] ?? '');
    }

    private function fetchBookmarksPage(string $token, string $userId, int $limit, string $paginationToken): array
    {
        $params = [
            'max_results' => $limit,
            'tweet.fields' => 'created_at,public_metrics,attachments,entities,referenced_tweets,author_id',
            'expansions' => 'author_id,attachments.media_keys',
            'media.fields' => 'url,preview_image_url,type',
            'user.fields' => 'name,username,profile_image_url,verified',
        ];
        if ($paginationToken !== '') {
            $params['pagination_token'] = $paginationToken;
        }

        return $this->http->getJson(
            'https://api.x.com/2/users/' . rawurlencode($userId) . '/bookmarks?' . http_build_query($params),
            ['Authorization: Bearer ' . $token],
            20
        );
    }

    private function normalizeTweet(array $tweet, array $users, array $media): array
    {
        $id = (string)($tweet['id'] ?? '');
        $authorId = (string)($tweet['author_id'] ?? '');
        $user = is_array($users[$authorId] ?? null) ? $users[$authorId] : [];
        $metrics = is_array($tweet['public_metrics'] ?? null) ? $tweet['public_metrics'] : [];
        $text = trim((string)($tweet['text'] ?? ''));
        $images = [];

        $attachments = is_array($tweet['attachments'] ?? null) ? $tweet['attachments'] : [];
        foreach ((array)($attachments['media_keys'] ?? []) as $key) {
            $item = is_array($media[(string)$key] ?? null) ? $media[(string)$key] : [];
            $image = (string)($item['url'] ?? $item['preview_image_url'] ?? '');
            if ($image !== '') {
                $images[] = $image;
            }
        }

        $entities = is_array($tweet['entities'] ?? null) ? $tweet['entities'] : [];
        foreach ((array)($entities['urls'] ?? []) as $urlEntity) {
            if (!is_array($urlEntity)) {
                continue;
            }
            $shortUrl = (string)($urlEntity['url'] ?? '');
            $expandedUrl = (string)($urlEntity['unwound_url'] ?? $urlEntity['expanded_url'] ?? '');
            if ($shortUrl !== '' && $expandedUrl !== '') {
                $text = str_replace($shortUrl, $expandedUrl, $text);
            }
        }

        $postedAt = null;
        if (!empty($tweet['created_at'])) {
            $ts = strtotime((string)$tweet['created_at']);
            $postedAt = $ts ? $this->formatSiteDateTime($ts) : null;
        }

        $handle = ltrim((string)($user['username'] ?? ''), '@');

        // 本地化:把头像与图片下载到本地(中国无法访问 pbs.twimg.com / x 媒体地址)
        $fetcher = $this->imageFetcher();
        $localImages = [];
        foreach (array_values(array_unique(array_filter($images))) as $i => $img) {
            $local = $fetcher->localizeImage($img, $id, $i);
            if ($local !== '') {
                $localImages[] = $local;
            } elseif (!$fetcher->isRemoteImageBlocked($img)) {
                $localImages[] = $img;
            }
        }
        $rawAvatar = (string)($user['profile_image_url'] ?? '');
        $avatar = '';
        if ($rawAvatar !== '') {
            $avatar = $fetcher->localizeImage($fetcher->upscaleAvatarUrl($rawAvatar), $id, 900);
            if ($avatar === '') {
                $avatar = $fetcher->localizeImage($rawAvatar, $id, 900);
            }
            if ($avatar === '' && !$fetcher->isRemoteImageBlocked($rawAvatar)) {
                $avatar = $rawAvatar;
            }
        }

        return [
            'id' => $id,
            'url' => $handle !== '' && $id !== '' ? 'https://x.com/' . rawurlencode($handle) . '/status/' . rawurlencode($id) : '',
            'text' => $text,
            'author_name' => (string)($user['name'] ?? ''),
            'author_handle' => $handle,
            'author_avatar' => $avatar,
            'author_verified' => !empty($user['verified']),
            'posted_at' => $postedAt,
            'likes_count' => (int)($metrics['like_count'] ?? 0),
            'reposts_count' => (int)($metrics['retweet_count'] ?? 0),
            'replies_count' => (int)($metrics['reply_count'] ?? 0),
            'views_count' => (int)($metrics['impression_count'] ?? 0),
            'images' => array_values(array_unique(array_filter($localImages))),
            'source' => 'x-bookmarks-api',
        ];
    }

    private function mapById(array $items): array
    {
        $out = [];
        foreach ($items as $item) {
            if (is_array($item) && isset($item['id'])) {
                $out[(string)$item['id']] = $item;
            }
        }
        return $out;
    }

    private function mapByKey(array $items): array
    {
        $out = [];
        foreach ($items as $item) {
            if (is_array($item) && isset($item['media_key'])) {
                $out[(string)$item['media_key']] = $item;
            }
        }
        return $out;
    }

    private function formatSiteDateTime(int $timestamp): string
    {
        $timezone = new \DateTimeZone((string)Config::get('app.timezone', 'Asia/Shanghai'));
        return (new \DateTimeImmutable('@' . $timestamp))
            ->setTimezone($timezone)
            ->format('Y-m-d H:i:s');
    }

    private function env(string $key): string
    {
        $value = getenv($key);
        if ($value !== false && $value !== '') {
            return (string)$value;
        }
        return (string)($_ENV[$key] ?? $_SERVER[$key] ?? '');
    }

}
