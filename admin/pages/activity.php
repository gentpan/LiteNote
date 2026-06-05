@extends('layouts.admin')

@section('content')
    <div class="admin-toolbar">
        <a class="btn btn-primary" href="/admin/activities/integrations"><i class="fa-solid fa-rotate"></i> 平台同步</a>
        <a class="btn" href="/activity" target="_blank"><i class="fa-solid fa-arrow-up-right-from-square"></i> 查看前台</a>
    </div>

    <table class="admin-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>动态</th>
                <th>类型</th>
                <th>来源</th>
                <th>可见性</th>
                <th>发生时间</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            @foreach($list as $item)
                <tr>
                    <td>{{ $item->id }}</td>
                    <td>
                        <div class="comment-cell">
                            <strong>{{ $item->title }}</strong>
                            @if($item->content)<small class="muted">{{ \App\Core\Helper::truncate((string)$item->content, 90) }}</small>@endif
                        </div>
                    </td>
                    <td>{{ $types[$item->type]['label'] ?? $item->type }}</td>
                    <td>{{ $item->source }}</td>
                    <td><span class="status status-{{ $item->visibility === 'public' ? 'published' : 'draft' }}">{{ $item->visibility }}</span></td>
                    <td>{!! \App\Core\Helper::dateTimeTag((string)$item->happened_at) !!}</td>
                    <td>
                        <div class="admin-action-bar">
                            <a href="/admin/activities/{{ $item->id }}/edit" class="admin-action-btn admin-action-edit" title="编辑" aria-label="编辑"><i class="fa-regular fa-pen-to-square"></i></a>
                            <button type="submit" form="activity-delete-form-{{ $item->id }}" class="admin-action-btn admin-action-delete" title="删除" aria-label="删除"><i class="fa-regular fa-trash-can"></i></button>
                        </div>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @foreach($list as $item)
        <form id="activity-delete-form-{{ $item->id }}" method="post" action="/admin/activities/delete" class="hidden"
              data-confirm="确定删除这条动态？此操作不可撤销。"
              data-confirm-title="删除动态"
              data-confirm-text="确认删除">
            <input type="hidden" name="_csrf" value="{{ $csrf }}">
            <input type="hidden" name="id" value="{{ $item->id }}">
        </form>
    @endforeach

    {!! $paginator ?? '' !!}
@endsection
