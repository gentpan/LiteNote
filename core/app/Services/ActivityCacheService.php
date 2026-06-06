<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\FileCache;
use App\Models\Activity;

final class ActivityCacheService
{
    private const CACHE_KEY = 'activity';

    public function rebuild(): array
    {
        ActivityInstaller::install();

        $recent = array_map(
            static fn(Activity $activity): array => self::serializeActivity($activity),
            Activity::recentPublic(60)
        );
        $summary = (new ActivityStatsService())->summary();
        if (is_array($summary)) {
            $summary['recent'] = array_map(
                static fn(Activity $activity): array => self::serializeActivity($activity),
                $summary['recent'] ?? []
            );
        }

        $payload = [
            'generated_at' => date('Y-m-d H:i:s'),
            'summary' => $summary,
            'heatmap' => (new ActivityStatsService())->heatmap(180),
            'recent' => $recent,
        ];

        $this->cache()->set(self::CACHE_KEY, $payload);

        return $payload;
    }

    public function snapshot(int $ttl = 300): array
    {
        $payload = $this->cache()->get(self::CACHE_KEY, null, $ttl);
        return is_array($payload) ? $payload : $this->rebuild();
    }

    public static function path(): string
    {
        return self::cacheFile();
    }

    public function age(): ?int
    {
        return $this->cache()->age(self::CACHE_KEY);
    }

    private static function serializeActivity(Activity $activity): array
    {
        $data = $activity->toArray();
        $data['metadata'] = $activity->metadata();
        return $data;
    }

    private function cache(): FileCache
    {
        return new FileCache(dirname(self::cacheFile()));
    }

    private static function cacheFile(): string
    {
        return dirname(__DIR__, 3) . '/runtime/storage/cache/activity.json';
    }
}
