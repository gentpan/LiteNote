@extends('layouts.admin')

@section('content')
    <div class="admin-toolbar">
        <a class="btn btn-primary" href="/admin/pages/create">+ 新建页面</a>
    </div>
    <table class="admin-table admin-action-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>标题</th>
                <th>类型</th>
                <th>slug</th>
                <th>菜单显示</th>
                <th>排序</th>
                <th>浏览</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            @foreach($pages as $p)
            <tr>
                <td>{{ $p->id }}</td>
                <td>
                    <a href="{{ $p->getUrl() }}" target="_blank">
                        <i class="{{ $p->iconClass() }}"></i>
                        {{ $p->title }}
                    </a>
                </td>
                <td>
                    @if($p->isSystem())
                        <span class="status status-published">系统</span>
                    @else
                        <span class="status status-draft">自定义</span>
                    @endif
                </td>
                <td><code>{{ $p->slug }}</code></td>
                <td>
                    <form method="post" action="/admin/pages/toggle" class="nav-toggle-form" data-ajax-toggle>
                        <input type="hidden" name="_csrf" value="{{ $csrf }}">
                        <input type="hidden" name="id" value="{{ $p->id }}">
                        <input type="hidden" name="is_nav" value="0">
                        <label class="cat-switch" title="{{ $p->is_nav ? '点击从菜单栏隐藏' : '点击在菜单栏显示' }}">
                            <input type="checkbox"
                                   name="is_nav"
                                   value="1"
                                   data-no-dirty
                                   aria-label="菜单显示"
                                   {{ $p->is_nav ? 'checked' : '' }}>
                            <span class="cat-switch-slider"></span>
                        </label>
                    </form>
                </td>
                <td>{{ $p->sort }}</td>
                <td>{{ $p->views }}</td>
                <td>
                    <div class="admin-action-bar">
                        <a href="/admin/pages/{{ $p->id }}/edit"
                           class="admin-action-btn admin-action-edit"
                           title="编辑"
                           aria-label="编辑">
                            <i class="fa-regular fa-pen-to-square"></i>
                        </a>
                        @if(!$p->isSystem())
                            <button type="submit"
                                    form="page-delete-form-{{ $p->id }}"
                                    class="admin-action-btn admin-action-delete"
                                    title="删除"
                                    aria-label="删除">
                                <i class="fa-regular fa-trash-can"></i>
                            </button>
                        @endif
                    </div>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    @foreach($pages as $p)
        @if(!$p->isSystem())
            <form id="page-delete-form-{{ $p->id }}" method="post" action="/admin/pages/delete" class="hidden"
                  data-confirm="确定删除这个页面？此操作不可撤销。"
                  data-confirm-title="删除页面"
                  data-confirm-text="确认删除">
                <input type="hidden" name="_csrf" value="{{ $csrf }}">
                <input type="hidden" name="id" value="{{ $p->id }}">
            </form>
        @endif
    @endforeach
@endsection
