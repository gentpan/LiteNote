@extends('layouts.admin')

@section('content')
    <div class="admin-toolbar">
        <a class="btn btn-primary" href="/admin/talk/create">+ 写滔客</a>
    </div>
    <table class="admin-table">
        <thead>
            <tr><th>ID</th><th>内容</th><th>心情</th><th>公开</th><th>时间</th><th>操作</th></tr>
        </thead>
        <tbody>
            @foreach($list as $s)
            <tr>
                <td>{{ $s->id }}</td>
                <td><div class="comment-cell">{{ \App\Core\Helper::truncate($s->content, 100) }}</div></td>
                <td>{{ $s->mood }}</td>
                <td>{!! $s->is_public ? '<span class="admin-check-icon admin-check-icon-sm" aria-hidden="true"><i class="fa-solid fa-check"></i></span>' : '<i class="fa-solid fa-xmark"></i>' !!}</td>
                <td>{!! \App\Core\Helper::timeTag($s->created_at) !!}</td>
                <td>
                    <div class="admin-action-bar">
                        <a href="/admin/talk/{{ $s->id }}/edit"
                           class="admin-action-btn admin-action-edit"
                           title="编辑"
                           aria-label="编辑">
                            <i class="fa-regular fa-pen-to-square"></i>
                        </a>
                        <button type="submit"
                                form="talk-delete-form-{{ $s->id }}"
                                class="admin-action-btn admin-action-delete"
                                title="删除"
                                aria-label="删除">
                            <i class="fa-regular fa-trash-can"></i>
                        </button>
                    </div>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    @foreach($list as $s)
        <form id="talk-delete-form-{{ $s->id }}" method="post" action="/admin/talk/delete" class="hidden"
              data-confirm="确定删除这条说说？此操作不可撤销。"
              data-confirm-title="删除说说"
              data-confirm-text="确认删除">
            <input type="hidden" name="_csrf" value="{{ $csrf }}">
            <input type="hidden" name="id" value="{{ $s->id }}">
        </form>
    @endforeach
    {!! $paginator ?? '' !!}
@endsection
