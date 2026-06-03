@extends('layouts.admin')

@section('content')
    <form method="post" action="{{ $post ? '/admin/posts/'.$post->id.'/edit' : '/admin/posts/create' }}" class="admin-form">
        <input type="hidden" name="_csrf" value="{{ $csrf }}">
        <div class="form-row">
            <div class="form-group flex-2">
                <label>标题 *</label>
                <input type="text" name="title" value="{{ $post->title ?? '' }}" required>
            </div>
            <div class="form-group">
                <label>slug（伪静态）</label>
                <input type="text" name="slug" value="{{ $post->slug ?? '' }}" placeholder="留空自动生成">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>分类</label>
                <select name="category_id">
                    <option value="0">未分类</option>
                    @foreach($categories as $c)
                        <option value="{{ $c->id }}" {{ ($post->category_id ?? 0) == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
            {{-- 标签功能已下线,UI 隐藏 --}}
            <div class="form-group">
                <label>状态</label>
                <select name="status">
                    <option value="published" {{ ($post->status ?? 'published') === 'published' ? 'selected' : '' }}>已发布</option>
                    <option value="draft" {{ ($post->status ?? '') === 'draft' ? 'selected' : '' }}>草稿</option>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group flex-2">
                <label>摘要</label>
                <textarea name="summary" rows="2">{{ $post->summary ?? '' }}</textarea>
            </div>
            <div class="form-group">
                <label>封面 URL</label>
                <input type="text" name="cover" value="{{ $post->cover ?? '' }}" placeholder="/assets/uploads/...">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label><input type="checkbox" name="is_top" value="1" {{ ($post->is_top ?? 0) ? 'checked' : '' }}> 置顶</label>
                <label><input type="checkbox" name="is_recommend" value="1" {{ ($post->is_recommend ?? 0) ? 'checked' : '' }}> 推荐</label>
            </div>
        </div>
        <div class="form-group">
            <label>内容（Markdown）</label>
            <textarea name="markdown_content" rows="18" id="editor-md" placeholder="# 标题\n\n内容..." required>{{ $post ? $post->markdown() : '' }}</textarea>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> 保存</button>
            <a href="/admin/posts" class="btn"><i class="fa-solid fa-xmark"></i> 取消</a>
        </div>
    </form>
@endsection
