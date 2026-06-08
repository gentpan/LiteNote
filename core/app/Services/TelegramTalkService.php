<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Http;
use App\Enums\Toggle;
use App\Models\Talk;

final class TelegramTalkService
{
    private string $token;
    /** @var array<int,string> */
    private array $allowedChatIds;

    public function __construct(?string $token = null, ?string $allowedChatIds = null)
    {
        $this->token = trim((string)($token ?? self::env('TELEGRAM_BOT_TOKEN')));
        $rawIds = (string)($allowedChatIds ?? self::env('TELEGRAM_ALLOWED_CHAT_IDS'));
        $this->allowedChatIds = array_values(array_filter(array_map('trim', preg_split('/[,;\s]+/', $rawIds) ?: [])));
    }

    public function enabled(): bool
    {
        return $this->token !== '';
    }

    /**
     * @return array{created:bool, ignored:bool, reason:string, id:int}
     */
    public function handleUpdate(array $update): array
    {
        if (!$this->enabled()) {
            return ['created' => false, 'ignored' => false, 'reason' => 'telegram_not_configured', 'id' => 0];
        }

        $message = $this->messageFromUpdate($update);
        if ($message === []) {
            return ['created' => false, 'ignored' => true, 'reason' => 'no_message', 'id' => 0];
        }

        $chatId = (string)($message['chat']['id'] ?? '');
        if (!$this->chatAllowed($chatId)) {
            return ['created' => false, 'ignored' => true, 'reason' => 'chat_not_allowed', 'id' => 0];
        }

        $content = trim((string)($message['text'] ?? $message['caption'] ?? ''));
        $images = $this->extractImages($message);
        if ($content === '' && empty($images)) {
            return ['created' => false, 'ignored' => true, 'reason' => 'empty_message', 'id' => 0];
        }
        if ($content === '') {
            $content = '分享了图片';
        }

        Talk::ensureLocationSchema();
        $item = new Talk([
            'content' => $content,
            'images' => implode(',', $images),
            'mood' => '',
            'music_id' => 0,
            'is_public' => Toggle::On->value,
            'post_type' => 'talk',
            'location_name' => '',
            'location_city' => '',
            'location_lat' => '',
            'location_lng' => '',
            'location_provider' => '',
            'location_data' => '',
            'published_at' => $this->publishedAt($message),
        ]);
        $item->save();

        return ['created' => true, 'ignored' => false, 'reason' => '', 'id' => (int)$item->id];
    }

    /**
     * @return array<string,mixed>
     */
    private function messageFromUpdate(array $update): array
    {
        foreach (['message', 'edited_message', 'channel_post'] as $key) {
            if (is_array($update[$key] ?? null)) {
                return $update[$key];
            }
        }
        return [];
    }

    private function chatAllowed(string $chatId): bool
    {
        if ($this->allowedChatIds === []) {
            return true;
        }
        return in_array($chatId, $this->allowedChatIds, true);
    }

    /**
     * @param array<string,mixed> $message
     * @return array<int,string>
     */
    private function extractImages(array $message): array
    {
        $photos = is_array($message['photo'] ?? null) ? $message['photo'] : [];
        if ($photos === []) {
            return [];
        }

        usort($photos, fn($a, $b) => (int)($a['file_size'] ?? 0) <=> (int)($b['file_size'] ?? 0));
        $best = end($photos);
        $fileId = is_array($best) ? trim((string)($best['file_id'] ?? '')) : '';
        if ($fileId === '') {
            return [];
        }

        try {
            $url = $this->downloadTelegramFileAsUpload($fileId);
            return $url !== '' ? [$url] : [];
        } catch (\Throwable) {
            return [];
        }
    }

    private function downloadTelegramFileAsUpload(string $fileId): string
    {
        $fileInfo = Http::getJson($this->apiUrl('getFile') . '?' . http_build_query(['file_id' => $fileId]), [], 12);
        $path = trim((string)($fileInfo['result']['file_path'] ?? ''));
        if (($fileInfo['ok'] ?? false) !== true || $path === '') {
            return '';
        }

        $bytes = Http::download(
            'https://api.telegram.org/file/bot' . $this->token . '/' . ltrim($path, '/'),
            (int)Config::get('upload.max_size', 5 * 1024 * 1024),
            ['Accept: image/avif,image/webp,image/apng,image/*,*/*;q=0.8'],
            20
        );

        $tmp = tempnam(sys_get_temp_dir(), 'litenote-tg-');
        if ($tmp === false) {
            return '';
        }
        file_put_contents($tmp, $bytes);
        $name = basename($path) ?: 'telegram-image.jpg';
        try {
            $uploaded = ImageUploadService::upload([
                'name' => $name,
                'type' => '',
                'tmp_name' => $tmp,
                'error' => UPLOAD_ERR_OK,
                'size' => strlen($bytes),
            ], 'telegram');
            return (string)($uploaded['url'] ?? '');
        } finally {
            if (is_file($tmp)) {
                @unlink($tmp);
            }
        }
    }

    /**
     * @param array<string,mixed> $message
     */
    private function publishedAt(array $message): string
    {
        $date = (int)($message['date'] ?? 0);
        return $date > 0 ? date('Y-m-d H:i:s', $date) : date('Y-m-d H:i:s');
    }

    private function apiUrl(string $method): string
    {
        return 'https://api.telegram.org/bot' . $this->token . '/' . $method;
    }

    private static function env(string $key, string $default = ''): string
    {
        $value = getenv($key);
        if ($value === false && isset($_ENV[$key])) {
            $value = $_ENV[$key];
        }
        if ($value === false && isset($_SERVER[$key])) {
            $value = $_SERVER[$key];
        }
        return trim((string)($value === false ? $default : $value));
    }
}
