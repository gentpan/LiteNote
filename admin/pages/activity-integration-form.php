@extends('layouts.admin')

@section('content')
    @php
        $meta = $integration->metadata();
        $isActive = ($integration->status ?? 'inactive') === 'active';
        $hasToken = trim((string)($integration->access_token ?? '')) !== '';
        $hasRefresh = trim((string)($integration->refresh_token ?? '')) !== '';
    @endphp

    <div class="admin-toolbar">
        <a class="btn" href="/admin/activities/integrations"><i class="fa-solid fa-arrow-left"></i> 返回同步管理</a>
        <form method="post" action="/admin/activities/integrations/{{ $provider }}/sync">
            <input type="hidden" name="_csrf" value="{{ $csrf }}">
            <button type="submit" class="btn" {{ !$isActive ? 'disabled' : '' }}><i class="fa-solid fa-arrows-rotate"></i> 立即同步</button>
        </form>
    </div>

    <form method="post" action="/admin/activities/integrations/{{ $provider }}/save" class="admin-form" data-dirty-watch>
        <input type="hidden" name="_csrf" value="{{ $csrf }}">

        <div class="activity-integration-head activity-integration-form-head">
            <span class="activity-integration-icon"><i class="{{ $definition['icon'] }}"></i></span>
            <div>
                <h3>{{ $definition['label'] }}</h3>
                <p>{{ $definition['description'] }}</p>
            </div>
            <span class="status {{ $isActive ? 'status-published' : 'status-draft' }}">{{ $isActive ? '已启用' : '未启用' }}</span>
        </div>

        <div class="form-row compact-flags">
            <label><input type="radio" name="status" value="active" {{ $isActive ? 'checked' : '' }}> 启用</label>
            <label><input type="radio" name="status" value="inactive" {{ !$isActive ? 'checked' : '' }}> 停用</label>
        </div>

        <div class="form-group">
            <label>同步频率</label>
            <input type="number" min="0" max="1440" step="15" name="sync_interval_minutes" value="{{ $integration->syncIntervalMinutes() }}" placeholder="{{ \App\Models\ActivityIntegration::defaultIntervalMinutes($provider) }}">
            <span class="field-hint">单位：分钟。240 分钟等于每天 6 次，60 分钟等于每小时一次，0 表示每次计划任务都执行。</span>
        </div>

        @if(!empty($definition['token_label']))
            <div class="form-group">
                <label>{{ $definition['token_label'] }}</label>
                <input type="password" name="access_token" value="" placeholder="{{ $hasToken ? '已保存，留空则不修改' : $definition['token_label'] }}">
                @if(!empty($definition['token_hint']))<span class="field-hint">{{ $definition['token_hint'] }}</span>@endif
            </div>
        @endif

        @if(!empty($definition['refresh_label']))
            <div class="form-group">
                <label>{{ $definition['refresh_label'] }}</label>
                <input type="password" name="refresh_token" value="" placeholder="{{ $hasRefresh ? '已保存，留空则不修改' : $definition['refresh_label'] }}">
            </div>
        @endif

        @foreach(($definition['fields'] ?? []) as $key => $field)
            <div class="form-group">
                <label>{{ $field['label'] }}</label>
                @if(!empty($field['textarea']))
                    <textarea name="metadata_{{ $key }}" rows="5" placeholder="{{ $field['placeholder'] ?? '' }}">{{ $meta[$key] ?? '' }}</textarea>
                @else
                    <input type="{{ !empty($field['secret']) ? 'password' : 'text' }}" name="metadata_{{ $key }}" value="{{ !empty($field['secret']) && !empty($meta[$key] ?? '') ? '' : ($meta[$key] ?? '') }}" placeholder="{{ !empty($field['secret']) && !empty($meta[$key] ?? '') ? '已保存，留空则不修改' : ($field['placeholder'] ?? '') }}">
                @endif
            </div>
        @endforeach

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><i class="fa-regular fa-floppy-disk"></i> 保存 API 信息</button>
            <a href="/admin/activities/integrations" class="btn">取消</a>
        </div>
    </form>
@endsection
