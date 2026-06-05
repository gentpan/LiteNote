@extends('layouts.admin')

@section('content')
    @php $isSystemPage = $page && $page->isSystem(); @endphp
    <form method="post" action="{{ $page ? '/admin/pages/'.$page->id.'/edit' : '/admin/pages/create' }}" class="admin-form" data-dirty-watch>
        <input type="hidden" name="_csrf" value="{{ $csrf }}">
        <div class="form-row">
            <div class="form-group flex-2">
                <label>标题 *</label>
                <input type="text" name="title" id="page-title" value="{{ $page->title ?? '' }}" required>
            </div>
            <div class="form-group">
                <label>slug</label>
                <input type="text" name="slug" value="{{ $page->slug ?? '' }}" {{ $isSystemPage ? 'disabled' : '' }}>
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
        @if($isSystemPage)
            <div class="admin-empty-state">系统功能页不编辑正文，只在这里控制标题、排序和菜单显示。</div>
        @else
            @php
                $editorId = 'page-editor-md';
                $editorName = 'markdown_content';
                $editorLabel = '页面内容（Markdown）';
                $editorValue = $editorMarkdown ?? '';
                $editorPreviewUrl = '/admin/posts/preview';
                $editorUploadUrl = '/admin/posts/upload-image';
                $editorUploadPurpose = 'page';
                $editorSummaryUrl = '';
                $editorCsrf = $csrf;
                $editorRequired = true;
                $editorShowSummary = false;
                $editorTitleInput = '#page-title';
                $editorSummaryInput = '';
                $editorWordsId = 'page-md-words';
                $editorLinesId = 'page-md-lines';
                $editorPreviewId = 'page-md-preview';
            @endphp
            @include('partials.admin-markdown-editor')
        @endif
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">保存</button>
            <a href="/admin/pages" class="btn">取消</a>
        </div>
    </form>
@endsection
