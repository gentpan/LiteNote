@extends('layouts.admin')

@section('content')
    <form method="post" action="{{ $post ? '/admin/posts/'.$post->id.'/edit' : '/admin/posts/create' }}" class="admin-form" data-dirty-watch>
        <input type="hidden" name="_csrf" value="{{ $csrf }}">
        <div class="form-row">
            <div class="form-group flex-2">
                <label>标题 *</label>
                <input type="text" name="title" id="post-title" value="{{ $post->title ?? '' }}" required>
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
                <textarea name="summary" id="post-summary" rows="2">{{ $post->summary ?? '' }}</textarea>
            </div>
            <div class="form-group">
                <label>特色图</label>
                <div class="cover-upload">
                    <input type="text" name="cover" id="cover-url" value="{{ $post->cover ?? '' }}" placeholder="/assets/uploads/...">
                    <button type="button" class="btn" id="cover-upload-btn"><i class="fa-regular fa-image"></i> 上传</button>
                    <input type="file" id="cover-file" accept="image/*" hidden>
                </div>
                <div class="cover-preview {{ empty($post->cover) ? 'hidden' : '' }}" id="cover-preview">
                    <img src="{{ $post->cover ?? '' }}" alt="特色图预览">
                </div>
            </div>
        </div>
        @php
            $editorId = 'editor-md';
            $editorName = 'markdown_content';
            $editorLabel = '内容（Markdown）';
            $editorValue = $post ? $post->markdown() : '';
            $editorPreviewUrl = '/admin/posts/preview';
            $editorUploadUrl = '/admin/posts/upload-image';
            $editorUploadPurpose = 'post';
            $editorSummaryUrl = '/admin/posts/summary';
            $editorCsrf = $csrf;
            $editorRequired = true;
            $editorShowSummary = true;
            $editorTitleInput = '#post-title';
            $editorSummaryInput = '#post-summary';
            $editorWordsId = 'md-words';
            $editorLinesId = 'md-lines';
            $editorPreviewId = 'md-preview';
        @endphp
        @include('partials.admin-markdown-editor')
        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> 保存</button>
            <a href="/admin/posts" class="btn"><i class="fa-solid fa-xmark"></i> 取消</a>
        </div>
    </form>
@endsection
