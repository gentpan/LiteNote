<?php
declare(strict_types=1);

namespace App\Services\ActivityAdapters;

use App\Core\Config;
use App\Models\ActivityIntegration;

final class NeteaseAdapter extends BaseAdapter
{
    public function provider(): string
    {
        return 'netease';
    }

    public function sync(ActivityIntegration $integration): array
    {
        $baseUrl = rtrim($this->stringSetting($integration, 'base_url', (string)Config::get('netease.api_base_url', '')), '/');
        if ($baseUrl === '') {
            throw new \RuntimeException('请填写 NeteaseCloudMusicApi 地址');
        }

        $uid = $this->stringSetting($integration, 'uid');
        $cookie = $this->normalizeCookie(trim((string)($integration->access_token ?? '')));
        $limit = $this->intSetting($integration, 'limit', 20, 1, 100);
        $created = $updated = $skipped = 0;
        $ran = false;

        if ($cookie !== '' && $this->boolSetting($integration, 'sync_recent', true)) {
            [$c, $u, $s] = $this->syncRecentSongs($baseUrl, $cookie, $limit);
            $created += $c;
            $updated += $u;
            $skipped += $s;
            $ran = true;
        }

        if ($uid !== '' && $this->boolSetting($integration, 'sync_records', $cookie === '')) {
            $recordType = in_array($this->stringSetting($integration, 'record_type', '1'), ['0', '1'], true)
                ? $this->stringSetting($integration, 'record_type', '1')
                : '1';
            [$c, $u, $s] = $this->syncUserRecords($baseUrl, $uid, $recordType, $limit);
            $created += $c;
            $updated += $u;
            $skipped += $s;
            $ran = true;
        }

        if ($uid !== '' && $this->boolSetting($integration, 'sync_liked', false)) {
            [$c, $u, $s] = $this->syncLikedSongs($baseUrl, $uid, $limit);
            $created += $c;
            $updated += $u;
            $skipped += $s;
            $ran = true;
        }

        if (!$ran) {
            throw new \RuntimeException('请至少填写 Cookie 或 UID，并开启一个同步项目');
        }

        return $this->result($created, $updated, $skipped, '网易云音乐同步完成');
    }

    private function syncRecentSongs(string $baseUrl, string $cookie, int $limit): array
    {
        $data = $this->get($baseUrl, '/record/recent/song', ['limit' => $limit], $cookie);
        $items = is_array($data['data']['list'] ?? null) ? $data['data']['list'] : [];
        $created = $updated = $skipped = 0;

        foreach ($items as $item) {
            if (!is_array($item)) {
                $skipped++;
                continue;
            }
            $song = $this->songFromRecentItem($item);
            $songId = $this->songId($song);
            if ($songId === '') {
                $skipped++;
                continue;
            }
            $playedAt = $this->neteaseTimeToSql($item['playTime'] ?? $item['playedTime'] ?? $item['time'] ?? null);
            $externalSuffix = $playedAt !== '' ? (string)strtotime($playedAt) : sha1(json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: $songId);
            $this->ingest($this->activityPayload(
                $song,
                'played',
                'netease:recent:' . $songId . ':' . $externalSuffix,
                $playedAt ?: date('Y-m-d H:i:s'),
                []
            )) ? $created++ : $updated++;
        }

        return [$created, $updated, $skipped];
    }

    private function syncUserRecords(string $baseUrl, string $uid, string $recordType, int $limit): array
    {
        $data = $this->get($baseUrl, '/user/record', [
            'uid' => $uid,
            'type' => $recordType,
        ]);
        $key = $recordType === '1' ? 'weekData' : 'allData';
        $items = is_array($data[$key] ?? null) ? $data[$key] : [];
        $items = array_slice($items, 0, $limit);
        $created = $updated = $skipped = 0;

        foreach ($items as $item) {
            if (!is_array($item) || !is_array($item['song'] ?? null)) {
                $skipped++;
                continue;
            }
            $song = $item['song'];
            $songId = $this->songId($song);
            if ($songId === '') {
                $skipped++;
                continue;
            }
            $this->ingest($this->activityPayload(
                $song,
                'played',
                'netease:record:' . $recordType . ':' . $songId,
                date('Y-m-d H:i:s'),
                [
                    'record_type' => $recordType,
                    'play_count' => (int)($item['playCount'] ?? 0),
                    'score' => (int)($item['score'] ?? 0),
                ],
                (int)($item['playCount'] ?? 0)
            )) ? $created++ : $updated++;
        }

        return [$created, $updated, $skipped];
    }

    private function syncLikedSongs(string $baseUrl, string $uid, int $limit): array
    {
        $data = $this->get($baseUrl, '/likelist', ['uid' => $uid]);
        $ids = array_values(array_filter(array_map('strval', (array)($data['ids'] ?? []))));
        $ids = array_slice($ids, 0, $limit);
        if ($ids === []) {
            return [0, 0, 0];
        }

        $detail = $this->get($baseUrl, '/song/detail', ['ids' => implode(',', $ids)]);
        $songs = is_array($detail['songs'] ?? null) ? $detail['songs'] : [];
        $created = $updated = $skipped = 0;
        foreach ($songs as $song) {
            if (!is_array($song)) {
                $skipped++;
                continue;
            }
            $songId = $this->songId($song);
            if ($songId === '') {
                $skipped++;
                continue;
            }
            $this->ingest($this->activityPayload(
                $song,
                'liked',
                'netease:liked:' . $songId,
                date('Y-m-d H:i:s'),
                []
            )) ? $created++ : $updated++;
        }

        return [$created, $updated, $skipped];
    }

    private function get(string $baseUrl, string $path, array $query = [], string $cookie = ''): array
    {
        $url = $baseUrl . $path;
        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }
        $headers = ['Accept: application/json'];
        if ($cookie !== '') {
            $headers[] = 'Cookie: ' . $cookie;
        }
        return $this->http->getJson($url, $headers, 20);
    }

    private function songFromRecentItem(array $item): array
    {
        foreach (['data', 'song', 'resource'] as $key) {
            if (is_array($item[$key] ?? null)) {
                return $item[$key];
            }
        }
        return $item;
    }

    private function activityPayload(
        array $song,
        string $action,
        string $externalId,
        string $happenedAt,
        array $extraMetadata = [],
        int $playCount = 0
    ): array {
        $track = $this->songName($song);
        $artist = $this->artistNames($song);
        $songId = $this->songId($song);
        $prefix = $action === 'liked' ? '收藏了歌曲：' : '播放了歌曲：';
        $title = $prefix . ($artist !== '' ? $artist . ' - ' : '') . $track;
        if ($playCount > 0) {
            $title .= '（' . $playCount . ' 次）';
        }

        return [
            'type' => 'music',
            'action' => $action,
            'source' => 'netease',
            'external_id' => $externalId,
            'title' => $title,
            'url' => $songId !== '' ? 'https://music.163.com/#/song?id=' . rawurlencode($songId) : '',
            'happened_at' => $happenedAt,
            'metadata' => array_merge([
                'provider' => 'netease',
                'track_id' => $songId,
                'track' => $track,
                'artist' => $artist,
                'album' => $this->albumName($song),
                'duration_seconds' => $this->durationSeconds($song),
                'cover' => $this->coverUrl($song),
            ], $extraMetadata),
        ];
    }

    private function songId(array $song): string
    {
        return trim((string)($song['id'] ?? $song['songId'] ?? $song['resourceId'] ?? ''));
    }

    private function songName(array $song): string
    {
        return trim((string)($song['name'] ?? $song['songName'] ?? '未知歌曲'));
    }

    private function albumName(array $song): string
    {
        $album = is_array($song['al'] ?? null) ? $song['al'] : (is_array($song['album'] ?? null) ? $song['album'] : []);
        return trim((string)($album['name'] ?? ''));
    }

    private function coverUrl(array $song): string
    {
        $album = is_array($song['al'] ?? null) ? $song['al'] : (is_array($song['album'] ?? null) ? $song['album'] : []);
        return trim((string)($album['picUrl'] ?? $album['coverUrl'] ?? ''));
    }

    private function durationSeconds(array $song): int
    {
        $duration = (int)($song['dt'] ?? $song['duration'] ?? $song['durationMs'] ?? 0);
        return $duration > 1000 ? (int)floor($duration / 1000) : $duration;
    }

    private function artistNames(array $song): string
    {
        $artists = is_array($song['ar'] ?? null) ? $song['ar'] : (is_array($song['artists'] ?? null) ? $song['artists'] : []);
        $names = [];
        foreach ($artists as $artist) {
            if (is_array($artist) && trim((string)($artist['name'] ?? '')) !== '') {
                $names[] = trim((string)$artist['name']);
            }
        }
        return implode(' / ', $names);
    }

    private function neteaseTimeToSql(mixed $value): string
    {
        if (is_numeric($value)) {
            $timestamp = (int)$value;
            if ($timestamp > 9999999999) {
                $timestamp = (int)floor($timestamp / 1000);
            }
            return $timestamp > 0 ? date('Y-m-d H:i:s', $timestamp) : '';
        }
        $timestamp = strtotime((string)$value);
        return $timestamp ? date('Y-m-d H:i:s', $timestamp) : '';
    }

    private function stringSetting(ActivityIntegration $integration, string $key, string $default = ''): string
    {
        $metadata = $integration->metadata();
        $value = $metadata[$key] ?? $default;
        $value = is_scalar($value) ? trim((string)$value) : '';
        return $value !== '' ? $value : $default;
    }

    private function intSetting(ActivityIntegration $integration, string $key, int $default, int $min, int $max): int
    {
        return max($min, min($max, (int)$this->stringSetting($integration, $key, (string)$default)));
    }

    private function boolSetting(ActivityIntegration $integration, string $key, bool $default): bool
    {
        $value = strtolower($this->stringSetting($integration, $key, $default ? '1' : '0'));
        return in_array($value, ['1', 'true', 'yes', 'on'], true);
    }

    private function normalizeCookie(string $cookie): string
    {
        if ($cookie === '' || str_contains($cookie, '=')) {
            return $cookie;
        }
        return 'MUSIC_U=' . $cookie;
    }
}
