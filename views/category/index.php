@extends('layouts.admin')

@section('content')
    <div class="admin-toolbar">
        <button class="btn btn-primary" onclick="document.getElementById('new-cat-form').classList.toggle('hidden')">+ 新建分类</button>
    </div>

    <form id="new-cat-form" method="post" action="/admin/categories/save" class="admin-form hidden">
        <input type="hidden" name="_csrf" value="{{ $csrf }}">
        <div class="form-row">
            <div class="form-group">
                <label>名称 *</label>
                <input type="text" name="name" required>
            </div>
            <div class="form-group">
                <label>slug</label>
                <input type="text" name="slug" placeholder="留空自动生成">
            </div>
            <div class="form-group">
                <label>排序</label>
                <input type="number" name="sort" value="0">
            </div>
        </div>
        <div class="form-group">
            <label>描述</label>
            <input type="text" name="description">
        </div>
        <button type="submit" class="btn btn-primary">保存</button>
    </form>

    <table class="admin-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>名称</th>
                <th>slug</th>
                <th>文章数</th>
                <th>排序</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            @foreach($categories as $c)
            <tr>
                <td>{{ $c->id }}</td>
                <td>{{ $c->name }}</td>
                <td><code>{{ $c->slug }}</code></td>
                <td>{{ $counts[$c->id] ?? 0 }}</td>
                <td>{{ $c->sort }}</td>
                <td>
                    <form method="post" action="/admin/categories/save" style="display:inline">
                        <input type="hidden" name="_csrf" value="{{ $csrf }}">
                        <input type="hidden" name="id" value="{{ $c->id }}">
                        <input type="hidden" name="name" value="{{ $c->name }}">
                        <input type="hidden" name="slug" value="{{ $c->slug }}">
                        <input type="hidden" name="description" value="{{ $c->description }}">
                        <input type="number" name="sort" value="{{ $c->sort }}" style="width:60px">
                        <button type="submit" class="link-btn">改排序</button>
                    </form>
                    <form method="post" action="/admin/categories/delete" style="display:inline" onsubmit="return confirm('确定删除？')">
                        <input type="hidden" name="_csrf" value="{{ $csrf }}">
                        <input type="hidden" name="id" value="{{ $c->id }}">
                        <button type="submit" class="link-btn link-danger">删除</button>
                    </form>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
@endsection
