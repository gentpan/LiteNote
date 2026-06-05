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

        return View::render('front.activity.index', [
            'list' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'perPage' => $perPage,
            'activeType' => $filters['type'] ?? '',
            'types' => ActivityService::TYPES,
            'heatmap' => $statsService->heatmap(180),
            'todayStats' => $todayStats,
            'paginator' => Helper::loadMore($page, $result['total'], $perPage, Helper::url('/activity' . ($type !== '' ? '?type=' . rawurlencode($type) : ''))),
            'pageTitle' => '动态',
            'activeNav' => 'activity',
        ]);
    }
}
