<?php
declare(strict_types=1);

namespace App\Models;

final class ActivityIntegration
{
    private array $attributes = [];

    public const BUILTIN_PROVIDERS = [
        'github' => [
            'label' => 'GitHub',
            'icon' => 'fa-brands fa-github',
            'description' => '同步公开 Events：Star、Push、PR、Issue、Create。',
            'default_interval_minutes' => 240,
            'token_label' => 'GitHub Token',
            'token_hint' => '公开事件可不填；填 token 可提高限额。',
            'refresh_label' => '',
            'fields' => [
                'username' => ['label' => '用户名', 'placeholder' => 'gentpan'],
                'limit' => ['label' => '同步条数', 'placeholder' => '30'],
            ],
        ],
        'spotify' => [
            'label' => 'Spotify',
            'icon' => 'fa-brands fa-spotify',
            'description' => '同步最近播放，可选同步收藏歌曲。',
            'default_interval_minutes' => 60,
            'token_label' => 'Access Token',
            'token_hint' => '由 Spotify 授权按钮自动写入；通常不需要手动填写。',
            'refresh_label' => 'Refresh Token',
            'fields' => [
                'client_id' => ['label' => 'Client ID', 'placeholder' => 'Spotify app client id'],
                'client_secret' => ['label' => 'Client Secret', 'placeholder' => 'Spotify app client secret', 'secret' => true],
                'redirect_uri' => ['label' => 'Redirect URI', 'placeholder' => 'http://127.0.0.1:5555/admin/oauth/spotify/callback'],
                'limit' => ['label' => '同步条数', 'placeholder' => '20'],
                'sync_saved' => ['label' => '同步收藏歌曲', 'placeholder' => '1 / 0'],
            ],
        ],
        'netease' => [
            'label' => '网易云音乐',
            'icon' => 'fa-solid fa-music',
            'description' => '通过 NeteaseCloudMusicApi 同步最近播放、听歌排行和喜欢列表。',
            'default_interval_minutes' => 120,
            'token_label' => 'Cookie / MUSIC_U',
            'token_hint' => '可选。填写后优先同步最近播放；不填写时可用 UID 同步公开听歌排行。',
            'refresh_label' => '',
            'fields' => [
                'base_url' => ['label' => 'API 地址', 'placeholder' => 'http://127.0.0.1:3000'],
                'uid' => ['label' => '网易云 UID', 'placeholder' => '123456789'],
                'limit' => ['label' => '同步条数', 'placeholder' => '20'],
                'sync_recent' => ['label' => '同步最近播放', 'placeholder' => '1 / 0'],
                'sync_records' => ['label' => '同步听歌排行', 'placeholder' => '1 / 0'],
                'record_type' => ['label' => '排行范围', 'placeholder' => '1 最近一周 / 0 所有时间'],
                'sync_liked' => ['label' => '同步喜欢列表', 'placeholder' => '1 / 0'],
            ],
        ],
        'neodb' => [
            'label' => 'NeoDB',
            'icon' => 'fa-solid fa-clapperboard',
            'description' => '同步电影、书影音标记和短评。',
            'default_interval_minutes' => 240,
            'token_label' => 'Access Token',
            'token_hint' => 'NeoDB API Token。',
            'refresh_label' => '',
            'fields' => [
                'base_url' => ['label' => '站点地址', 'placeholder' => 'https://neodb.social'],
                'category' => ['label' => '分类', 'placeholder' => 'movie / tv / music / book'],
                'shelf_type' => ['label' => '状态', 'placeholder' => 'complete / progress / wishlist'],
                'limit' => ['label' => '同步条数', 'placeholder' => '30'],
            ],
        ],
        'bilibili' => [
            'label' => 'Bilibili',
            'icon' => 'fa-brands fa-bilibili',
            'description' => '只同步公开 RSS/JSON Feed，不读取私有 cookie。',
            'default_interval_minutes' => 240,
            'token_label' => '',
            'token_hint' => '',
            'refresh_label' => '',
            'fields' => [
                'feed_url' => ['label' => '公开 Feed 地址', 'placeholder' => 'RSSHub 或公开 JSON Feed URL'],
            ],
        ],
    ];

    /**
     * 内置 + 插件注册的 provider 定义合并。形态与原 const 数组完全一致,
     * 供 configured()/defaultIntervalMinutes()/ActivityController 以数组方式访问。
     *
     * @return array<string,array<string,mixed>>
     */
    public static function providers(): array
    {
        return array_merge(self::BUILTIN_PROVIDERS, \App\Services\Plugins\Registry::providers());
    }

    public function __construct(array $attributes = [])
    {
        $this->attributes = $attributes;
    }

    public function __get(string $name): mixed
    {
        return $this->attributes[$name] ?? null;
    }

    public function toArray(): array
    {
        return $this->attributes;
    }

    public static function all(): array
    {
        $rows = Activity::db()->fetchAll('SELECT * FROM activity_integrations ORDER BY provider ASC');
        return array_map(static fn(array $row): self => new self($row), $rows);
    }

    public static function configured(): array
    {
        $byProvider = [];
        foreach (self::all() as $item) {
            $byProvider[(string)$item->provider] = $item;
        }

        $out = [];
        foreach (self::providers() as $provider => $definition) {
            $out[$provider] = [
                'provider' => $provider,
                'definition' => $definition,
                'integration' => $byProvider[$provider] ?? new self([
                    'provider' => $provider,
                    'status' => 'inactive',
                    'metadata' => '{}',
                ]),
            ];
        }
        return $out;
    }

    public static function findByProvider(string $provider): ?self
    {
        $row = Activity::db()->fetchOne('SELECT * FROM activity_integrations WHERE provider = ? LIMIT 1', [$provider]);
        return $row ? new self($row) : null;
    }

    public static function saveProvider(string $provider, array $data): self
    {
        $now = date('Y-m-d H:i:s');
        $existing = self::findByProvider($provider);
        $payload = [
            'provider' => $provider,
            'access_token' => (string)($data['access_token'] ?? ''),
            'refresh_token' => (string)($data['refresh_token'] ?? ''),
            'expires_at' => trim((string)($data['expires_at'] ?? '')) ?: null,
            'status' => in_array((string)($data['status'] ?? 'active'), ['active', 'inactive'], true) ? (string)$data['status'] : 'active',
            'metadata' => json_encode((array)($data['metadata'] ?? []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'updated_at' => $now,
        ];

        if ($existing) {
            if ($payload['access_token'] === '') {
                $payload['access_token'] = (string)($existing->access_token ?? '');
            }
            if ($payload['refresh_token'] === '') {
                $payload['refresh_token'] = (string)($existing->refresh_token ?? '');
            }
            Activity::db()->update('activity_integrations', $payload, 'provider = :provider', [':provider' => $provider]);
        } else {
            $payload['created_at'] = $now;
            Activity::db()->insert('activity_integrations', $payload);
        }

        return self::findByProvider($provider) ?: new self($payload);
    }

    public function metadata(): array
    {
        $data = json_decode((string)($this->metadata ?? ''), true);
        return is_array($data) ? $data : [];
    }

    public static function defaultIntervalMinutes(string $provider): int
    {
        return max(0, (int)(self::providers()[$provider]['default_interval_minutes'] ?? 240));
    }

    public function syncIntervalMinutes(): int
    {
        $provider = (string)($this->provider ?? '');
        $meta = $this->metadata();
        $value = $meta['sync_interval_minutes'] ?? self::defaultIntervalMinutes($provider);
        return max(0, min(1440, (int)$value));
    }

    public function nextSyncAt(): ?string
    {
        $lastSyncedAt = trim((string)($this->last_synced_at ?? ''));
        if ($lastSyncedAt === '') {
            return null;
        }

        $interval = $this->syncIntervalMinutes();
        if ($interval <= 0) {
            return $lastSyncedAt;
        }

        $timestamp = strtotime($lastSyncedAt);
        if ($timestamp === false) {
            return null;
        }
        return date('Y-m-d H:i:s', $timestamp + ($interval * 60));
    }

    public function isSyncDue(): bool
    {
        if ((string)($this->status ?? 'inactive') !== 'active') {
            return false;
        }

        $lastSyncedAt = trim((string)($this->last_synced_at ?? ''));
        if ($lastSyncedAt === '') {
            return true;
        }

        $interval = $this->syncIntervalMinutes();
        if ($interval <= 0) {
            return true;
        }

        $nextSyncAt = $this->nextSyncAt();
        return $nextSyncAt === null || strtotime($nextSyncAt) <= time();
    }

    public function updateSyncStatus(string $status, ?string $lastSyncedAt = null): void
    {
        Activity::db()->update('activity_integrations', [
            'status' => $status,
            'last_synced_at' => $lastSyncedAt,
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'provider = :provider', [':provider' => (string)$this->provider]);
    }

    public function updateMetadata(array $metadata): void
    {
        $current = $this->metadata();
        $merged = array_replace($current, $metadata);
        Activity::db()->update('activity_integrations', [
            'metadata' => json_encode($merged, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'provider = :provider', [':provider' => (string)$this->provider]);
    }
}
