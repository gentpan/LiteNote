<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Background;
use App\Core\Helper;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\Activity;
use App\Models\ActivityIntegration;
use App\Services\ActivityInstaller;
use App\Services\ActivityService;
use App\Services\ActivityStatsService;
use App\Services\ActivitySyncService;

final class ActivityController
{
    public function index(): string
    {
        ActivityInstaller::install();
        $page = max(1, (int)($_GET['page'] ?? 1));
        $type = trim((string)($_GET['type'] ?? ''));
        $filters = [];
        if ($type !== '' && isset(ActivityService::TYPES[$type]) && $type !== 'manual') {
            $filters['type'] = $type;
        }
        $result = Activity::paginate($page, 20, $filters, false);

        return View::render('activity.index', [
            'list' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'perPage' => 20,
            'activeType' => $filters['type'] ?? '',
            'types' => $this->visibleTypes(),
            'paginator' => Helper::paginate($page, $result['total'], 20, '/admin/activities'),
            'csrf' => Session::csrfToken(),
            'pageTitle' => '动态管理',
        ], 'layouts.admin');
    }

    public function integrations(): string
    {
        ActivityInstaller::install();
        return View::render('activity.integrations', [
            'providers' => ActivityIntegration::configured(),
            'logs' => (new ActivitySyncService())->recentLogs(40),
            'csrf' => Session::csrfToken(),
            'pageTitle' => '动态同步',
        ], 'layouts.admin');
    }

    public function editIntegration(Request $request, array $params): string
    {
        ActivityInstaller::install();
        $provider = (string)($params['provider'] ?? '');
        if (!isset(ActivityIntegration::providers()[$provider])) {
            Session::flash('error', '不支持的平台');
            Response::redirect('/admin/activities/integrations');
        }

        return View::render('activity.integration-form', [
            'provider' => $provider,
            'definition' => ActivityIntegration::providers()[$provider],
            'integration' => ActivityIntegration::findByProvider($provider) ?: new ActivityIntegration([
                'provider' => $provider,
                'status' => 'inactive',
                'metadata' => '{}',
            ]),
            'csrf' => Session::csrfToken(),
            'pageTitle' => '配置同步',
        ], 'layouts.admin');
    }

    public function saveIntegration(Request $request, array $params): never
    {
        $provider = (string)($params['provider'] ?? '');
        if (!isset(ActivityIntegration::providers()[$provider])) {
            Session::flash('error', '不支持的平台');
            Response::redirect('/admin/activities/integrations');
        }

        $definition = ActivityIntegration::providers()[$provider];
        $existing = ActivityIntegration::findByProvider($provider);
        $existingMeta = $existing ? $existing->metadata() : [];
        $metadata = [];
        foreach (($definition['fields'] ?? []) as $key => $field) {
            $value = trim((string)$request->input('metadata_' . $key, ''));
            if ($provider === 'spotify' && $key === 'redirect_uri' && $value === '') {
                $value = $this->defaultSpotifyRedirectUri();
            }
            if ($value === '' && !empty($field['secret']) && isset($existingMeta[$key])) {
                $value = (string)$existingMeta[$key];
            }
            $metadata[$key] = $value;
        }
        $metadata['sync_interval_minutes'] = max(0, min(1440, (int)$request->input(
            'sync_interval_minutes',
            ActivityIntegration::defaultIntervalMinutes($provider)
        )));

        ActivityIntegration::saveProvider($provider, [
            'status' => (string)$request->input('status', 'inactive'),
            'access_token' => trim((string)$request->input('access_token', '')),
            'refresh_token' => trim((string)$request->input('refresh_token', '')),
            'expires_at' => trim((string)$request->input('expires_at', '')),
            'metadata' => $metadata,
        ]);

        Session::flash('success', ($definition['label'] ?? $provider) . ' 配置已保存');
        Response::redirect('/admin/activities/integrations');
    }

    private function defaultSpotifyRedirectUri(): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = (string)($_SERVER['HTTP_HOST'] ?? '127.0.0.1:5555');
        return $scheme . '://' . $host . '/admin/oauth/spotify/callback';
    }

    public function syncIntegration(Request $request, array $params): never
    {
        $provider = (string)($params['provider'] ?? '');
        Background::run(function () use ($provider): void {
            try {
                (new ActivitySyncService())->sync($provider, true);
            } catch (\Throwable $e) {
                error_log('LiteNote activity sync failed: ' . $e->getMessage());
            }
        });
        Session::flash('success', '同步任务已提交，请稍后刷新页面查看最新日志');
        Response::redirect('/admin/activities/integrations');
    }

    public function edit(Request $request, array $params): string
    {
        $item = Activity::find((int)($params['id'] ?? 0));
        if (!$item) {
            Session::flash('error', '动态不存在');
            Response::redirect('/admin/activities');
        }
        return $this->form($item);
    }

    private function form(?Activity $item): string
    {
        return View::render('activity.form', [
            'item' => $item,
            'types' => $this->visibleTypes(),
            'actions' => ActivityService::ACTIONS,
            'csrf' => Session::csrfToken(),
            'pageTitle' => '编辑动态',
        ], 'layouts.admin');
    }

    public function update(Request $request, array $params): never
    {
        $this->save($request, (int)($params['id'] ?? 0));
    }

    private function save(Request $request, ?int $id): never
    {
        $title = trim((string)$request->input('title', ''));
        if ($title === '') {
            Session::flash('error', '标题不能为空');
            Response::redirect("/admin/activities/{$id}/edit");
        }

        $metadata = [];
        $metadataJson = trim((string)$request->input('metadata', ''));
        if ($metadataJson !== '') {
            $decoded = json_decode($metadataJson, true);
            if (!is_array($decoded)) {
                Session::flash('error', 'Metadata 必须是有效 JSON');
                Response::redirect("/admin/activities/{$id}/edit");
            }
            $metadata = $decoded;
        }
        $rating = trim((string)$request->input('rating', ''));
        if ($rating !== '') {
            $metadata['rating'] = (float)$rating;
            $metadata['rating_max'] = 5;
        }

        try {
            (new ActivityService())->record([
                'id' => $id,
                'type' => (string)$request->input('type', 'manual'),
                'action' => (string)$request->input('action', 'manual'),
                'source' => (string)$request->input('source', 'manual'),
                'external_id' => (string)$request->input('external_id', ''),
                'title' => $title,
                'content' => (string)$request->input('content', ''),
                'url' => (string)$request->input('url', ''),
                'visibility' => (string)$request->input('visibility', 'public'),
                'happened_at' => (string)$request->input('happened_at', ''),
                'metadata' => $metadata,
            ]);
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
            Response::redirect("/admin/activities/{$id}/edit");
        }

        Session::flash('success', '动态已更新');
        Response::redirect('/admin/activities');
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

    public function destroy(Request $request): never
    {
        $item = Activity::find((int)$request->input('id', 0));
        if ($item) {
            $date = substr((string)$item->happened_at, 0, 10);
            $item->delete();
            (new ActivityStatsService())->rebuildForDate($date);
        }
        Session::flash('success', '动态已删除');
        Response::redirect('/admin/activities');
    }
}
