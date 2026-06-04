@extends('layouts.admin')

@section('content')
    <div class="admin-toolbar">
        <a class="btn btn-primary" href="/admin/pages/create">+ 新建页面</a>
    </div>
    <table class="admin-table">
        <thead>
            <tr><th>ID</th><th>标题</th><th>slug</th><th>导航</th><th>排序</th><th>浏览</th><th>操作</th></tr>
        </thead>
        <tbody>
            @foreach($pages as $p)
            <tr>
                <td>{{ $p->id }}</td>
                <td><a href="/page/{{ $p->slug }}.html" target="_blank">{{ $p->title }}</a></td>
                <td><code>{{ $p->slug }}</code></td>
                <td>{!! $p->is_nav ? '<i class="fa-solid fa-check"></i>' : '-' !!}</td>
                <td>{{ $p->sort }}</td>
                <td>{{ $p->views }}</td>
                <td>
                    <a href="/admin/pages/{{ $p->id }}/edit">编辑</a>
                    <form method="post" action="/admin/pages/delete" style="display:inline" onsubmit="return confirm('确定删除？')">
                        <input type="hidden" name="_csrf" value="{{ $csrf }}">
                        <input type="hidden" name="id" value="{{ $p->id }}">
                        <button type="submit" class="link-btn link-danger">删除</button>
                    </form>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
@endsection
