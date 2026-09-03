<?php
declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\FileCache;
use App\Core\Helper;
use App\Core\Request;
use App\Core\View;
use App\Models\Activity;
use App\Models\ActivityIntegration;
use App\Services\ActivityInstaller;
use App\Services\ActivityService;
use App\Services\ActivityStatsService;
use App\Services\HeatmapBuilder;

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
        if ($type !== '' && isset(ActivityService::TYPES[$type]) && $type !== 'manual') {
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
        $heatmap = (new FileCache())->remember('activity.page-heatmap', 3600, function () use ($statsService): array {
            return HeatmapBuilder::buildActivityGrid($statsService->heatmap(365));
        });
        $visibleTypes = $this->visibleTypes();
        $sourceFilters = $this->sourceFilters();
        $activityTotal = $this->publicTotal();
        $query = [];
        if (!empty($filters['type'])) {
            $query['type'] = $filters['type'];
        }
        if (!empty($filters['source'])) {
            $query['source'] = $filters['source'];
        }
        $baseUrl = '/activity' . ($query !== [] ? '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986) : '');

        return View::render('front.activity.index', [
            'list' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'perPage' => $perPage,
            'activeType' => $filters['type'] ?? '',
            'activeSource' => $filters['source'] ?? '',
            'types' => $visibleTypes,
            'sourceFilters' => $sourceFilters,
            'activityTotal' => $activityTotal,
            'heatmap' => $heatmap,
            'todayStats' => $todayStats,
            'paginator' => Helper::loadMore($page, $result['total'], $perPage, Helper::url($baseUrl)),
            'pageTitle' => '动态',
            'activeNav' => 'activity',
        ]);
    }

    private function publicTotal(): int
    {
        return (int)Activity::db()->fetchColumn("SELECT COUNT(*) FROM activities WHERE visibility = 'public'");
    }

    /**
     * @return array<string,array{label:string,icon:string}>
     */
    private function visibleTypes(): array
    {
        return array_filter(
            ActivityService::TYPES,
            static fn(string $key): bool => $key !== 'manual',
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * @return array<int,array{source:string,label:string,icon:string,count:int}>
     */
    private function sourceFilters(): array
    {
        $rows = Activity::db()->fetchAll(
            "SELECT source, COUNT(*) AS total
             FROM activities
             WHERE visibility = 'public' AND COALESCE(source, '') <> '' AND source <> 'manual'
             GROUP BY source
             ORDER BY total DESC, source ASC"
        );

        $out = [];
        foreach ($rows as $row) {
            $source = trim((string)($row['source'] ?? ''));
            if ($source === '') {
                continue;
            }
            $out[] = [
                'source' => $source,
                'label' => $this->sourceLabel($source),
                'icon' => $this->sourceIcon($source),
                'count' => (int)($row['total'] ?? 0),
            ];
        }
        return $out;
    }

    private function sourceLabel(string $source): string
    {
        if ($source === 'litenote') {
            return '文章 / 说说';
        }

        $providers = ActivityIntegration::providers();
        if (isset($providers[$source]['label'])) {
            return (string)$providers[$source]['label'];
        }

        return ucfirst(str_replace(['_', '-'], ' ', $source));
    }

    private function sourceIcon(string $source): string
    {
        $providers = ActivityIntegration::providers();
        if (isset($providers[$source]['icon'])) {
            return (string)$providers[$source]['icon'];
        }

        return match ($source) {
            'litenote' => 'fa-regular fa-file-lines',
            default => 'fa-solid fa-chart-simple',
        };
    }
}
