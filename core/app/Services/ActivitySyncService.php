<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Activity;
use App\Models\ActivityIntegration;
use App\Services\ActivityAdapters\ActivityAdapter;
use App\Services\ActivityAdapters\AnthropicUsageAdapter;
use App\Services\ActivityAdapters\BilibiliAdapter;
use App\Services\ActivityAdapters\GitHubAdapter;
use App\Services\ActivityAdapters\NeoDbAdapter;
use App\Services\ActivityAdapters\OpenAiUsageAdapter;
use App\Services\ActivityAdapters\SpotifyAdapter;
use App\Services\ActivityAdapters\WakaTimeAdapter;

final class ActivitySyncService
{
    /** @var array<string,ActivityAdapter> */
    private array $adapters;

    public function __construct()
    {
        $this->adapters = [];
        foreach ([
            new GitHubAdapter(),
            new WakaTimeAdapter(),
            new SpotifyAdapter(),
            new NeoDbAdapter(),
            new OpenAiUsageAdapter(),
            new AnthropicUsageAdapter(),
            new BilibiliAdapter(),
        ] as $adapter) {
            $this->adapters[$adapter->provider()] = $adapter;
        }

        // 合并插件注册的 Activity 适配器(同 provider 时插件覆盖内置)。
        foreach (\App\Services\Plugins\Registry::adapters() as $adapter) {
            $this->adapters[$adapter->provider()] = $adapter;
        }
    }

    public function providers(): array
    {
        return array_keys($this->adapters);
    }

    public function sync(string $provider, bool $force = false): array
    {
        ActivityInstaller::install();
        $provider = trim($provider);
        $adapter = $this->adapters[$provider] ?? null;
        if (!$adapter) {
            throw new \InvalidArgumentException('不支持的平台：' . $provider);
        }
        $integration = ActivityIntegration::findByProvider($provider);
        if (!$integration || (string)$integration->status !== 'active') {
            if (!$force) {
                return [
                    'created' => 0,
                    'updated' => 0,
                    'skipped' => 1,
                    'due' => false,
                    'message' => '平台未启用，已跳过',
                ];
            }
            throw new \RuntimeException('平台未启用：' . $provider);
        }
        if (!$force && !$integration->isSyncDue()) {
            return [
                'created' => 0,
                'updated' => 0,
                'skipped' => 1,
                'due' => false,
                'message' => '未到同步间隔',
                'last_synced_at' => (string)($integration->last_synced_at ?? ''),
                'next_sync_at' => $integration->nextSyncAt(),
                'sync_interval_minutes' => $integration->syncIntervalMinutes(),
            ];
        }

        $startedAt = date('Y-m-d H:i:s');
        $logId = Activity::db()->insert('activity_sync_logs', [
            'provider' => $provider,
            'status' => 'running',
            'message' => '',
            'metadata' => '{}',
            'started_at' => $startedAt,
            'finished_at' => null,
        ]);

        try {
            $result = $adapter->sync($integration);
            $finishedAt = date('Y-m-d H:i:s');
            Activity::db()->update('activity_sync_logs', [
                'status' => 'success',
                'message' => (string)($result['message'] ?? '同步完成'),
                'metadata' => json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'finished_at' => $finishedAt,
            ], 'id = :id', [':id' => (int)$logId]);
            $integration->updateSyncStatus('active', $finishedAt);
            (new ActivityCacheService())->rebuild();
            return $result;
        } catch (\Throwable $e) {
            $finishedAt = date('Y-m-d H:i:s');
            Activity::db()->update('activity_sync_logs', [
                'status' => 'failed',
                'message' => $e->getMessage(),
                'metadata' => '{}',
                'finished_at' => $finishedAt,
            ], 'id = :id', [':id' => (int)$logId]);
            throw $e;
        }
    }

    public function syncDue(?array $providers = null): array
    {
        $providers = $providers ?: $this->providers();
        $results = [];
        foreach ($providers as $provider) {
            $results[(string)$provider] = $this->sync((string)$provider);
        }
        return $results;
    }

    public function recentLogs(int $limit = 30): array
    {
        ActivityInstaller::install();
        return Activity::db()->fetchAll(
            'SELECT * FROM activity_sync_logs ORDER BY started_at DESC, id DESC LIMIT ' . max(1, min(100, $limit))
        );
    }
}
