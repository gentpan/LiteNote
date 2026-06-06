<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Http;

final class AiSummaryService
{
    public static function summarize(string $markdown, string $title = ''): array
    {
        $plain = self::plainText($markdown);
        if ($plain === '') {
            throw new \RuntimeException('正文为空，无法生成摘要');
        }

        $provider = strtolower(trim((string)Config::get('ai.provider', 'deepseek')));
        if ($provider === '') {
            $provider = 'deepseek';
        }

        $providers = $provider === 'local' ? [] : array_values(array_unique([$provider, 'openai']));
        foreach ($providers as $candidate) {
            try {
                $summary = self::remoteSummary($candidate, $plain, $title);
                if ($summary !== '') {
                    return ['summary' => $summary, 'provider' => $candidate];
                }
            } catch (\Throwable) {
                // 线上写作不能因为外部 API 失败而中断，自动降级到本地摘要。
            }
        }

        return ['summary' => self::localSummary($plain), 'provider' => 'local'];
    }

    private static function remoteSummary(string $provider, string $plain, string $title): string
    {
        return match ($provider) {
            'openai' => self::chatSummary(
                trim((string)Config::get('ai.openai.api_key', '')),
                trim((string)Config::get('ai.openai.model', 'gpt-4o-mini')),
                trim((string)Config::get('ai.openai.base_url', 'https://api.openai.com/v1')),
                $plain,
                $title
            ),
            'deepseek' => self::chatSummary(
                trim((string)Config::get('ai.deepseek.api_key', '')),
                trim((string)Config::get('ai.deepseek.model', 'deepseek-v4-flash')),
                trim((string)Config::get('ai.deepseek.base_url', 'https://api.deepseek.com')),
                $plain,
                $title
            ),
            default => '',
        };
    }

    private static function chatSummary(string $apiKey, string $model, string $baseUrl, string $plain, string $title): string
    {
        if ($apiKey === '' || $model === '') {
            return '';
        }
        $baseUrl = rtrim($baseUrl, '/');
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

        $res = Http::postJson($baseUrl . '/chat/completions', $payload, [
            'Authorization: Bearer ' . $apiKey,
        ], 18);
        if (!$res['ok'] || $res['body'] === '') {
            return '';
        }
        $data = json_decode($res['body'], true);
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
