<?php
declare(strict_types=1);

namespace App\Services\ActivityAdapters;

use App\Models\ActivityIntegration;

final class WakaTimeAdapter extends BaseAdapter
{
    public function provider(): string
    {
        return 'wakatime';
    }

    public function sync(ActivityIntegration $integration): array
    {
        $apiKey = trim((string)$integration->access_token);
        if ($apiKey === '') {
            throw new \RuntimeException('WakaTime API Key 不能为空');
        }
        $user = $this->meta($integration, 'username', 'current') ?: 'current';
        $days = max(1, min(31, (int)$this->meta($integration, 'days', '7')));
        $start = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));
        $end = date('Y-m-d');
        $url = 'https://wakatime.com/api/v1/users/' . rawurlencode($user)
            . '/summaries?start=' . rawurlencode($start) . '&end=' . rawurlencode($end);
        $data = $this->http->getJson($url, ['Authorization: Basic ' . base64_encode($apiKey)], 20);
        $daysData = is_array($data['data'] ?? null) ? $data['data'] : [];

        $created = $updated = 0;
        foreach ($daysData as $day) {
            if (!is_array($day)) {
                continue;
            }
            $date = (string)($day['range']['date'] ?? $day['date'] ?? '');
            if ($date === '') {
                continue;
            }
            $seconds = (int)($day['grand_total']['total_seconds'] ?? 0);
            $title = '编码 ' . $this->formatSeconds($seconds);
            $externalId = 'wakatime:summary:' . $date;
            $before = $this->exists('wakatime', $externalId);
            $this->activities->record([
                'type' => 'coding',
                'action' => 'started_session',
                'source' => 'wakatime',
                'external_id' => $externalId,
                'title' => $title,
                'content' => $this->topNames((array)($day['projects'] ?? []), '项目'),
                'url' => 'https://wakatime.com/dashboard',
                'happened_at' => $date . ' 23:59:00',
                'metadata' => [
                    'coding_seconds' => $seconds,
                    'languages' => $day['languages'] ?? [],
                    'projects' => $day['projects'] ?? [],
                    'editors' => $day['editors'] ?? [],
                ],
            ]);
            $before ? $updated++ : $created++;
        }

        return $this->result($created, $updated, 0, 'WakaTime 同步完成');
    }

    private function formatSeconds(int $seconds): string
    {
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        return $hours > 0 ? $hours . 'h ' . $minutes . 'm' : $minutes . 'm';
    }

    private function topNames(array $items, string $label): string
    {
        $names = [];
        foreach (array_slice($items, 0, 3) as $item) {
            if (is_array($item) && !empty($item['name'])) {
                $names[] = (string)$item['name'];
            }
        }
        return $names ? $label . '：' . implode(' / ', $names) : '';
    }

    private function exists(string $source, string $externalId): bool
    {
        return (bool)\App\Models\Activity::db()->fetchOne(
            'SELECT id FROM activities WHERE source = ? AND external_id = ? LIMIT 1',
            [$source, $externalId]
        );
    }
}
