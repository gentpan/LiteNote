<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;

final class AiSummaryService
{
    public static function summarize(string $markdown, string $title = ''): array
    {
        $plain = self::plainText($markdown);
        if ($plain === '') {
            throw new \RuntimeException('正文为空，无法生成摘要');
        }

        $apiKey = trim((string)(getenv('OPENAI_API_KEY') ?: Setting::get('openai_api_key', '')));
        if ($apiKey !== '') {
            try {
                $summary = self::openAiSummary($apiKey, $plain, $title);
                if ($summary !== '') {
                    return ['summary' => $summary, 'provider' => 'openai'];
                }
            } catch (\Throwable) {
                // 线上写作不能因为外部 API 失败而中断，自动降级到本地摘要。
            }
        }

        return ['summary' => self::localSummary($plain), 'provider' => 'local'];
    }

    private static function openAiSummary(string $apiKey, string $plain, string $title): string
    {
        $model = trim((string)(getenv('OPENAI_MODEL') ?: Setting::get('openai_model', 'gpt-4o-mini')));
        if (!function_exists('curl_init')) {
            return '';
        }
        $payload = [
            'model' => $model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => '你是博客编辑。请为文章生成一段自然、克制、适合 SEO 的中文摘要，80 到 140 字，不要使用列表。',
                ],
                [
                    'role' => 'user',
                    'content' => trim("标题：{$title}\n\n正文：\n" . mb_substr($plain, 0, 6000)),
                ],
            ],
            'temperature' => 0.4,
            'max_tokens' => 220,
        ];

        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        if (!$ch) {
            return '';
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_TIMEOUT => 18,
        ]);
        $raw = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if (!is_string($raw) || $code < 200 || $code >= 300) {
            return '';
        }
        $data = json_decode($raw, true);
        $summary = $data['choices'][0]['message']['content'] ?? '';
        return self::cleanSummary((string)$summary);
    }

    private static function localSummary(string $plain): string
    {
        $sentences = preg_split('/(?<=[。！？!?])\s*/u', $plain, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $summary = '';
        foreach ($sentences as $sentence) {
            $sentence = trim($sentence);
            if ($sentence === '' || mb_strlen($sentence) < 8) {
                continue;
            }
            if (mb_strlen($summary . $sentence) > 150) {
                break;
            }
            $summary .= $sentence;
            if (mb_strlen($summary) >= 80) {
                break;
            }
        }
        if ($summary === '') {
            $summary = mb_substr($plain, 0, 140);
        }
        return self::cleanSummary($summary);
    }

    private static function plainText(string $markdown): string
    {
        $text = preg_replace('/^---\s*\n.*?\n---\s*\n/s', '', $markdown) ?? $markdown;
        $text = preg_replace('/```.*?```/s', ' ', $text) ?? $text;
        $text = preg_replace('/!\[[^\]]*]\([^)]+\)/', ' ', $text) ?? $text;
        $text = preg_replace('/\[([^\]]+)]\([^)]+\)/', '$1', $text) ?? $text;
        $text = preg_replace('/[#>*_`~\-\|\[\]()]/', ' ', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', strip_tags($text)) ?? $text;
        return trim($text);
    }

    private static function cleanSummary(string $summary): string
    {
        $summary = trim(preg_replace('/\s+/u', ' ', strip_tags($summary)) ?? $summary);
        $summary = trim($summary, " \t\n\r\0\x0B\"'");
        return mb_strlen($summary) > 180 ? mb_substr($summary, 0, 180) : $summary;
    }
}
