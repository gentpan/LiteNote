<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Http;
use App\Models\Talk;

final class TweetFetchService
{
    private const IMAGE_MIMES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];
    private const WEBP_QUALITY = 86;
    private string $bearerToken;

    public function __construct(?string $bearerToken = null)
    {
        $this->bearerToken = trim((string)$bearerToken);
    }

    public function fetch(string $urlOrId): array
    {
        $tweetId = Talk::extractTweetId($urlOrId);
        $url = $this->normalizeUrl($urlOrId, $tweetId);
        if ($url === '') {
            throw new \InvalidArgumentException('无效的 X 链接');
        }

        $data = [
            'id' => $tweetId,
            'url' => $url,
            'text' => '',
            'author_name' => '',
            'author_handle' => $this->extractHandleFromUrl($url),
            'author_avatar' => '',
            'author_verified' => false,
            'posted_at' => null,
            'likes_count' => 0,
            'reposts_count' => 0,
            'replies_count' => 0,
            'views_count' => 0,
            'images' => [],
            'html' => '',
            'source' => '',
            'fetched_at' => date('c'),
        ];

        $syndication = $tweetId !== '' ? $this->fetchSyndication($tweetId) : null;
        if ($syndication) {
            $data = array_replace($data, $this->normalizeSyndication($syndication, $url));
        }

        $official = $tweetId !== '' ? $this->fetchOfficialApi($tweetId) : null;
        if ($official) {
            $data = array_replace($data, $this->normalizeOfficialApi($official, $url));
        }

        $oembed = $this->fetchOembed($url);
        if ($oembed) {
            $data = $this->mergePreferFilled($data, $this->normalizeOembed($oembed, $url));
        }

        $page = $this->fetchPage($url);
        if ($page) {
            $data = $this->mergePreferFilled($data, $this->normalizePage($page, $url));
        }

        $profile = $data['author_handle'] !== '' ? $this->fetchElfsightProfile((string)$data['author_handle']) : null;
        if ($profile) {
            $data = $this->mergePreferFilled($data, $this->normalizeElfsightProfile($profile));
        }

        $data['id'] = $data['id'] !== '' ? $data['id'] : $tweetId;
        $data['url'] = $data['url'] !== '' ? $data['url'] : $url;
        $data['author_handle'] = ltrim((string)$data['author_handle'], '@');
        $data = $this->applyAuthorDefaults($data);
        if ($data['id'] !== '') {
            $derivedPostedAt = $this->postedAtFromTweetId((string)$data['id']);
            if ($derivedPostedAt !== null && ($data['posted_at'] === null || str_ends_with((string)$data['posted_at'], ' 00:00:00'))) {
                $data['posted_at'] = $derivedPostedAt;
            }
        }
        $data['images'] = array_values(array_unique(array_filter(array_map('trim', $data['images']))));
        $data['remote_images'] = $data['images'];
        if ($data['images'] !== []) {
            $data['images'] = $this->localizeTweetImages($data['images'], (string)$data['id']);
        }

        return $data;
    }

    private function normalizeUrl(string $urlOrId, string $tweetId): string
    {
        $value = trim($urlOrId);
        if ($tweetId !== '') {
            return preg_match('~^https?://~i', $value) ? $value : 'https://x.com/i/web/status/' . $tweetId;
        }
        return preg_match('~^https?://~i', $value) ? $value : '';
    }

    private function fetchOembed(string $url): ?array
    {
        $endpoint = 'https://publish.twitter.com/oembed?omit_script=true&dnt=true&url=' . rawurlencode($url);
        $json = $this->httpGet($endpoint, 8);
        if ($json === '') {
            return null;
        }
        $data = json_decode($json, true);
        return is_array($data) ? $data : null;
    }

    private function fetchSyndication(string $tweetId): ?array
    {
        $endpoint = 'https://cdn.syndication.twimg.com/tweet-result?id=' . rawurlencode($tweetId) . '&lang=zh-cn';
        $json = $this->httpGet($endpoint, 8);
        if ($json === '') {
            return null;
        }
        $data = json_decode($json, true);
        return is_array($data) && !empty($data) ? $data : null;
    }

    private function fetchOfficialApi(string $tweetId): ?array
    {
        $token = $this->bearerToken !== '' ? $this->bearerToken : trim($this->env('X_BEARER_TOKEN'));
        if ($token === '') {
            return null;
        }

        $query = http_build_query([
            'tweet.fields' => 'created_at,public_metrics,attachments,entities',
            'expansions' => 'author_id,attachments.media_keys',
            'user.fields' => 'name,username,profile_image_url,public_metrics,verified',
            'media.fields' => 'url,preview_image_url,type',
        ]);
        $json = $this->httpGet(
            'https://api.x.com/2/tweets/' . rawurlencode($tweetId) . '?' . $query,
            8,
            ['Authorization: Bearer ' . $token]
        );
        if ($json === '') {
            return null;
        }
        $data = json_decode($json, true);
        return is_array($data) && !empty($data['data']) ? $data : null;
    }

    private function fetchElfsightProfile(string $handle): ?array
    {
        $handle = ltrim(trim($handle), '@');
        if ($handle === '') {
            return null;
        }

        $json = $this->httpGet(
            'https://widget-data.service.elfsight.com/api/twitter/profile?username=' . rawurlencode($handle),
            8
        );
        if ($json === '') {
            return null;
        }

        $data = json_decode($json, true);
        return is_array($data) && (int)($data['code'] ?? 0) === 200 && is_array($data['payload'] ?? null)
            ? $data['payload']
            : null;
    }

    private function fetchPage(string $url): string
    {
        return $this->httpGet($url, 8);
    }

    private function httpGet(string $url, int $timeout, array $headers = []): string
    {
        $headers = array_merge([
            'Accept-Language: zh-CN,zh;q=0.9,en;q=0.8',
            'User-Agent: LiteNote TweetFetcher/1.0',
        ], $headers);
        $r = Http::request('GET', $url, [
            'headers' => $headers,
            'timeout' => $timeout,
            'default_headers' => false,
        ]);
        return ($r['status'] >= 200 && $r['status'] < 400) ? $r['body'] : '';
    }

    private function normalizeOembed(array $oembed, string $url): array
    {
        $html = (string)($oembed['html'] ?? '');
        $authorUrl = (string)($oembed['author_url'] ?? '');
        return [
            'url' => (string)($oembed['url'] ?? $url),
            'text' => $this->extractTweetTextFromHtml($html),
            'author_name' => (string)($oembed['author_name'] ?? ''),
            'author_handle' => $this->extractHandleFromUrl($authorUrl),
            'posted_at' => $this->extractTweetDateFromHtml($html),
            'html' => $html,
            'source' => 'oembed',
        ];
    }

    private function normalizeSyndication(array $data, string $url): array
    {
        $user = is_array($data['user'] ?? null) ? $data['user'] : [];
        $photos = [];
        foreach (($data['photos'] ?? []) as $photo) {
            if (is_array($photo) && !empty($photo['url'])) {
                $photos[] = (string)$photo['url'];
            }
        }

        $postedAt = null;
        if (!empty($data['created_at'])) {
            $ts = strtotime((string)$data['created_at']);
            $postedAt = $ts ? $this->formatSiteDateTime($ts) : null;
        }

        return [
            'id' => (string)($data['id_str'] ?? $data['id'] ?? ''),
            'url' => (string)($data['url'] ?? $url),
            'text' => trim((string)($data['text'] ?? '')),
            'author_name' => (string)($user['name'] ?? ''),
            'author_handle' => (string)($user['screen_name'] ?? ''),
            'author_avatar' => (string)($user['profile_image_url_https'] ?? $user['profile_image_url'] ?? ''),
            'author_verified' => !empty($user['verified']),
            'posted_at' => $postedAt,
            'likes_count' => (int)($data['favorite_count'] ?? $data['like_count'] ?? 0),
            'reposts_count' => (int)($data['retweet_count'] ?? $data['repost_count'] ?? 0),
            'replies_count' => (int)($data['reply_count'] ?? $data['replies_count'] ?? 0),
            'views_count' => (int)($data['view_count'] ?? $data['views_count'] ?? 0),
            'images' => $photos,
            'source' => 'syndication',
        ];
    }

    private function normalizeOfficialApi(array $payload, string $url): array
    {
        $tweet = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $includes = is_array($payload['includes'] ?? null) ? $payload['includes'] : [];
        $users = is_array($includes['users'] ?? null) ? $includes['users'] : [];
        $user = is_array($users[0] ?? null) ? $users[0] : [];
        $userMetrics = is_array($user['public_metrics'] ?? null) ? $user['public_metrics'] : [];
        $metrics = is_array($tweet['public_metrics'] ?? null) ? $tweet['public_metrics'] : [];
        $media = is_array($includes['media'] ?? null) ? $includes['media'] : [];
        $images = [];
        $text = trim((string)($tweet['text'] ?? ''));
        foreach ($media as $item) {
            if (!is_array($item)) {
                continue;
            }
            $imageUrl = (string)($item['url'] ?? $item['preview_image_url'] ?? '');
            if ($imageUrl !== '') {
                $images[] = $imageUrl;
            }
        }
        $entities = is_array($tweet['entities'] ?? null) ? $tweet['entities'] : [];
        foreach (($entities['urls'] ?? []) as $urlEntity) {
            if (!is_array($urlEntity)) {
                continue;
            }
            $expandedUrl = (string)($urlEntity['unwound_url'] ?? $urlEntity['expanded_url'] ?? '');
            $shortUrl = (string)($urlEntity['url'] ?? '');
            if ($shortUrl !== '' && $expandedUrl !== '') {
                $text = str_replace($shortUrl, $expandedUrl, $text);
            }
            foreach ($this->fetchLinkPreviewImages($expandedUrl) as $previewImage) {
                $images[] = $previewImage;
            }
        }

        $postedAt = null;
        if (!empty($tweet['created_at'])) {
            $ts = strtotime((string)$tweet['created_at']);
            $postedAt = $ts ? $this->formatSiteDateTime($ts) : null;
        }

        return [
            'id' => (string)($tweet['id'] ?? ''),
            'url' => $url,
            'text' => $text,
            'author_name' => (string)($user['name'] ?? ''),
            'author_handle' => (string)($user['username'] ?? ''),
            'author_avatar' => (string)($user['profile_image_url'] ?? ''),
            'author_verified' => !empty($user['verified']),
            'author_followers_count' => (int)($userMetrics['followers_count'] ?? 0),
            'author_following_count' => (int)($userMetrics['following_count'] ?? 0),
            'author_posts_count' => (int)($userMetrics['tweet_count'] ?? 0),
            'posted_at' => $postedAt,
            'likes_count' => (int)($metrics['like_count'] ?? 0),
            'reposts_count' => (int)($metrics['retweet_count'] ?? 0),
            'replies_count' => (int)($metrics['reply_count'] ?? 0),
            'views_count' => (int)($metrics['impression_count'] ?? 0),
            'images' => $images,
            'source' => 'x-api',
        ];
    }

    /**
     * @return array<int,string>
     */
    private function fetchLinkPreviewImages(string $url): array
    {
        $url = trim($url);
        if (!preg_match('~^https?://~i', $url)) {
            return [];
        }

        $host = strtolower((string)(parse_url($url, PHP_URL_HOST) ?? ''));
        if ($host === '' || $host === 'x.com' || $host === 'twitter.com' || str_ends_with($host, '.x.com') || str_ends_with($host, '.twitter.com')) {
            return [];
        }

        $html = $this->httpGet($url, 10, ['Accept: text/html,application/xhtml+xml']);
        if ($html === '') {
            return [];
        }

        foreach (['og:image', 'og:image:secure_url', 'twitter:image'] as $name) {
            $image = $this->meta($html, $name);
            if ($image !== '') {
                return [$this->resolveUrl($image, $url)];
            }
        }

        return [];
    }

    private function resolveUrl(string $url, string $baseUrl): string
    {
        $url = trim($url);
        if ($url === '' || preg_match('~^https?://~i', $url)) {
            return $url;
        }
        if (str_starts_with($url, '//')) {
            return 'https:' . $url;
        }

        $scheme = (string)(parse_url($baseUrl, PHP_URL_SCHEME) ?: 'https');
        $host = (string)(parse_url($baseUrl, PHP_URL_HOST) ?: '');
        if ($host === '') {
            return '';
        }
        if (str_starts_with($url, '/')) {
            return $scheme . '://' . $host . $url;
        }

        $path = (string)(parse_url($baseUrl, PHP_URL_PATH) ?: '/');
        $dir = rtrim(str_replace('\\', '/', dirname($path)), '/');
        return $scheme . '://' . $host . ($dir !== '' ? $dir : '') . '/' . $url;
    }

    private function normalizeElfsightProfile(array $profile): array
    {
        return [
            'author_name' => (string)($profile['fullName'] ?? ''),
            'author_handle' => (string)($profile['username'] ?? ''),
            'author_avatar' => (string)($profile['pictureUrl'] ?? ''),
            'author_verified' => !empty($profile['isVerified']),
            'author_followers_count' => (int)($profile['followersCount'] ?? 0),
            'author_posts_count' => (int)($profile['postsCount'] ?? 0),
        ];
    }

    private function normalizePage(string $html, string $url): array
    {
        $title = $this->meta($html, 'og:title') ?: $this->meta($html, 'twitter:title');
        $description = $this->meta($html, 'og:description') ?: $this->meta($html, 'twitter:description');
        $image = $this->meta($html, 'og:image') ?: $this->meta($html, 'twitter:image');
        $author = '';
        $handle = $this->extractHandleFromUrl($url);

        if (preg_match('/^(.*?)\s+on\s+X:/u', $title, $m)) {
            $author = trim($m[1], " \t\n\r\0\x0B\"“”");
        } elseif (preg_match('/^(.*?)\s+on\s+Twitter:/u', $title, $m)) {
            $author = trim($m[1], " \t\n\r\0\x0B\"“”");
        }

        return [
            'text' => $description,
            'author_name' => $author,
            'author_handle' => $handle,
            'images' => $image !== '' ? [$image] : [],
            'source' => 'page',
        ];
    }

    /**
     * @param array<int,string> $images
     * @return array<int,string>
     */
    private function localizeTweetImages(array $images, string $tweetId): array
    {
        $localized = [];
        foreach ($images as $index => $imageUrl) {
            $imageUrl = (string)$imageUrl;
            $localUrl = $this->downloadRemoteImage($imageUrl, $tweetId, $index);
            if ($localUrl !== '') {
                $localized[] = $localUrl;
                continue;
            }

            if (!$this->isBlockedRemoteImage($imageUrl)) {
                $localized[] = $imageUrl;
            }
        }

        return array_values(array_unique(array_filter($localized)));
    }

    private function downloadRemoteImage(string $url, string $tweetId, int $index): string
    {
        $url = trim($url);
        if (!preg_match('~^https?://~i', $url)) {
            return '';
        }

        $body = $this->httpGet($url, 12, ['Accept: image/avif,image/webp,image/apng,image/*,*/*;q=0.8']);
        if ($body === '') {
            return '';
        }

        $maxSize = min((int)Config::get('upload.max_size', 10 * 1024 * 1024), 15 * 1024 * 1024);
        if (strlen($body) > $maxSize) {
            return '';
        }

        $mime = $this->detectImageMimeFromBuffer($body);
        if (!isset(self::IMAGE_MIMES[$mime])) {
            return '';
        }

        $uploadDir = rtrim((string)Config::get('upload.path'), '/');
        $uploadUrl = rtrim((string)Config::get('upload.url'), '/');
        if ($uploadDir === '' || $uploadUrl === '') {
            return '';
        }

        $sub = 'x/' . date('Y/m');
        $subDir = $uploadDir . '/' . $sub;
        if (!is_dir($subDir) && !mkdir($subDir, 0775, true) && !is_dir($subDir)) {
            return '';
        }

        $safeId = preg_match('/^[0-9]{8,40}$/', $tweetId) ? $tweetId : 'tweet';
        $filename = $safeId . '-' . ($index + 1) . '-' . substr(sha1($url), 0, 12) . '.webp';
        $path = $subDir . '/' . $filename;
        if (!is_file($path) && !$this->writeWebpImage($body, $mime, $path)) {
            return '';
        }

        return $uploadUrl . '/' . $sub . '/' . $filename;
    }

    private function isBlockedRemoteImage(string $url): bool
    {
        $host = strtolower((string)(parse_url($url, PHP_URL_HOST) ?? ''));
        return $host === 'twimg.com' || str_ends_with($host, '.twimg.com');
    }

    private function writeWebpImage(string $body, string $mime, string $target): bool
    {
        $tmp = tempnam(sys_get_temp_dir(), 'litenote-x-img-');
        if (!is_string($tmp) || file_put_contents($tmp, $body, LOCK_EX) === false) {
            return false;
        }

        try {
            if (class_exists(\Imagick::class)) {
                try {
                    $image = new \Imagick($tmp);
                    if ($image->getNumberImages() > 1) {
                        $image = $image->coalesceImages();
                        $image->setIteratorIndex(0);
                    }
                    $image->setImageFormat('webp');
                    $image->setImageCompressionQuality(self::WEBP_QUALITY);
                    $image->stripImage();
                    if ($image->writeImage($target)) {
                        $image->clear();
                        return true;
                    }
                    $image->clear();
                } catch (\Throwable) {
                    // GD/cwebp fallback below.
                }
            }

            if (function_exists('imagewebp')) {
                $resource = match ($mime) {
                    'image/jpeg' => imagecreatefromjpeg($tmp),
                    'image/png' => imagecreatefrompng($tmp),
                    'image/gif' => imagecreatefromgif($tmp),
                    'image/webp' => imagecreatefromwebp($tmp),
                    default => false,
                };
                if ($resource) {
                    if (function_exists('imagepalettetotruecolor')) {
                        imagepalettetotruecolor($resource);
                    }
                    imagealphablending($resource, true);
                    imagesavealpha($resource, true);
                    $ok = imagewebp($resource, $target, self::WEBP_QUALITY);
                    imagedestroy($resource);
                    if ($ok) {
                        return true;
                    }
                }
            }

            $cwebp = trim((string)shell_exec('command -v cwebp 2>/dev/null'));
            if ($cwebp !== '') {
                $cmd = escapeshellcmd($cwebp) . ' -quiet -q ' . self::WEBP_QUALITY . ' '
                    . escapeshellarg($tmp) . ' -o ' . escapeshellarg($target);
                exec($cmd, $output, $code);
                return $code === 0 && is_file($target);
            }

            return $mime === 'image/webp' && file_put_contents($target, $body, LOCK_EX) !== false;
        } finally {
            @unlink($tmp);
        }
    }

    private function detectImageMimeFromBuffer(string $body): string
    {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $mime = finfo_buffer($finfo, $body);
                if (is_string($mime) && isset(self::IMAGE_MIMES[$mime])) {
                    return $mime;
                }
            }
        }

        return match (true) {
            str_starts_with($body, "\xFF\xD8\xFF") => 'image/jpeg',
            str_starts_with($body, "\x89PNG\r\n\x1A\n") => 'image/png',
            str_starts_with($body, 'GIF87a'), str_starts_with($body, 'GIF89a') => 'image/gif',
            substr($body, 0, 4) === 'RIFF' && substr($body, 8, 4) === 'WEBP' => 'image/webp',
            default => '',
        };
    }

    private function extractTweetTextFromHtml(string $html): string
    {
        if ($html === '') {
            return '';
        }
        if (!preg_match('~<p[^>]*>(.*?)</p>~is', $html, $m)) {
            return '';
        }
        $text = preg_replace('~<br\s*/?>~i', "\n", $m[1]) ?? $m[1];
        $text = preg_replace('~<a\b[^>]*>.*?</a>~is', '', $text) ?? $text;
        return trim(html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private function extractTweetDateFromHtml(string $html): ?string
    {
        if ($html === '') {
            return null;
        }
        if (preg_match('~<a[^>]+href="[^"]*/status(?:es)?/[0-9]+[^"]*"[^>]*>(.*?)</a>~is', $html, $m)) {
            $raw = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $ts = strtotime($raw);
            return $ts ? $this->formatSiteDateTime($ts) : null;
        }
        return null;
    }

    private function postedAtFromTweetId(string $tweetId): ?string
    {
        if (!preg_match('/^[0-9]{8,40}$/', $tweetId)) {
            return null;
        }

        $twitterEpochMs = 1288834974657;
        $timestampMs = (int)floor(((int)$tweetId / 4194304) + $twitterEpochMs);
        if ($timestampMs <= $twitterEpochMs) {
            return null;
        }

        return $this->formatSiteDateTime((int)floor($timestampMs / 1000));
    }

    private function formatSiteDateTime(int $timestamp): string
    {
        $timezone = new \DateTimeZone((string)Config::get('app.timezone', 'Asia/Shanghai'));
        return (new \DateTimeImmutable('@' . $timestamp))
            ->setTimezone($timezone)
            ->format('Y-m-d H:i:s');
    }

    private function applyAuthorDefaults(array $data): array
    {
        $defaultName = trim($this->env('X_DEFAULT_NAME'));
        $defaultHandle = ltrim(trim($this->env('X_DEFAULT_HANDLE')), '@');
        $defaultAvatar = trim($this->env('X_DEFAULT_AVATAR'));
        $defaultVerified = $this->envBool('X_DEFAULT_VERIFIED', false);

        if (trim((string)($data['author_name'] ?? '')) === '' && $defaultName !== '') {
            $data['author_name'] = $defaultName;
        }
        if (trim((string)($data['author_handle'] ?? '')) === '' && $defaultHandle !== '') {
            $data['author_handle'] = $defaultHandle;
        }
        if (trim((string)($data['author_avatar'] ?? '')) === '' && $defaultAvatar !== '') {
            $data['author_avatar'] = $defaultAvatar;
        }
        if (empty($data['author_verified']) && $defaultVerified) {
            $data['author_verified'] = true;
        }

        return $data;
    }

    private function envBool(string $key, bool $default = false): bool
    {
        $value = $this->env($key);
        if (trim($value) === '') {
            return $default;
        }
        return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
    }

    private function env(string $key, string $default = ''): string
    {
        if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
            return (string)$_ENV[$key];
        }
        if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') {
            return (string)$_SERVER[$key];
        }
        $value = getenv($key);
        return $value !== false && $value !== '' ? (string)$value : $default;
    }

    private function meta(string $html, string $name): string
    {
        $quoted = preg_quote($name, '~');
        if (preg_match('~<meta[^>]+(?:property|name)=["\']' . $quoted . '["\'][^>]+content=["\']([^"\']*)["\']~i', $html, $m)) {
            return trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
        if (preg_match('~<meta[^>]+content=["\']([^"\']*)["\'][^>]+(?:property|name)=["\']' . $quoted . '["\']~i', $html, $m)) {
            return trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
        return '';
    }

    private function extractHandleFromUrl(string $url): string
    {
        $path = (string)(parse_url($url, PHP_URL_PATH) ?? '');
        $parts = array_values(array_filter(explode('/', trim($path, '/'))));
        if (!empty($parts[0]) && !in_array($parts[0], ['i', 'web', 'status', 'statuses'], true)) {
            return ltrim($parts[0], '@');
        }
        return '';
    }

    private function mergePreferFilled(array $base, array $extra): array
    {
        foreach ($extra as $key => $value) {
            if ($key === 'images') {
                $base[$key] = array_values(array_unique(array_merge($base[$key] ?? [], $value ?: [])));
                continue;
            }
            if (is_bool($value) && $value === true && empty($base[$key])) {
                $base[$key] = true;
                continue;
            }
            if (($base[$key] ?? null) === '' || ($base[$key] ?? null) === null || ($base[$key] ?? null) === []) {
                $base[$key] = $value;
            }
        }
        return $base;
    }
}
