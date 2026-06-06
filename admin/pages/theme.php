@extends('layouts.admin')

@section('content')
    <div class="settings-page-shell resource-page-shell">
    <div class="resource-page-head">
        <div>
            <strong>{{ count($themes) }}</strong>
            <span>个主题包</span>
        </div>
        <div>
            <span class="muted">当前启用</span>
            <strong>{{ $themes[$activeTheme]['name'] ?? $activeTheme }}</strong>
        </div>
    </div>

    @if(empty($themes))
        <div class="admin-empty-state">暂无可用主题。</div>
    @else
        <div class="resource-card-grid theme-card-grid">
            @foreach($themes as $theme)
                @php
                    $isActive = (bool)($theme['active'] ?? false);
                    $isProtected = (bool)($theme['protected'] ?? false);
                    $canDelete = !$isActive && !$isProtected;
                @endphp
                <article class="resource-card theme-card {{ $isActive ? 'is-active' : '' }}">
                    <div class="resource-shot">
                        @if(!empty($theme['screenshot']))
                            <img src="{{ $theme['screenshot'] }}" alt="{{ $theme['name'] }} screenshot" loading="lazy">
                        @else
                            <div class="resource-shot-empty">
                                <i class="fa-solid fa-image"></i>
                                <span>No Screenshot</span>
                            </div>
                        @endif
                        @if($isActive)
                            <span class="resource-shot-badge"><i class="fa-solid fa-circle-check"></i> 启用中</span>
                        @endif
                    </div>
                    <div class="resource-card-body">
                        <div class="resource-card-title">
                            <div>
                                <h3>{{ $theme['name'] }}</h3>
                                <p>{{ $theme['description'] ?: '这个主题暂未填写描述。' }}</p>
                            </div>
                            @if($isProtected)
                                <span class="status status-draft">默认</span>
                            @endif
                        </div>
                        <div class="resource-meta">
                            <span><i class="fa-solid fa-folder"></i> {{ $theme['key'] }}</span>
                            @if(!empty($theme['version']))
                                <span><i class="fa-solid fa-code-branch"></i> {{ $theme['version'] }}</span>
                            @endif
                            @if(!empty($theme['author']))
                                <span><i class="fa-regular fa-user"></i> {{ $theme['author'] }}</span>
                            @endif
                        </div>
                    </div>
                    <div class="resource-actions">
                        @if($isActive)
                            <button class="btn btn-primary" type="button" disabled><i class="fa-solid fa-check"></i> 已启用</button>
                        @else
                            <form method="post" action="/admin/themes/activate">
                                <input type="hidden" name="_csrf" value="{{ $csrf }}">
                                <input type="hidden" name="theme" value="{{ $theme['key'] }}">
                                <button class="btn btn-primary" type="submit"><i class="fa-solid fa-rotate"></i> 切换主题</button>
                            </form>
                        @endif

                        @if($canDelete)
                            <form method="post"
                                  action="/admin/themes/delete"
                                  data-confirm="确定删除这个主题？主题目录和文件会被永久移除，此操作不可撤销。"
                                  data-confirm-title="删除主题"
                                  data-confirm-text="确认删除">
                                <input type="hidden" name="_csrf" value="{{ $csrf }}">
                                <input type="hidden" name="theme" value="{{ $theme['key'] }}">
                                <button class="btn btn-danger" type="submit"><i class="fa-regular fa-trash-can"></i> 删除主题</button>
                            </form>
                        @else
                            <button class="btn btn-danger" type="button" disabled><i class="fa-regular fa-trash-can"></i> 删除主题</button>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    @endif
    </div>
@endsection
