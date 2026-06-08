@extends('layouts.admin')

@section('content')
    @php
        $postPublishedAt = $post && !empty($post->published_at) ? (string)$post->published_at : date('Y-m-d H:i:s');
        $postPublishedInput = date('Y-m-d\TH:i', strtotime($postPublishedAt) ?: time());
        $postAllowComments = $post ? (int)($post->allow_comments ?? 1) === 1 : true;
        $postAllowRss = $post ? (int)($post->allow_rss ?? 1) === 1 : true;
        $postIsTop = $post ? (int)($post->is_top ?? 0) === 1 : false;
        $postIsPrivate = $post ? (int)($post->is_private ?? 0) === 1 : false;
        $postStatus = (string)($post->status ?? 'published');
        $selectedCategoryId = (int)($post->category_id ?? 0);
        if ($selectedCategoryId <= 0 && !empty($categories)) {
            $selectedCategoryId = (int)$categories[0]->id;
        }
    @endphp
    <form method="post" action="{{ $post ? '/admin/posts/'.$post->id.'/edit' : '/admin/posts/create' }}" class="admin-form post-editor-form" data-dirty-watch>
        <input type="hidden" name="_csrf" value="{{ $csrf }}">
        <div class="post-editor-title-row">
            <input type="text" name="title" id="post-title" value="{{ $post->title ?? '' }}" placeholder="在此输入标题..." required>
        </div>

        <div class="post-editor-layout">
            <div class="post-editor-main">
                @php
                    $editorId = 'editor-md';
                    $editorName = 'markdown_content';
                    $editorLabel = '内容（Markdown）';
                    $editorValue = $post ? \App\Services\PostContentStorage::bodyWithoutTitleHeading($post->markdown(), (string)$post->title) : '';
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
            </div>

            <aside class="post-editor-sidebar">
                <div class="post-editor-actions">
                    <button type="submit" class="btn btn-primary" data-post-publish-button><i class="fa-solid fa-paper-plane"></i> 发布</button>
                    <button type="submit" class="btn"><i class="fa-solid fa-floppy-disk"></i> 保存</button>
                    <a href="/admin/posts" class="btn"><i class="fa-solid fa-arrow-left"></i> 返回</a>
                </div>

                <section class="post-editor-panel">
                    <h3>设置</h3>
                    <div class="form-group">
                        <label>特色图 URL</label>
                        <div class="cover-upload admin-upload-field">
                            <input type="text" name="cover" id="cover-url" value="{{ $post->cover ?? '' }}" placeholder="留空自动回退为正文首图">
                            <button type="button" class="admin-upload-field-btn" id="cover-upload-btn" aria-label="上传特色图" title="上传特色图">
                                <i class="fa-solid fa-arrow-up-from-bracket"></i>
                            </button>
                            <input type="file" id="cover-file" accept="image/*" hidden>
                        </div>
                        <div class="cover-preview {{ empty($post->cover) ? 'hidden' : '' }}" id="cover-preview">
                            <img src="{{ $post->cover ?? '' }}" alt="特色图预览">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>别名（Slug）</label>
                        <input type="text" name="slug" value="{{ $post->slug ?? '' }}" placeholder="留空自动生成">
                    </div>
                    <div class="form-group">
                        <label>分类</label>
                        <select name="category_id">
                            @foreach($categories as $c)
                                <option value="{{ $c->id }}" {{ $selectedCategoryId === (int)$c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>发布时间</label>
                        <input type="datetime-local" name="published_at" value="{{ $postPublishedInput }}">
                    </div>
                    <div class="form-group">
                        <label>状态</label>
                        <select name="status">
                            <option value="published" {{ $postStatus === 'published' ? 'selected' : '' }}>已发布</option>
                            <option value="draft" {{ $postStatus === 'draft' ? 'selected' : '' }}>草稿</option>
                        </select>
                    </div>
                </section>

                <section class="post-editor-panel">
                    <h3>高级</h3>
                    <div class="form-group">
                        <label>摘要</label>
                        <textarea name="summary" id="post-summary" rows="4" placeholder="留空自动截取">{{ $post->summary ?? '' }}</textarea>
                    </div>
                    <label class="admin-inline-check post-option-check">
                        <input type="hidden" name="allow_comments" value="0">
                        <input type="checkbox" name="allow_comments" value="1" {{ $postAllowComments ? 'checked' : '' }}>
                        允许评论
                    </label>
                    <label class="admin-inline-check post-option-check">
                        <input type="hidden" name="allow_rss" value="0">
                        <input type="checkbox" name="allow_rss" value="1" {{ $postAllowRss ? 'checked' : '' }}>
                        允许本文出现在 RSS 聚合
                    </label>
                    <label class="admin-inline-check post-option-check">
                        <input type="hidden" name="is_top" value="0">
                        <input type="checkbox" name="is_top" value="1" {{ $postIsTop ? 'checked' : '' }}>
                        置顶文章
                    </label>
                    <label class="admin-inline-check post-option-check">
                        <input type="hidden" name="is_private" value="0">
                        <input type="checkbox" name="is_private" value="1" {{ $postIsPrivate ? 'checked' : '' }}>
                        私密文章
                    </label>
                </section>
            </aside>
        </div>
    </form>
@endsection
