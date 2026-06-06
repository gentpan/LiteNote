<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Activity;
use App\Models\Post;

final class ActivityService
{
    public const TYPES = [
        'music' => ['label' => '音乐', 'icon' => 'fa-solid fa-music'],
        'movie' => ['label' => '电影', 'icon' => 'fa-solid fa-clapperboard'],
        'food' => ['label' => '美食', 'icon' => 'fa-solid fa-utensils'],
        'sport' => ['label' => '运动', 'icon' => 'fa-solid fa-person-running'],
        'video' => ['label' => '视频', 'icon' => 'fa-solid fa-tv'],
        'coding' => ['label' => '代码', 'icon' => 'fa-solid fa-keyboard'],
        'ai' => ['label' => 'AI', 'icon' => 'fa-solid fa-robot'],
        'blog' => ['label' => '博客', 'icon' => 'fa-regular fa-file-lines'],
        'social' => ['label' => '社交', 'icon' => 'fa-regular fa-comments'],
        'manual' => ['label' => '手动', 'icon' => 'fa-solid fa-location-pin'],
    ];

    public const ACTIONS = [
        'watched' => '看过',
        'liked' => '喜欢',
        'played' => '播放',
        'saved' => '收藏',
        'published_post' => '发布文章',
        'updated_post' => '更新文章',
        'used_tokens' => '使用 AI',
        'committed' => '提交代码',
        'starred' => 'Star',
        'created_repo' => '创建仓库',
        'opened_issue' => '创建 Issue',
        'opened_pr' => '创建 PR',
        'created' => '创建',
        'posted' => '发布',
        'manual' => '记录',
    ];

    public static function normalizeType(string $type): string
    {
        $type = trim($type);
        return isset(self::TYPES[$type]) ? $type : 'manual';
    }

    public static function typeLabel(string $type): string
    {
        return self::TYPES[$type]['label'] ?? '记录';
    }

    public static function typeIcon(string $type): string
    {
        return self::TYPES[$type]['icon'] ?? 'fa-solid fa-circle';
    }

    public static function actionLabel(string $action): string
    {
        return self::ACTIONS[$action] ?? $action;
    }

    public function record(array $data): Activity
    {
        ActivityInstaller::install();
        $now = date('Y-m-d H:i:s');
        $type = self::normalizeType((string)($data['type'] ?? 'manual'));
        $metadata = $data['metadata'] ?? [];
        if (is_string($metadata)) {
            $decoded = json_decode($metadata, true);
            $metadata = is_array($decoded) ? $decoded : [];
        }

        $fields = [
            'type' => $type,
            'action' => trim((string)($data['action'] ?? 'manual')) ?: 'manual',
            'source' => trim((string)($data['source'] ?? 'manual')) ?: 'manual',
            'external_id' => trim((string)($data['external_id'] ?? '')) ?: null,
            'title' => trim((string)($data['title'] ?? '')),
            'content' => trim((string)($data['content'] ?? '')),
            'url' => trim((string)($data['url'] ?? '')),
            'icon' => trim((string)($data['icon'] ?? self::typeIcon($type))),
            'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'visibility' => $this->normalizeVisibility((string)($data['visibility'] ?? 'public')),
            'happened_at' => $this->normalizeDateTime((string)($data['happened_at'] ?? ''), $now),
            'created_at' => (string)($data['created_at'] ?? $now),
            'updated_at' => $now,
        ];

        if ($fields['title'] === '') {
            throw new \InvalidArgumentException('动态标题不能为空');
        }

        if (!empty($data['id'])) {
            $activity = Activity::find((int)$data['id']);
            if (!$activity) {
                throw new \RuntimeException('动态不存在');
            }
            unset($fields['created_at']);
            $activity->toArray();
            foreach ($fields as $key => $value) {
                $activity->{$key} = $value;
            }
            $activity->save();
            (new ActivityStatsService())->rebuildForDate(substr($fields['happened_at'], 0, 10));
            return $activity;
        }

        if ($fields['external_id']) {
            $existing = Activity::db()->fetchOne(
                'SELECT id FROM activities WHERE source = ? AND external_id = ? LIMIT 1',
                [$fields['source'], $fields['external_id']]
            );
            if ($existing) {
                $fields['id'] = (int)$existing['id'];
                return $this->record($fields);
            }
        }

        $activity = new Activity($fields);
        $activity->save();
        (new ActivityStatsService())->rebuildForDate(substr($fields['happened_at'], 0, 10));
        return $activity;
    }

    public function recordPost(Post $post, string $action): void
    {
        if ((string)($post->status ?? '') !== Post::STATUS_PUBLISHED) {
            return;
        }

        $publishedAt = (string)($post->published_at ?: $post->created_at ?: date('Y-m-d H:i:s'));
        $this->record([
            'type' => 'blog',
            'action' => $action,
            'source' => 'litenote',
            'external_id' => 'post:' . $action . ':' . (int)$post->id,
            'title' => ($action === 'updated_post' ? '更新文章：' : '发布文章：') . (string)$post->title,
            'url' => $post->getUrl(),
            'icon' => self::typeIcon('blog'),
            'happened_at' => $action === 'updated_post' ? date('Y-m-d H:i:s') : $publishedAt,
            'metadata' => [
                'post_id' => (int)$post->id,
                'slug' => (string)$post->slug,
            ],
        ]);
    }

    public function normalizeDateTime(string $value, ?string $fallback = null): string
    {
        $value = trim($value);
        if ($value === '') {
            return $fallback ?: date('Y-m-d H:i:s');
        }
        $value = str_replace('T', ' ', $value);
        $ts = strtotime($value);
        return $ts ? date('Y-m-d H:i:s', $ts) : ($fallback ?: date('Y-m-d H:i:s'));
    }

    private function normalizeVisibility(string $value): string
    {
        return in_array($value, ['public', 'private', 'hidden'], true) ? $value : 'public';
    }
}
