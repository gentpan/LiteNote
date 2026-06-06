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
                    <div class="resource-shot">
                        @if(!empty($plugin['screenshot']))
                            <img src="{{ $plugin['screenshot'] }}" alt="{{ $plugin['name'] }} screenshot" loading="lazy">
                        @else
                            <div class="resource-shot-empty">
                                <i class="fa-solid fa-plug-circle-bolt"></i>
                                <span>No Screenshot</span>
                            </div>
                        @endif
                    </div>
                    <div class="resource-card-body">
                        <div class="resource-card-title">
                            <div>
                                <h3>{{ $plugin['name'] }}</h3>
                                <p>{{ $plugin['description'] ?: '这个插件暂未填写描述。' }}</p>
                            </div>
                            <span class="status status-draft">已安装</span>
                        </div>
                        <div class="resource-meta">
                            <span><i class="fa-solid fa-folder"></i> {{ $plugin['key'] }}</span>
                            @if(!empty($plugin['version']))
                                <span><i class="fa-solid fa-code-branch"></i> {{ $plugin['version'] }}</span>
                            @endif
                            @if(!empty($plugin['author']))
                                <span><i class="fa-regular fa-user"></i> {{ $plugin['author'] }}</span>
                            @endif
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    @endif
    </div>
@endsection
