<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\ApiResponse;
use App\Core\Request;
use App\Core\Response;
use App\Middleware\ActivityApiAuthMiddleware;
use App\Models\Activity;
use App\Models\DailyActivityStat;
use App\Services\ActivityCacheService;
use App\Services\ActivityInstaller;
use App\Services\ActivityStatsService;

final class ActivityApiController
{
    public function index(Request $request): never
    {
        ActivityInstaller::install();
        $page = max(1, (int)$request->input('page', 1));
        $limit = max(1, min(100, (int)$request->input('limit', 20)));
        $filters = [];
        foreach (['type', 'source', 'date'] as $key) {
            $value = trim((string)$request->input($key, ''));
            if ($value !== '') {
                $filters[$key] = $value;
            }
        }
        $result = Activity::paginate($page, $limit, $filters, true);
        Response::json([
            'data' => array_map([$this, 'serializeActivity'], $result['items']),
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $result['total'],
            ],
        ]);
    }

    public function feed(Request $request): never
    {
        ActivityInstaller::install();
        $limit = max(1, min(100, (int)$request->input('limit', 60)));
        $filters = [];
        foreach (['type', 'source', 'date'] as $key) {
            $value = trim((string)$request->input($key, ''));
            if ($value !== '') {
                $filters[$key] = $value;
            }
        }

        $cache = new ActivityCacheService();
        $snapshot = $cache->snapshot((int)$request->input('ttl', 300));
        $items = $snapshot['recent'] ?? [];
        if (!is_array($items)) {
            $items = [];
        }

        $items = array_values(array_filter($items, static function (array $item) use ($filters): bool {
            foreach ($filters as $key => $value) {
                if ($key === 'date') {
                    if (substr((string)($item['happened_at'] ?? ''), 0, 10) !== $value) {
                        return false;
                    }
                    continue;
                }
                if ((string)($item[$key] ?? '') !== $value) {
                    return false;
                }
            }
            return true;
        }));

        ApiResponse::ok(array_map([$this, 'sanitizePublicActivity'], array_slice($items, 0, $limit)), [
            'generated_at' => (string)($snapshot['generated_at'] ?? ''),
            'cache_age_seconds' => $cache->age(),
            'filters' => $filters,
            'limit' => $limit,
        ]);
    }

    public function summary(Request $request): never
    {
        $cache = new ActivityCacheService();
        $snapshot = $cache->snapshot((int)$request->input('ttl', 300));
        ApiResponse::ok($snapshot['summary'] ?? [], [
            'generated_at' => (string)($snapshot['generated_at'] ?? ''),
            'cache_age_seconds' => $cache->age(),
        ]);
    }

    public function today(Request $request, array $params = []): never
    {
        $this->stat(date('Y-m-d'));
    }

    public function stat(Request|string $requestOrDate, array $params = []): never
    {
        $date = is_string($requestOrDate) ? $requestOrDate : (string)($params['date'] ?? date('Y-m-d'));
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : date('Y-m-d');
        $stat = DailyActivityStat::forDate($date);
        if (!$stat) {
            $stat = (new ActivityStatsService())->rebuildForDate($date);
        } elseif (!empty($stat['metadata'])) {
            $decoded = json_decode((string)$stat['metadata'], true);
            $stat['metadata'] = is_array($decoded) ? $decoded : [];
        }
        if (($requestOrDate instanceof Request) && str_starts_with((string)$requestOrDate->path, '/api/v1/')) {
            ApiResponse::ok($stat, ['date' => $date]);
        }
        Response::json($stat);
    }

    private function serializeActivity(Activity $activity): array
    {
        return $this->sanitizePublicActivity([
            'id' => (int)$activity->id,
            'type' => (string)$activity->type,
            'action' => (string)$activity->action,
            'source' => (string)$activity->source,
            'title' => (string)$activity->title,
            'content' => (string)$activity->content,
            'url' => (string)$activity->url,
            'icon' => (string)$activity->icon,
            'happened_at' => (string)$activity->happened_at,
            'metadata' => $activity->metadata(),
        ]);
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function sanitizePublicActivity(array $item): array
    {
        if (!ActivityApiAuthMiddleware::isPublic()) {
            return $item;
        }
        unset($item['metadata']);
        return $item;
    }
}
