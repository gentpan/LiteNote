<?php
declare(strict_types=1);

namespace App\Services\ActivityAdapters;

use App\Models\ActivityIntegration;
use App\Services\ActivityService;

abstract class BaseAdapter implements ActivityAdapter
{
    protected HttpClient $http;
    protected ActivityService $activities;

    public function __construct(?HttpClient $http = null, ?ActivityService $activities = null)
    {
        $this->http = $http ?: new HttpClient();
        $this->activities = $activities ?: new ActivityService();
    }

    protected function meta(ActivityIntegration $integration, string $key, string $default = ''): string
    {
        $metadata = $integration->metadata();
        $value = $metadata[$key] ?? $default;
        return is_scalar($value) ? trim((string)$value) : $default;
    }

    protected function boolMeta(ActivityIntegration $integration, string $key, bool $default = false): bool
    {
        $value = strtolower($this->meta($integration, $key, $default ? '1' : '0'));
        return in_array($value, ['1', 'true', 'yes', 'on'], true);
    }

    protected function isoToSql(?string $value): string
    {
        $ts = $value ? strtotime($value) : false;
        return $ts ? date('Y-m-d H:i:s', $ts) : date('Y-m-d H:i:s');
    }

    protected function result(int $created, int $updated, int $skipped, string $message): array
    {
        return compact('created', 'updated', 'skipped', 'message');
    }
}
