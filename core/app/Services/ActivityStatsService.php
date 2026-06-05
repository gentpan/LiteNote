<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Activity;

final class ActivityStatsService
{
    public function rebuildForDate(string $date): array
    {
        ActivityInstaller::install();
        $date = substr($date, 0, 10);
        $rows = Activity::db()->fetchAll(
            "SELECT * FROM activities WHERE substr(happened_at, 1, 10) = ? AND visibility IN ('public', 'hidden')",
            [$date]
        );

        $stats = [
            'date' => $date,
            'coding_seconds' => 0,
            'music_seconds' => 0,
            'video_seconds' => 0,
            'ai_input_tokens' => 0,
            'ai_output_tokens' => 0,
            'ai_total_tokens' => 0,
            'github_events' => 0,
            'blog_posts' => 0,
            'social_events' => 0,
            'manual_events' => 0,
            'total_events' => count($rows),
            'active_types' => 0,
            'metadata' => [],
        ];
        $types = [];
        $typeCounts = [];

        foreach ($rows as $row) {
            $type = (string)($row['type'] ?? 'manual');
            $source = (string)($row['source'] ?? '');
            $action = (string)($row['action'] ?? '');
            $types[$type] = true;
            $typeCounts[$type] = ($typeCounts[$type] ?? 0) + 1;
            $meta = json_decode((string)($row['metadata'] ?? ''), true);
            $meta = is_array($meta) ? $meta : [];

            $stats['coding_seconds'] += (int)($meta['coding_seconds'] ?? 0);
            $stats['music_seconds'] += (int)($meta['music_seconds'] ?? $meta['duration_seconds'] ?? 0);
            $stats['video_seconds'] += (int)($meta['video_seconds'] ?? 0);
            $stats['ai_input_tokens'] += (int)($meta['input_tokens'] ?? 0);
            $stats['ai_output_tokens'] += (int)($meta['output_tokens'] ?? 0);
            $stats['ai_total_tokens'] += (int)($meta['total_tokens'] ?? 0);
            if ($source === 'github') {
                $stats['github_events']++;
            }
            if ($type === 'blog' && $action === 'published_post') {
                $stats['blog_posts']++;
            }
            if ($type === 'social') {
                $stats['social_events']++;
            }
            if ($source === 'manual') {
                $stats['manual_events']++;
            }
        }

        $stats['active_types'] = count($types);
        $stats['metadata'] = ['type_counts' => $typeCounts];
        $now = date('Y-m-d H:i:s');
        $payload = $stats;
        $payload['metadata'] = json_encode($payload['metadata'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $existing = Activity::db()->fetchOne('SELECT id FROM daily_activity_stats WHERE date = ? LIMIT 1', [$date]);
        if ($existing) {
            $payload['updated_at'] = $now;
            unset($payload['date']);
            Activity::db()->update('daily_activity_stats', $payload, 'id = :id', [':id' => (int)$existing['id']]);
        } else {
            $payload['created_at'] = $now;
            $payload['updated_at'] = $now;
            Activity::db()->insert('daily_activity_stats', $payload);
        }

        return $stats;
    }

    public function heatmap(int $days = 180): array
    {
        ActivityInstaller::install();
        $from = date('Y-m-d', strtotime('-' . max(1, $days - 1) . ' days'));
        $rows = Activity::db()->fetchAll(
            "SELECT substr(happened_at, 1, 10) AS day, type, COUNT(*) AS total
             FROM activities
             WHERE visibility = 'public' AND substr(happened_at, 1, 10) >= ?
             GROUP BY day, type
             ORDER BY day ASC",
            [$from]
        );
        $byDay = [];
        foreach ($rows as $row) {
            $day = (string)$row['day'];
            $type = (string)$row['type'];
            if (!isset($byDay[$day])) {
                $byDay[$day] = ['date' => $day, 'total' => 0, 'types' => []];
            }
            $byDay[$day]['total'] += (int)$row['total'];
            $byDay[$day]['types'][$type] = (int)$row['total'];
        }

        $out = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $day = date('Y-m-d', strtotime('-' . $i . ' days'));
            $cell = $byDay[$day] ?? ['date' => $day, 'total' => 0, 'types' => []];
            arsort($cell['types']);
            $cell['dominant_type'] = array_key_first($cell['types']) ?: 'none';
            $out[] = $cell;
        }
        return $out;
    }
}
