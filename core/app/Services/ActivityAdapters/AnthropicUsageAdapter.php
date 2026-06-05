<?php
declare(strict_types=1);

namespace App\Services\ActivityAdapters;

use App\Models\ActivityIntegration;

final class AnthropicUsageAdapter extends BaseAdapter
{
    public function provider(): string
    {
        return 'anthropic';
    }

    public function sync(ActivityIntegration $integration): array
    {
        $adminKey = trim((string)$integration->access_token);
        if ($adminKey === '') {
            throw new \RuntimeException('Anthropic Admin API Key 不能为空');
        }

        $days = max(1, min(31, (int)$this->meta($integration, 'days', '7')));
        $startingAt = gmdate('Y-m-d\T00:00:00\Z', strtotime('-' . ($days - 1) . ' days'));
        $query = http_build_query([
            'starting_at' => $startingAt,
            'bucket_width' => '1d',
            'limit' => $days,
        ]) . '&group_by[]=model';
        $data = $this->http->getJson(
            'https://api.anthropic.com/v1/organizations/usage_report/messages?' . $query,
            [
                'x-api-key: ' . $adminKey,
                'anthropic-version: 2023-06-01',
                'content-type: application/json',
            ],
            20
        );
        $buckets = is_array($data['data'] ?? null) ? $data['data'] : [];

        $created = $updated = $skipped = 0;
        foreach ($buckets as $bucket) {
            if (!is_array($bucket)) {
                $skipped++;
                continue;
            }
            $date = substr((string)($bucket['starting_at'] ?? gmdate('Y-m-d')), 0, 10);
            foreach ((array)($bucket['results'] ?? []) as $result) {
                if (!is_array($result)) {
                    $skipped++;
                    continue;
                }
                $uncached = (int)($result['uncached_input_tokens'] ?? 0);
                $cacheRead = (int)($result['cache_read_input_tokens'] ?? 0);
                $cacheCreation = is_array($result['cache_creation'] ?? null) ? $result['cache_creation'] : [];
                $cacheCreate = (int)($cacheCreation['ephemeral_1h_input_tokens'] ?? 0)
                    + (int)($cacheCreation['ephemeral_5m_input_tokens'] ?? 0);
                $input = $uncached + $cacheRead + $cacheCreate;
                $output = (int)($result['output_tokens'] ?? 0);
                if ($input + $output === 0) {
                    $skipped++;
                    continue;
                }
                $model = (string)($result['model'] ?? 'Claude');
                $externalId = 'anthropic:usage:' . $date . ':' . ($model ?: 'all');
                $before = $this->exists('anthropic', $externalId);
                $this->activities->record([
                    'type' => 'ai',
                    'action' => 'used_tokens',
                    'source' => 'anthropic',
                    'external_id' => $externalId,
                    'title' => ($model ?: 'Claude') . ' 使用记录',
                    'content' => '输入 ' . $this->formatNumber($input) . ' Tokens，输出 ' . $this->formatNumber($output) . ' Tokens',
                    'happened_at' => $date . ' 23:57:00',
                    'visibility' => $this->boolMeta($integration, 'public_usage', true) ? 'public' : 'hidden',
                    'metadata' => [
                        'model' => $model,
                        'input_tokens' => $input,
                        'output_tokens' => $output,
                        'total_tokens' => $input + $output,
                        'uncached_input_tokens' => $uncached,
                        'cache_read_input_tokens' => $cacheRead,
                        'cache_creation_input_tokens' => $cacheCreate,
                    ],
                ]);
                $before ? $updated++ : $created++;
            }
        }

        return $this->result($created, $updated, $skipped, 'Claude Usage 同步完成');
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
