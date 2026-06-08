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
                <article class="resource-card plugin-card">
                    <div class="resource-card-body">
                        <div class="plugin-main">
                            <strong class="plugin-name">{{ $plugin['name'] }}</strong>
                            <span class="plugin-desc">{{ $plugin['description'] ?: '这个插件暂未填写描述。' }}</span>
                        </div>
                        <div class="plugin-meta plugin-key" title="插件目录">{{ $plugin['key'] }}</div>
                        <div class="plugin-meta plugin-version" title="版本">{{ $plugin['version'] ?: '-' }}</div>
                        <div class="plugin-meta plugin-author" title="作者">{{ $plugin['author'] ?: '-' }}</div>
                        <div class="plugin-state">
                            @if($plugin['enabled'] ?? false)
                                <span class="status status-published">已启用</span>
                            @else
                                <span class="status status-draft">未启用</span>
                            @endif
                        </div>
                        <form method="post" action="/admin/plugins/{{ $plugin['key'] }}/toggle" class="plugin-toggle-form">
                            <input type="hidden" name="_csrf" value="{{ $csrf }}">
                            @if($plugin['enabled'] ?? false)
                                <button type="submit" class="btn btn-danger">禁用</button>
                            @else
                                <button type="submit" class="btn btn-primary">启用</button>
                            @endif
                        </form>
                    </div>
                </article>
            @endforeach
        </div>
    @endif
    </div>
@endsection
