@extends('layouts.admin')

@section('content')
    <form method="post" action="/admin/posts/import" class="admin-form post-import-form" enctype="multipart/form-data" data-dirty-watch>
        <input type="hidden" name="_csrf" value="{{ $csrf }}">

        <div class="import-panel">
            <div>
                <h3><i class="fa-brands fa-markdown"></i> 导入 Markdown</h3>
                <p>上传 `.md` 文件，或选择服务器 `runtime/storage/imports/` 里的 Markdown。导入后会生成正式文章记录，正文保存到 `runtime/storage/posts/{slug}.md`。</p>
            </div>
        </div>

        <div class="form-row post-import-source-row">
            <div class="form-group">
                <label>上传 Markdown 文件</label>
                <input type="file" name="md_file" accept=".md,text/markdown,text/plain">
            </div>
            <div class="form-group">
                <label>或选择服务器文件</label>
                <select name="import_file">
                    <option value="">不选择</option>
                    @foreach($files as $file)
                        <option value="{{ $file['name'] }}">{{ $file['name'] }} · {{ round(($file['size'] ?? 0) / 1024, 1) }} KB</option>
                    @endforeach
                </select>
                <p class="field-hint">服务器导入目录：runtime/storage/imports/</p>
            </div>
        </div>

        <div class="form-row post-import-title-row">
            <div class="form-group flex-2">
                <label>标题</label>
                <input type="text" name="title" placeholder="留空则读取 front matter、一级标题或文件名">
            </div>
            <div class="form-group">
                <label>slug</label>
                <input type="text" name="slug" placeholder="留空自动生成">
            </div>
        </div>

        <div class="form-row post-import-meta-row">
            <div class="form-group">
                <label>分类</label>
                <select name="category_id">
                    <option value="0">默认分类</option>
                    @foreach($categories as $c)
                        <option value="{{ $c->id }}">{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>状态</label>
                <select name="status">
                    <option value="draft">草稿</option>
                    <option value="published">已发布</option>
                </select>
            </div>
        </div>

        <div class="form-row post-import-content-row">
            <div class="form-group flex-2">
                <label>摘要</label>
                <textarea name="summary" rows="2"></textarea>
            </div>
            <div class="form-group">
                <label>特色图</label>
                <div class="cover-upload admin-upload-field">
                    <input type="text" name="cover" id="cover-url" placeholder="/uploads/...">
                    <button type="button" class="admin-upload-field-btn" id="cover-upload-btn" aria-label="上传特色图" title="上传特色图">
                        <i class="fa-solid fa-arrow-up-from-bracket"></i>
                    </button>
                    <input type="file" id="cover-file" accept="image/*" hidden>
                </div>
                <div class="cover-preview hidden" id="cover-preview">
                    <img src="" alt="特色图预览">
                </div>
            </div>
        </div>

        <div class="form-row compact-flags post-import-flags">
            <label><input type="checkbox" name="delete_source" value="1"> 导入后删除服务器源文件</label>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-file-import"></i> 导入为文章</button>
            <a href="/admin/posts" class="btn"><i class="fa-solid fa-xmark"></i> 取消</a>
        </div>
    </form>
    <div class="markdown-editor hidden"
         data-upload-url="/admin/posts/upload-image"
         data-csrf="{{ $csrf }}"></div>
@endsection
