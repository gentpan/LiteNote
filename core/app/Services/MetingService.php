<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Http;

final class MetingService
{
    private string $baseUrl;
    private string $token;
    private string $quality;
    private int $timeout;

    public function __construct()
    {
        $this->baseUrl = rtrim((string)Config::get('meting.base_url', 'https://api.meting.io'), '/');
        $this->token = trim((string)Config::get('meting.token', ''));
        $this->quality = trim((string)Config::get('meting.quality', 'exhigh')) ?: 'exhigh';
        $this->timeout = max(3, min(30, (int)Config::get('meting.timeout', 12)));
    }

    public function search(string $provider, string $keyword, int $page = 1, int $limit = 10): array
    {
        $provider = $this->normalizeProvider($provider);
        $keyword = trim($keyword);
        if ($keyword === '') {
            throw new \InvalidArgumentException('请输入搜索关键词');
        }

        $payload = $this->requestJson(sprintf(
            '/v1/%s/search?q=%s&page=%d&limit=%d',
            rawurlencode($provider),
            rawurlencode($keyword),
            max(1, $page),
            max(1, min(30, $limit))
        ));

        $items = $payload['items'] ?? $payload['songs'] ?? [];
        if (!is_array($items)) {
            $items = [];
        }

        return [
            'provider' => $provider,
            'items' => array_values(array_map(fn(mixed $item): array => $this->normalizeSong((array)$item, $provider), $items)),
        ];
    }

    public function songPayload(string $provider, string $id): array
    {
        $provider = $this->normalizeProvider($provider);
        $id = trim($id);
        if ($id === '') {
            throw new \InvalidArgumentException('缺少歌曲 ID');
        }

        $rawSong = $this->requestJson(sprintf('/v1/%s/songs/%s', rawurlencode($provider), rawurlencode($id)));
        $songData = is_array($rawSong['item'] ?? null)
            ? (array)$rawSong['item']
            : (is_array($rawSong['song'] ?? null) ? (array)$rawSong['song'] : $rawSong);
        $song = $this->normalizeSong($songData, $provider);
        if (($song['id'] ?? '') === '') {
            $song['id'] = $id;
        }

        $stream = $this->requestJson(sprintf(
            '/v1/%s/songs/%s/stream?redirect=false&quality=%s',
            rawurlencode($provider),
            rawurlencode($id),
            rawurlencode($this->quality)
        ));
        $cover = $this->requestJson(sprintf(
            '/v1/%s/songs/%s/cover?size=600&redirect=false',
            rawurlencode($provider),
            rawurlencode($id)
        ));

        return $song + [
            'audio_url' => trim((string)($stream['url'] ?? '')),
            'cover_url' => trim((string)($cover['url'] ?? '')),
            'lyrics_url' => $this->publicLyricUrl($provider, $id),
            'quality' => (string)($stream['quality'] ?? $this->quality),
            'format' => (string)($stream['format'] ?? ''),
            'source' => 'meting',
        ];
    }

    public function lyricText(string $provider, string $id): string
    {
        $provider = $this->normalizeProvider($provider);
        $id = trim($id);
        if ($id === '') {
            throw new \InvalidArgumentException('缺少歌曲 ID');
        }
        return $this->extractLyricText($this->requestText(sprintf('/v1/%s/songs/%s/lyric', rawurlencode($provider), rawurlencode($id))));
    }

    private function publicLyricUrl(string $provider, string $id): string
    {
        $path = '/music/lyrics/meting?provider=' . rawurlencode($provider) . '&id=' . rawurlencode($id);
        return $this->absoluteSiteUrl($path);
    }

    private function absoluteSiteUrl(string $path): string
    {
        $path = '/' . ltrim($path, '/');
        $host = trim((string)($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? ''));
        if ($host !== '') {
            $proto = trim((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
            if ($proto === '') {
                $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            }
            $proto = strtolower(explode(',', $proto)[0] ?? 'http');
            $proto = in_array($proto, ['http', 'https'], true) ? $proto : 'http';
            return $proto . '://' . $host . $path;
        }

        $base = rtrim((string)Config::get('app.url', ''), '/');
        return ($base !== '' ? $base : 'http://127.0.0.1:5555') . $path;
    }

    private function normalizeProvider(string $provider): string
    {
        $provider = strtolower(trim($provider));
        return in_array($provider, ['netease', 'tencent', 'kugou'], true) ? $provider : 'netease';
    }

    private function normalizeSong(array $item, string $provider): array
    {
        $artists = $item['artists'] ?? null;
        if (is_array($artists)) {
            $artists = array_values(array_filter(array_map(static function (mixed $artist): string {
                if (is_array($artist)) {
                    return trim((string)($artist['name'] ?? $artist['artist'] ?? ''));
                }
                return trim((string)$artist);
            }, $artists)));
        } else {
            $artists = [];
        }

        $artist = trim((string)($item['artist'] ?? ''));
        if ($artist === '' && !empty($artists)) {
            $artist = implode(' / ', $artists);
        }

        return [
            'id' => (string)($item['id'] ?? $item['mid'] ?? $item['hash'] ?? ''),
            'provider' => (string)($item['provider'] ?? $provider),
            'title' => (string)($item['name'] ?? $item['title'] ?? ''),
            'artist' => $artist,
            'album' => (string)($item['album'] ?? ''),
            'duration' => $this->formatDuration($item['duration'] ?? $item['time'] ?? null),
            'raw_url' => (string)($item['url'] ?? ''),
            'raw_cover' => (string)($item['cover'] ?? ''),
            'raw_lyric' => (string)($item['lyric'] ?? ''),
        ];
    }

    private function formatDuration(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        if (is_string($value) && preg_match('/^\d{1,2}:\d{2}$/', $value)) {
            return $value;
        }
        $seconds = (int)$value;
        if ($seconds > 1000) {
            $seconds = (int)round($seconds / 1000);
        }
        if ($seconds <= 0) {
            return '';
        }
        return sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60);
    }

    private function requestJson(string $path): array
    {
        $body = $this->request($path, 'application/json');
        $json = json_decode($body, true);
        if (!is_array($json)) {
            throw new \RuntimeException('Meting 返回格式无效');
        }
        if (isset($json['error'])) {
            $message = is_array($json['error']) ? (string)($json['error']['message'] ?? 'Meting 请求失败') : (string)$json['error'];
            throw new \RuntimeException($message);
        }
        return $json;
    }

    private function requestText(string $path): string
    {
        return $this->request($path, 'text/plain');
    }

    private function extractLyricText(string $body): string
    {
        $trimmed = trim($body);
        if ($trimmed === '') {
            return '';
        }

        $json = json_decode($trimmed, true);
        if (is_array($json)) {
            foreach (['lyric', 'lrc', 'content', 'text'] as $key) {
                if (isset($json[$key]) && is_string($json[$key])) {
                    return trim($json[$key]);
                }
            }
            foreach (['data', 'result'] as $group) {
                if (!isset($json[$group]) || !is_array($json[$group])) {
                    continue;
                }
                foreach (['lyric', 'lrc', 'content', 'text'] as $key) {
                    if (isset($json[$group][$key]) && is_string($json[$group][$key])) {
                        return trim($json[$group][$key]);
                    }
                }
            }
        }

        return $body;
    }

    private function request(string $path, string $accept): string
    {
        if ($this->token === '') {
            throw new \RuntimeException('请先在 .env 配置 METING_TOKEN');
        }

        $url = $this->baseUrl . '/' . ltrim($path, '/');
        $r = Http::request('GET', $url, [
            'headers' => [
                'Accept: ' . $accept,
                'Authorization: Bearer ' . $this->token,
                'User-Agent: LiteNote/1.0 MetingClient',
            ],
            'timeout' => $this->timeout,
            'default_headers' => false,
        ]);

        $status = $r['status'];
        if ($status === 0 || $status >= 400) {
            $message = $status === 401 ? 'Meting Token 无效或未配置' : ($status === 429 ? 'Meting 今日配额已用完' : 'Meting 请求失败');
            throw new \RuntimeException($message);
        }

        return $r['body'];
    }
}
