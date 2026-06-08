@extends('layouts.admin')

@section('content')
    @php
        $isSystemPage = $page && $page->isSystem();
        $pageIsNav = $page ? (int)($page->is_nav ?? 0) === 1 : true;
    @endphp
    <form method="post" action="{{ $page ? '/admin/pages/'.$page->id.'/edit' : '/admin/pages/create' }}" class="admin-form post-editor-form page-editor-form" data-dirty-watch>
        <input type="hidden" name="_csrf" value="{{ $csrf }}">
        <div class="post-editor-title-row">
            <input type="text" name="title" id="page-title" value="{{ $page->title ?? '' }}" placeholder="在此输入页面标题..." required>
        </div>

        <div class="post-editor-layout">
            <div class="post-editor-main">
                @if($isSystemPage)
                    <div class="admin-empty-state page-system-empty">
                        系统功能页不编辑正文，只在右侧控制标题、排序和菜单显示。
                    </div>
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
            </div>

            <aside class="post-editor-sidebar">
                <div class="post-editor-actions">
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> 保存</button>
                    <a href="/admin/pages" class="btn"><i class="fa-solid fa-arrow-left"></i> 返回</a>
                </div>

                <section class="post-editor-panel">
                    <h3>设置</h3>
                    <div class="form-group">
                        <label>别名（Slug）</label>
                        <input type="text" name="slug" value="{{ $page->slug ?? '' }}" placeholder="留空自动生成" {{ $isSystemPage ? 'disabled' : '' }}>
                    </div>
                    <div class="form-group">
                        <label>排序</label>
                        <input type="number" name="sort" value="{{ $page->sort ?? 0 }}">
                    </div>
                    <label class="admin-inline-check post-option-check">
                        <input type="hidden" name="is_nav" value="0">
                        <input type="checkbox" name="is_nav" value="1" {{ $pageIsNav ? 'checked' : '' }}>
                        加入导航
                    </label>
                </section>
            </aside>
        </div>
    </form>
@endsection
