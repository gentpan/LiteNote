<?php
declare(strict_types=1);

namespace App\Services\ActivityAdapters;

use App\Models\ActivityIntegration;

final class OpenAiUsageAdapter extends BaseAdapter
{
    public function provider(): string
    {
        return 'openai';
    }

    public function sync(ActivityIntegration $integration): array
    {
        $adminKey = trim((string)$integration->access_token);
        if ($adminKey === '') {
            throw new \RuntimeException('OpenAI Admin Key 不能为空');
        }
        $days = max(1, min(31, (int)$this->meta($integration, 'days', '7')));
        $startTime = strtotime('-' . ($days - 1) . ' days midnight');
        $url = 'https://api.openai.com/v1/organization/usage/completions?bucket_width=1d&limit='
            . $days . '&start_time=' . $startTime . '&group_by=model';
        $data = $this->http->getJson($url, ['Authorization: Bearer ' . $adminKey], 20);
        $buckets = is_array($data['data'] ?? null) ? $data['data'] : [];

        $created = $updated = $skipped = 0;
        foreach ($buckets as $bucket) {
            if (!is_array($bucket)) {
                $skipped++;
                continue;
            }
            $start = (int)($bucket['start_time'] ?? 0);
            $date = $start > 0 ? date('Y-m-d', $start) : date('Y-m-d');
            foreach ((array)($bucket['results'] ?? []) as $result) {
                if (!is_array($result)) {
                    $skipped++;
                    continue;
                }
                $input = (int)($result['input_tokens'] ?? 0);
                $output = (int)($result['output_tokens'] ?? 0);
                $requests = (int)($result['num_model_requests'] ?? 0);
                if ($input + $output + $requests === 0) {
                    $skipped++;
                    continue;
                }
                $model = (string)($result['model'] ?? 'OpenAI');
                $externalId = 'openai:usage:' . $date . ':' . ($model ?: 'all');
                $before = $this->exists('openai', $externalId);
                $this->activities->record([
                    'type' => 'ai',
                    'action' => 'used_tokens',
                    'source' => 'openai',
                    'external_id' => $externalId,
                    'title' => ($model ?: 'OpenAI') . ' 使用记录',
                    'content' => '输入 ' . $this->formatNumber($input) . ' Tokens，输出 ' . $this->formatNumber($output) . ' Tokens',
                    'happened_at' => $date . ' 23:58:00',
                    'visibility' => $this->boolMeta($integration, 'public_usage', true) ? 'public' : 'hidden',
                    'metadata' => [
                        'model' => $model,
                        'input_tokens' => $input,
                        'output_tokens' => $output,
                        'total_tokens' => $input + $output,
                        'requests' => $requests,
                    ],
                ]);
                $before ? $updated++ : $created++;
            }
        }

        return $this->result($created, $updated, $skipped, 'OpenAI Usage 同步完成');
    }

    private function formatNumber(int $value): string
    {
        return $value >= 1000 ? round($value / 1000, 1) . 'K' : (string)$value;
    }

    private function exists(string $source, string $externalId): bool
    {
        return (bool)\App\Models\Activity::db()->fetchOne(
            'SELECT id FROM activities WHERE source = ? AND external_id = ? LIMIT 1',
            [$source, $externalId]
        );
    }
}
