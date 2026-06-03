@extends('layouts.admin')

@section('content')
    <form method="post" action="{{ $post ? '/admin/posts/'.$post->id.'/edit' : '/admin/posts/create' }}" class="admin-form">
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
        <div class="form-row">
            <div class="form-group">
                <label><input type="checkbox" name="is_top" value="1" {{ ($post->is_top ?? 0) ? 'checked' : '' }}> 置顶</label>
                <label><input type="checkbox" name="is_recommend" value="1" {{ ($post->is_recommend ?? 0) ? 'checked' : '' }}> 推荐</label>
            </div>
        </div>
        <div class="form-group markdown-editor"
             data-preview-url="/admin/posts/preview"
             data-upload-url="/admin/posts/upload-image"
             data-summary-url="/admin/posts/summary"
             data-csrf="{{ $csrf }}">
            <div class="editor-head">
                <label>内容（Markdown）</label>
                <div class="editor-stats">
                    <span id="md-words">0 字</span>
                    <span id="md-lines">0 行</span>
                </div>
            </div>
            <div class="editor-toolbar" aria-label="Markdown 工具栏">
                <button type="button" data-md="heading" title="标题"><i class="fa-solid fa-heading"></i></button>
                <button type="button" data-md="bold" title="加粗"><i class="fa-solid fa-bold"></i></button>
                <button type="button" data-md="italic" title="斜体"><i class="fa-solid fa-italic"></i></button>
                <button type="button" data-md="quote" title="引用"><i class="fa-solid fa-quote-left"></i></button>
                <button type="button" data-md="code" title="代码块"><i class="fa-solid fa-code"></i></button>
                <button type="button" data-md="link" title="链接"><i class="fa-solid fa-link"></i></button>
                <button type="button" data-md="image-upload" title="上传图片"><i class="fa-regular fa-image"></i></button>
                <button type="button" data-md="ul" title="无序列表"><i class="fa-solid fa-list-ul"></i></button>
                <button type="button" data-md="ol" title="有序列表"><i class="fa-solid fa-list-ol"></i></button>
                <button type="button" data-md="table" title="表格"><i class="fa-solid fa-table"></i></button>
                <button type="button" data-md="summary" title="AI 摘要"><i class="fa-solid fa-wand-magic-sparkles"></i></button>
                <label class="editor-file" title="导入本地 .md">
                    <i class="fa-solid fa-file-arrow-up"></i>
                    <input type="file" id="md-file-picker" accept=".md,text/markdown,text/plain">
                </label>
                <input type="file" id="md-image-picker" accept="image/*" hidden>
            </div>
            <div class="editor-pane">
                <textarea name="markdown_content" rows="24" id="editor-md" placeholder="# 标题\n\n内容..." required>{{ $post ? $post->markdown() : '' }}</textarea>
                <div class="editor-preview" id="md-preview">
                    <div class="empty">预览会显示在这里</div>
                </div>
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> 保存</button>
            <a href="/admin/posts" class="btn"><i class="fa-solid fa-xmark"></i> 取消</a>
        </div>
    </form>
    <script src="/assets/js/markdown-editor.js?v=20260603b"></script>
@endsection
