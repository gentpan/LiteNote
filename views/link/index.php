@extends('layouts.admin')

@section('content')
    <div class="admin-toolbar">
        <button class="btn btn-primary" onclick="document.getElementById('new-link-form').classList.toggle('hidden')">+ 添加友链</button>
    </div>

    <form id="new-link-form" method="post" action="/admin/links/save" class="admin-form hidden">
        <input type="hidden" name="_csrf" value="{{ $csrf }}">
        <div class="form-row">
            <div class="form-group">
                <label>名称 *</label>
                <input type="text" name="name" required>
            </div>
            <div class="form-group flex-2">
                <label>URL *</label>
                <input type="url" name="url" required>
            </div>
            <div class="form-group">
                <label>排序</label>
                <input type="number" name="sort" value="0">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Logo URL</label>
                <input type="text" name="logo">
            </div>
            <div class="form-group flex-2">
                <label>RSS URL（可选，用于抓取最新文章）</label>
                <input type="url" name="rss_url" placeholder="https://example.com/feed.xml">
            </div>
        </div>
        <div class="form-group">
            <label>描述</label>
            <input type="text" name="description">
        </div>
        <div class="form-group">
            <label><input type="checkbox" name="is_enabled" value="1" checked> 启用</label>
        </div>
        <button type="submit" class="btn btn-primary">保存</button>
    </form>

    <table class="admin-table">
        <thead>
            <tr><th>ID</th><th>名称</th><th>URL</th><th>RSS</th><th>排序</th><th>启用</th><th>操作</th></tr>
        </thead>
        <tbody>
            @foreach($links as $l)
            <tr>
                <td>{{ $l->id }}</td>
                <td>{{ $l->name }}</td>
                <td><a href="{{ $l->url }}" target="_blank" rel="nofollow noopener">{{ $l->url }}</a></td>
                <td>
                    @if($l->rss_url)
                        @if(($rssStatus[$l->id] ?? false))
                            <span class="status status-published"><i class="fa-solid fa-check"></i> 可用</span>
                        @else
                            <span class="status status-draft"><i class="fa-solid fa-xmark"></i> 抓取失败</span>
                        @endif
                        <code style="display:block;font-size:11px">{{ $l->rss_url }}</code>
                    @else
                        <span class="muted">无</span>
                    @endif
                </td>
                <td>{{ $l->sort }}</td>
                <td>{{ $l->is_enabled ? '<i class="fa-solid fa-check"></i>' : '<i class="fa-solid fa-xmark"></i>' }}</td>
                <td>
                    @if($l->rss_url)
                    <form method="post" action="/admin/links/refresh" style="display:inline">
                        <input type="hidden" name="_csrf" value="{{ $csrf }}">
                        <input type="hidden" name="id" value="{{ $l->id }}">
                        <button type="submit" class="link-btn">刷新RSS</button>
                    </form>
                    @endif
                    <form method="post" action="/admin/links/delete" style="display:inline" onsubmit="return confirm('确定删除？')">
                        <input type="hidden" name="_csrf" value="{{ $csrf }}">
                        <input type="hidden" name="id" value="{{ $l->id }}">
                        <button type="submit" class="link-btn link-danger">删除</button>
                    </form>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
@endsection
