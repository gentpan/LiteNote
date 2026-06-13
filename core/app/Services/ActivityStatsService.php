<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Activity;
use App\Models\DailyActivityStat;

final class ActivityStatsService
{
    public function rebuildForDate(string $date): array
    {
        ActivityInstaller::install();
        $date = substr($date, 0, 10);

        $existing = Activity::db()->fetchOne(
            'SELECT * FROM daily_activity_stats WHERE date = ? LIMIT 1',
            [$date]
        );
        $cacheWindow = 300; // 5 分钟内不重复重建
        if ($existing && strtotime((string)$existing['updated_at']) >= time() - $cacheWindow) {
            $metadata = json_decode((string)($existing['metadata'] ?? ''), true);
            $existing['metadata'] = is_array($metadata) ? $metadata : [];
            return $existing;
        }

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

    /**
     * 组装"今日概览"卡片(今日统计 + 最近 3 条 + 类型 TopN)。
     * 原 ActivitySummaryService 已并入此处。
     */
    public function summary(): ?array
    {
        ActivityInstaller::install();
        $today = date('Y-m-d');
        $stat = DailyActivityStat::today();
        $recent = Activity::recentPublic(3);
        $total = (int)Activity::db()->fetchColumn("SELECT COUNT(*) FROM activities WHERE visibility = 'public'");
        if (!$stat && $recent === []) {
            return null;
        }
        if (!$stat) {
            $stat = $this->rebuildForDate($today);
        }

        $rawMetadata = $stat['metadata'] ?? [];
        $metadata = is_array($rawMetadata) ? $rawMetadata : json_decode((string)$rawMetadata, true);
        $metadata = is_array($metadata) ? $metadata : [];
        $typeCounts = is_array($metadata['type_counts'] ?? null) ? $metadata['type_counts'] : [];
        arsort($typeCounts);
        $metrics = [];
        foreach (array_slice($typeCounts, 0, 4, true) as $type => $count) {
            $metrics[] = [
                'type' => $type,
                'label' => ActivityService::typeLabel((string)$type),
                'icon' => ActivityService::typeIcon((string)$type),
                'value' => (int)$count . ' 条',
            ];
        }
        if ($metrics === [] && $total > 0) {
            $metrics[] = ['type' => 'manual', 'label' => '记录', 'icon' => ActivityService::typeIcon('manual'), 'value' => $total . ' 条'];
        }

        return [
            'date' => $today,
            'total_today' => (int)($stat['total_events'] ?? 0),
            'active_types' => (int)($stat['active_types'] ?? count($typeCounts)),
            'total' => $total,
            'metrics' => $metrics,
            'recent' => $recent,
        ];
    }
}
