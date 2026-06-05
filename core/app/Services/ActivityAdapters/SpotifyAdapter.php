<?php
declare(strict_types=1);

namespace App\Services\ActivityAdapters;

use App\Models\ActivityIntegration;

final class SpotifyAdapter extends BaseAdapter
{
    public function provider(): string
    {
        return 'spotify';
    }

    public function sync(ActivityIntegration $integration): array
    {
        $token = $this->accessToken($integration);
        if ($token === '') {
            throw new \RuntimeException('Spotify access token 或 refresh token 不能为空');
        }

        $headers = ['Authorization: Bearer ' . $token];
        $limit = max(1, min(50, (int)$this->meta($integration, 'limit', '20')));
        $data = $this->http->getJson('https://api.spotify.com/v1/me/player/recently-played?limit=' . $limit, $headers, 15);
        $items = is_array($data['items'] ?? null) ? $data['items'] : [];

        $created = $updated = $skipped = 0;
        foreach ($items as $item) {
            if (!is_array($item) || !is_array($item['track'] ?? null)) {
                $skipped++;
                continue;
            }
            $track = $item['track'];
            $trackId = (string)($track['id'] ?? '');
            $playedAt = $this->isoToSql((string)($item['played_at'] ?? ''));
            if ($trackId === '') {
                $skipped++;
                continue;
            }
            $artists = $this->artistNames((array)($track['artists'] ?? []));
            $externalId = 'spotify:played:' . $trackId . ':' . strtotime($playedAt);
            $before = $this->exists('spotify', $externalId);
            $this->activities->record([
                'type' => 'music',
                'action' => 'played',
                'source' => 'spotify',
                'external_id' => $externalId,
                'title' => '播放了歌曲：' . ($artists ? $artists . ' - ' : '') . (string)($track['name'] ?? ''),
                'url' => (string)($track['external_urls']['spotify'] ?? ''),
                'happened_at' => $playedAt,
                'metadata' => [
                    'track_id' => $trackId,
                    'track' => (string)($track['name'] ?? ''),
                    'artist' => $artists,
                    'album' => (string)($track['album']['name'] ?? ''),
                    'duration_seconds' => (int)floor(((int)($track['duration_ms'] ?? 0)) / 1000),
                    'cover' => (string)($track['album']['images'][0]['url'] ?? ''),
                ],
            ]);
            $before ? $updated++ : $created++;
        }

        if ($this->boolMeta($integration, 'sync_saved', false)) {
            [$c, $u, $s] = $this->syncSavedTracks($headers, $limit);
            $created += $c;
            $updated += $u;
            $skipped += $s;
        }

        return $this->result($created, $updated, $skipped, 'Spotify 同步完成');
    }

    private function accessToken(ActivityIntegration $integration): string
    {
        $token = trim((string)$integration->access_token);
        $clientId = $this->meta($integration, 'client_id');
        $clientSecret = $this->meta($integration, 'client_secret');
        $refreshToken = trim((string)$integration->refresh_token);
        if ($clientId === '' || $clientSecret === '' || $refreshToken === '') {
            return $token;
        }

        $data = $this->http->postForm('https://accounts.spotify.com/api/token', [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
        ], ['Authorization: Basic ' . base64_encode($clientId . ':' . $clientSecret)], 15);

        return trim((string)($data['access_token'] ?? $token));
    }

    private function syncSavedTracks(array $headers, int $limit): array
    {
        $data = $this->http->getJson('https://api.spotify.com/v1/me/tracks?limit=' . $limit, $headers, 15);
        $items = is_array($data['items'] ?? null) ? $data['items'] : [];
        $created = $updated = $skipped = 0;
        foreach ($items as $item) {
            if (!is_array($item) || !is_array($item['track'] ?? null)) {
                $skipped++;
                continue;
            }
            $track = $item['track'];
            $trackId = (string)($track['id'] ?? '');
            if ($trackId === '') {
                $skipped++;
                continue;
            }
            $artists = $this->artistNames((array)($track['artists'] ?? []));
            $addedAt = $this->isoToSql((string)($item['added_at'] ?? ''));
            $externalId = 'spotify:liked:' . $trackId;
            $before = $this->exists('spotify', $externalId);
            $this->activities->record([
                'type' => 'music',
                'action' => 'liked',
                'source' => 'spotify',
                'external_id' => $externalId,
                'title' => '收藏了歌曲：' . ($artists ? $artists . ' - ' : '') . (string)($track['name'] ?? ''),
                'url' => (string)($track['external_urls']['spotify'] ?? ''),
                'happened_at' => $addedAt,
                'metadata' => [
                    'track_id' => $trackId,
                    'track' => (string)($track['name'] ?? ''),
                    'artist' => $artists,
                    'album' => (string)($track['album']['name'] ?? ''),
                    'duration_seconds' => (int)floor(((int)($track['duration_ms'] ?? 0)) / 1000),
                    'cover' => (string)($track['album']['images'][0]['url'] ?? ''),
                ],
            ]);
            $before ? $updated++ : $created++;
        }
        return [$created, $updated, $skipped];
    }

    private function artistNames(array $artists): string
    {
        $names = [];
        foreach ($artists as $artist) {
            if (is_array($artist) && !empty($artist['name'])) {
                $names[] = (string)$artist['name'];
            }
        }
        return implode(' / ', $names);
    }

    private function exists(string $source, string $externalId): bool
    {
        return (bool)\App\Models\Activity::db()->fetchOne(
            'SELECT id FROM activities WHERE source = ? AND external_id = ? LIMIT 1',
            [$source, $externalId]
        );
    }
}
