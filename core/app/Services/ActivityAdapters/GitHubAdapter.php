<?php
declare(strict_types=1);

namespace App\Services\ActivityAdapters;

use App\Models\ActivityIntegration;

final class GitHubAdapter extends BaseAdapter
{
    public function provider(): string
    {
        return 'github';
    }

    public function sync(ActivityIntegration $integration): array
    {
        $username = $this->meta($integration, 'username');
        if ($username === '') {
            throw new \RuntimeException('GitHub 用户名不能为空');
        }

        $limit = max(1, min(100, (int)$this->meta($integration, 'limit', '30')));
        $url = 'https://api.github.com/users/' . rawurlencode($username) . '/events/public?per_page=' . $limit;
        $headers = ['Accept: application/vnd.github+json', 'X-GitHub-Api-Version: 2022-11-28'];
        if (trim((string)$integration->access_token) !== '') {
            $headers[] = 'Authorization: Bearer ' . trim((string)$integration->access_token);
        }

        $events = $this->http->getJson($url, $headers, 15);
        if (!$events) {
            return $this->result(0, 0, 0, 'GitHub 没有返回公开事件');
        }

        $created = $updated = $skipped = 0;
        foreach ($events as $event) {
            if (!is_array($event)) {
                $skipped++;
                continue;
            }
            $mapped = $this->mapEvent($event);
            if (!$mapped) {
                $skipped++;
                continue;
            }
            $this->ingest($mapped) ? $created++ : $updated++;
        }

        return $this->result($created, $updated, $skipped, 'GitHub 同步完成');
    }

    private function mapEvent(array $event): ?array
    {
        $eventId = (string)($event['id'] ?? '');
        $eventType = (string)($event['type'] ?? '');
        $repo = is_array($event['repo'] ?? null) ? $event['repo'] : [];
        $repoName = (string)($repo['name'] ?? '');
        $repoUrl = $repoName !== '' ? 'https://github.com/' . $repoName : '';
        $payload = is_array($event['payload'] ?? null) ? $event['payload'] : [];
        $createdAt = $this->isoToSql((string)($event['created_at'] ?? ''));

        return match ($eventType) {
            'WatchEvent' => [
                'type' => 'coding',
                'action' => 'starred',
                'source' => 'github',
                'external_id' => 'github:event:' . $eventId,
                'title' => 'Star 了 ' . $repoName,
                'url' => $repoUrl,
                'happened_at' => $createdAt,
                'metadata' => ['repo' => $repoName, 'event_type' => $eventType],
            ],
            'PushEvent' => [
                'type' => 'coding',
                'action' => 'committed',
                'source' => 'github',
                'external_id' => 'github:event:' . $eventId,
                'title' => '提交了代码到 ' . $repoName,
                'content' => $this->pushContent($payload),
                'url' => $repoUrl,
                'happened_at' => $createdAt,
                'metadata' => [
                    'repo' => $repoName,
                    'event_type' => $eventType,
                    'commit_count' => count((array)($payload['commits'] ?? [])),
                ],
            ],
            'PullRequestEvent' => [
                'type' => 'coding',
                'action' => 'opened_pr',
                'source' => 'github',
                'external_id' => 'github:event:' . $eventId,
                'title' => 'PR ' . (($payload['action'] ?? '') === 'closed' ? '更新' : '创建') . '：' . $repoName,
                'url' => (string)($payload['pull_request']['html_url'] ?? $repoUrl),
                'happened_at' => $createdAt,
                'metadata' => ['repo' => $repoName, 'event_type' => $eventType, 'action' => (string)($payload['action'] ?? '')],
            ],
            'IssuesEvent' => [
                'type' => 'coding',
                'action' => 'opened_issue',
                'source' => 'github',
                'external_id' => 'github:event:' . $eventId,
                'title' => 'Issue ' . (string)($payload['action'] ?? '更新') . '：' . $repoName,
                'url' => (string)($payload['issue']['html_url'] ?? $repoUrl),
                'happened_at' => $createdAt,
                'metadata' => ['repo' => $repoName, 'event_type' => $eventType, 'action' => (string)($payload['action'] ?? '')],
            ],
            'CreateEvent' => [
                'type' => 'coding',
                'action' => 'created_repo',
                'source' => 'github',
                'external_id' => 'github:event:' . $eventId,
                'title' => '创建了 ' . ((string)($payload['ref_type'] ?? '') ?: '资源') . '：' . $repoName,
                'url' => $repoUrl,
                'happened_at' => $createdAt,
                'metadata' => ['repo' => $repoName, 'event_type' => $eventType, 'ref_type' => (string)($payload['ref_type'] ?? '')],
            ],
            default => null,
        };
    }

    private function pushContent(array $payload): string
    {
        $commits = array_slice((array)($payload['commits'] ?? []), 0, 3);
        $messages = [];
        foreach ($commits as $commit) {
            if (is_array($commit) && !empty($commit['message'])) {
                $messages[] = trim(strtok((string)$commit['message'], "\n") ?: (string)$commit['message']);
            }
        }
        return implode("\n", $messages);
    }

}
