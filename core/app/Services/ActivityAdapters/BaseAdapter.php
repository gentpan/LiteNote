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

    /**
     * 记录一条动态并返回是否为新建(true=created / false=updated)。
     * 复用 ActivityService::record() 内部的存在性判断,免去各 adapter 重复的 exists() 查询。
     */
    protected function ingest(array $data): bool
    {
        return $this->activities->record($data)->wasRecentlyCreated;
    }

    /** 读取整数型 meta 并 clamp 到 [min,max]。 */
    protected function intMeta(ActivityIntegration $integration, string $key, int $default, int $min, int $max): int
    {
        return max($min, min($max, (int)$this->meta($integration, $key, (string)$default)));
    }

    /** 大数字友好显示:>=1000 转 K。 */
    protected function formatNumber(int $value): string
    {
        return $value >= 1000 ? round($value / 1000, 1) . 'K' : (string)$value;
    }
}
