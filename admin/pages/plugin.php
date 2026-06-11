@extends('layouts.admin')

@section('content')
    <div class="settings-page-shell resource-page-shell">
    <div class="resource-page-head">
        <div>
            <strong>{{ count($plugins) }}</strong>
            <span>个插件包</span>
        </div>
        <div>
            <span class="muted">插件目录</span>
            <code>/plugins</code>
        </div>
    </div>

    @if(empty($plugins))
        <div class="admin-empty-state">暂无插件包。</div>
    @else
        <div class="resource-card-grid plugin-card-grid">
            @foreach($plugins as $plugin)
                <form method="post"
                      action="/admin/plugins/{{ $plugin['key'] }}/toggle"
                      class="resource-card plugin-card plugin-card-form {{ ($plugin['enabled'] ?? false) ? 'is-enabled' : '' }}"
                      title="{{ ($plugin['enabled'] ?? false) ? '点击禁用插件' : '点击启用插件' }}"
                      data-confirm="{{ ($plugin['enabled'] ?? false) ? '确认关闭这个插件？关闭后相关功能将不可用。' : '确认启用这个插件？' }}"
                      data-confirm-title="{{ ($plugin['enabled'] ?? false) ? '关闭插件' : '启用插件' }}"
                      data-confirm-text="{{ ($plugin['enabled'] ?? false) ? '确认关闭' : '确认启用' }}">
                    <input type="hidden" name="_csrf" value="{{ $csrf }}">
                    <div class="resource-card-body">
                        <div class="plugin-main">
                            <strong class="plugin-name">{{ $plugin['name'] }}</strong>
                            <span class="plugin-desc">{{ $plugin['description'] ?: '这个插件暂未填写描述。' }}</span>
                        </div>
                        <div class="plugin-meta-row">
                            <div class="plugin-meta plugin-version" title="版本">{{ $plugin['version'] ?: '-' }}</div>
                        </div>
                        <span class="plugin-state-text">{{ ($plugin['enabled'] ?? false) ? '已启用' : '未启用' }}</span>
                    </div>
                    <button type="submit" class="plugin-card-submit" aria-label="{{ ($plugin['enabled'] ?? false) ? '禁用插件 ' . $plugin['name'] : '启用插件 ' . $plugin['name'] }}"></button>
                </form>
            @endforeach
        </div>
    @endif
    </div>
@endsection
