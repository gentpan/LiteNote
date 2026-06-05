<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Activity;
use App\Models\DailyActivityStat;

final class ActivitySummaryService
{
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
            $stat = (new ActivityStatsService())->rebuildForDate($today);
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
