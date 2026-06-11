@extends('layouts.admin')

@section('content')
    <div class="admin-toolbar">
        <a class="btn btn-primary" href="/admin/activities"><i class="fa-solid fa-list"></i> 动态列表</a>
        <a class="btn" href="/activity" target="_blank"><i class="fa-solid fa-arrow-up-right-from-square"></i> 查看前台</a>
        <span class="muted">一行一个平台，后台只保存 API 信息，前台只读取同步后的本地动态数据。</span>
    </div>

    <table class="admin-table admin-action-table activity-integration-table">
        <thead>
            <tr>
                <th>平台</th>
                <th>状态</th>
                <th>API 信息</th>
                <th>频率</th>
                <th>最近同步</th>
                <th>说明</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            @foreach($providers as $provider => $row)
                @php
                    $def = $row['definition'];
                    $integration = $row['integration'];
                    $meta = $integration->metadata();
                    $isActive = ($integration->status ?? 'inactive') === 'active';
                    $hasToken = trim((string)($integration->access_token ?? '')) !== '';
                    $hasRefresh = trim((string)($integration->refresh_token ?? '')) !== '';
                    $filled = 0;
                    foreach (($def['fields'] ?? []) as $key => $field) {
                        if (trim((string)($meta[$key] ?? '')) !== '') $filled++;
                    }
                    if ($hasToken) $filled++;
                    if ($hasRefresh) $filled++;
                    $fieldTotal = count($def['fields'] ?? []) + (!empty($def['token_label']) ? 1 : 0) + (!empty($def['refresh_label']) ? 1 : 0);
                    $interval = $integration->syncIntervalMinutes();
                    $nextSyncAt = $integration->nextSyncAt();
                @endphp
                <tr>
                    <td>
                        <div class="activity-provider-cell">
                            <span class="activity-integration-icon"><i class="{{ $def['icon'] }}"></i></span>
                            <div>
                                <strong>{{ $def['label'] }}</strong>
                                <small class="muted">{{ $provider }}</small>
                            </div>
                        </div>
                    </td>
                    <td><span class="status {{ $isActive ? 'status-published' : 'status-draft' }}">{{ $isActive ? '已启用' : '未启用' }}</span></td>
                    <td>
                        @if($filled > 0)
                            <span class="badge">{{ $filled }}/{{ $fieldTotal }}</span>
                        @else
                            <span class="muted">未配置</span>
                        @endif
                    </td>
                    <td>
                        <strong>{{ $interval > 0 ? $interval . ' 分钟' : '每次任务' }}</strong>
                        <small class="muted">
                            下次：{!! $nextSyncAt ? \App\Core\Helper::dateTimeTag((string)$nextSyncAt) : '首次任务' !!}
                        </small>
                    </td>
                    <td>{!! $integration->last_synced_at ? \App\Core\Helper::dateTimeTag((string)$integration->last_synced_at) : '<span class="muted">从未</span>' !!}</td>
                    <td><span class="muted">{{ $def['description'] }}</span></td>
                    <td>
                        <div class="admin-action-bar">
                            <a href="/admin/activities/integrations/{{ $provider }}/edit" class="admin-action-btn admin-action-edit" title="设置" aria-label="设置"><i class="fa-solid fa-gear"></i></a>
                            <form method="post" action="/admin/activities/integrations/{{ $provider }}/sync">
                                <input type="hidden" name="_csrf" value="{{ $csrf }}">
                                <button type="submit" class="admin-action-btn admin-action-refresh" title="立即同步" aria-label="立即同步" {{ !$isActive ? 'disabled' : '' }}><i class="fa-solid fa-arrows-rotate"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <section class="admin-form activity-sync-log">
        <h3 class="admin-form-title"><i class="fa-solid fa-clock-rotate-left"></i> 同步日志</h3>
        <table class="admin-table activity-sync-log-table">
            <thead>
                <tr>
                    <th>平台</th>
                    <th>状态</th>
                    <th>信息</th>
                    <th>开始时间</th>
                    <th>结束时间</th>
                </tr>
            </thead>
            <tbody>
                @if(empty($logs))
                    <tr><td colspan="5" class="empty">暂无同步日志</td></tr>
                @else
                    @foreach($logs as $log)
                        <tr>
                            <td>{{ $log['provider'] }}</td>
                            <td><span class="status {{ $log['status'] === 'success' ? 'status-published' : ($log['status'] === 'failed' ? 'status-spam' : 'status-pending') }}">{{ $log['status'] }}</span></td>
                            <td>{{ $log['message'] }}</td>
                            <td>{!! \App\Core\Helper::dateTimeTag((string)$log['started_at']) !!}</td>
                            <td>{!! !empty($log['finished_at']) ? \App\Core\Helper::dateTimeTag((string)$log['finished_at']) : '<span class="muted">-</span>' !!}</td>
                        </tr>
                    @endforeach
                @endif
            </tbody>
        </table>
    </section>
@endsection
