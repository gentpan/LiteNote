<?php
declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\Helper;
use App\Core\Request;
use App\Core\View;
use App\Models\Activity;
use App\Services\ActivityInstaller;
use App\Services\ActivityService;
use App\Services\ActivityStatsService;

final class ActivityController
{
    public function index(Request $request): string
    {
        ActivityInstaller::install();
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 20;
        $type = trim((string)($_GET['type'] ?? ''));
        $source = trim((string)($_GET['source'] ?? ''));
        $date = trim((string)($_GET['date'] ?? ''));
        $filters = [];
        if ($type !== '' && isset(ActivityService::TYPES[$type])) {
            $filters['type'] = $type;
        }
        if ($source !== '') {
            $filters['source'] = $source;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $filters['date'] = $date;
        }

        $result = Activity::paginate($page, $perPage, $filters, true);
        $statsService = new ActivityStatsService();
        $todayStats = $statsService->rebuildForDate(date('Y-m-d'));
        $heatmap = $this->activityHeatmap($statsService->heatmap(365));

        return View::render('front.activity.index', [
            'list' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'perPage' => $perPage,
            'activeType' => $filters['type'] ?? '',
            'types' => ActivityService::TYPES,
            'heatmap' => $heatmap,
            'todayStats' => $todayStats,
            'paginator' => Helper::loadMore($page, $result['total'], $perPage, Helper::url('/activity' . ($type !== '' ? '?type=' . rawurlencode($type) : ''))),
            'pageTitle' => '动态',
            'activeNav' => 'activity',
        ]);
    }

    private function activityHeatmap(array $cells): array
    {
        $byDay = [];
        foreach ($cells as $cell) {
            $date = substr((string)($cell['date'] ?? ''), 0, 10);
            if ($date === '') {
                continue;
            }
            $byDay[$date] = [
                'total' => (int)($cell['total'] ?? 0),
                'types' => is_array($cell['types'] ?? null) ? $cell['types'] : [],
            ];
        }

        $today = strtotime(date('Y-m-d')) ?: time();
        $rangeStart = strtotime('-364 days', $today) ?: $today;
        $startDow = (int)date('w', $rangeStart);
        $gridStart = strtotime("-{$startDow} days", $rangeStart) ?: $rangeStart;
        $endDow = (int)date('w', $today);
        $gridEnd = strtotime('+' . (6 - $endDow) . ' days', $today) ?: $today;

        $days = [];
        $months = [];
        $monthSeen = [];
        $activeDays = 0;
        $totalEvents = 0;
        $i = 0;
        for ($ts = $gridStart; $ts <= $gridEnd; $ts += 86400, $i++) {
            $date = date('Y-m-d', $ts);
            $inRange = $ts >= $rangeStart && $ts <= $today;
            $week = intdiv($i, 7) + 1;
            if ($inRange) {
                $monthKey = date('Y-m', $ts);
                if (!isset($monthSeen[$monthKey]) && ((int)date('j', $ts) <= 7 || $date === date('Y-m-d', $rangeStart))) {
                    $monthSeen[$monthKey] = true;
                    $months[] = ['label' => date('n月', $ts), 'week' => $week];
                }
            }

            $total = $inRange ? (int)($byDay[$date]['total'] ?? 0) : 0;
            if ($total > 0) {
                $activeDays++;
                $totalEvents += $total;
            }

            $days[] = [
                'date' => $date,
                'total' => $total,
                'level' => $inRange ? min(4, $total) : 0,
                'muted' => !$inRange,
            ];
        }

        return [
            'days' => $days,
            'months' => $months,
            'weeks' => max(1, (int)ceil(count($days) / 7)),
            'activeDays' => $activeDays,
            'totalEvents' => $totalEvents,
        ];
    }
}
