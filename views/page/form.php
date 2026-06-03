@extends('layouts.admin')

@section('content')
    <form method="post" action="{{ $page ? '/admin/pages/'.$page->id.'/edit' : '/admin/pages/create' }}" class="admin-form">
        <input type="hidden" name="_csrf" value="{{ $csrf }}">
        <div class="form-row">
            <div class="form-group flex-2">
                <label>标题 *</label>
                <input type="text" name="title" value="{{ $page->title ?? '' }}" required>
            </div>
            <div class="form-group">
                <label>slug</label>
                <input type="text" name="slug" value="{{ $page->slug ?? '' }}">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label><input type="checkbox" name="is_nav" value="1" {{ ($page->is_nav ?? 0) ? 'checked' : '' }}> 加入导航</label>
            </div>
            <div class="form-group">
                <label>排序</label>
                <input type="number" name="sort" value="{{ $page->sort ?? 0 }}">
            </div>
        </div>
        <div class="form-group">
            <label>内容（HTML）</label>
            <textarea name="content" rows="10">{{ $page->content ?? '' }}</textarea>
        </div>
        <div class="form-group">
            <label>Markdown</label>
            <textarea name="markdown_content" rows="10">{{ $page->markdown_content ?? '' }}</textarea>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">保存</button>
            <a href="/admin/pages" class="btn">取消</a>
        </div>
    </form>
@endsection
