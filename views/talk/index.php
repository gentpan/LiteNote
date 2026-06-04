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
                <td>{!! $s->is_public ? '<i class="fa-solid fa-check"></i>' : '<i class="fa-solid fa-xmark"></i>' !!}</td>
                <td>{!! \App\Core\Helper::timeTag($s->created_at) !!}</td>
                <td>
                    <a href="/admin/talk/{{ $s->id }}/edit">编辑</a>
                    <form method="post" action="/admin/talk/delete" style="display:inline" onsubmit="return confirm('确定删除？')">
                        <input type="hidden" name="_csrf" value="{{ $csrf }}">
                        <input type="hidden" name="id" value="{{ $s->id }}">
                        <button type="submit" class="link-btn link-danger">删除</button>
                    </form>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    {!! $paginator ?? '' !!}
@endsection
