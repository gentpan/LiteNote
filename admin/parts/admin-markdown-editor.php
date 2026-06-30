@php
    $editorId = $editorId ?? 'editor-md';
    $editorName = $editorName ?? 'markdown_content';
    $editorLabel = $editorLabel ?? '内容（Markdown）';
    $editorValue = $editorValue ?? '';
    $editorRows = $editorRows ?? 24;
    $editorPlaceholder = $editorPlaceholder ?? "内容...";
    $editorPreviewUrl = $editorPreviewUrl ?? '/admin/posts/preview';
    $editorUploadUrl = $editorUploadUrl ?? '/admin/posts/upload-image';
    $editorUploadPurpose = $editorUploadPurpose ?? 'editor';
    $editorSummaryUrl = $editorSummaryUrl ?? '';
    $editorCsrf = $editorCsrf ?? ($csrf ?? '');
    $editorRequired = $editorRequired ?? true;
    $editorShowSummary = $editorShowSummary ?? ($editorSummaryUrl !== '');
    $editorTitleInput = $editorTitleInput ?? '';
    $editorSummaryInput = $editorSummaryInput ?? '';
    $editorWordsId = $editorWordsId ?? $editorId . '-words';
    $editorLinesId = $editorLinesId ?? $editorId . '-lines';
    $editorPreviewId = $editorPreviewId ?? $editorId . '-preview';
@endphp

<div class="form-group markdown-editor"
     data-preview-url="{{ $editorPreviewUrl }}"
     data-upload-url="{{ $editorUploadUrl }}"
     data-upload-purpose="{{ $editorUploadPurpose }}"
     data-summary-url="{{ $editorSummaryUrl }}"
     data-title-input="{{ $editorTitleInput }}"
     data-summary-input="{{ $editorSummaryInput }}"
     data-csrf="{{ $editorCsrf }}">
    <div class="editor-head">
        <label>{{ $editorLabel }}</label>
        <div class="editor-stats">
            <span id="{{ $editorWordsId }}" data-editor-words>0 字</span>
            <span id="{{ $editorLinesId }}" data-editor-lines>0 行</span>
        </div>
    </div>
    <div class="editor-toolbar" aria-label="Markdown 工具栏">
        <span class="editor-toolbar-label"><i class="fa-regular fa-eye"></i> Markdown</span>
        <div class="editor-heading-menu" data-heading-menu>
            <button type="button" data-heading-toggle title="标题" aria-haspopup="true" aria-expanded="false">
                <i class="fa-solid fa-heading"></i>
            </button>
            <div class="editor-heading-dropdown" data-heading-dropdown hidden>
                @for($level = 1; $level <= 5; $level++)
                    <button type="button" data-md-heading="{{ $level }}">H{{ $level }}</button>
                @endfor
            </div>
        </div>
        <button type="button" data-md="bold" title="加粗"><i class="fa-solid fa-bold"></i></button>
        <button type="button" data-md="italic" title="斜体"><i class="fa-solid fa-italic"></i></button>
        <button type="button" data-md="quote" title="引用"><i class="fa-solid fa-quote-left"></i></button>
        <button type="button" data-md="code" title="代码块"><i class="fa-solid fa-code"></i></button>
        <button type="button" data-md="link" title="链接"><i class="fa-solid fa-link"></i></button>
        <button type="button" data-md="image" title="插入图片"><i class="fa-regular fa-image"></i></button>
        <button type="button" data-md="image-upload" title="上传图片"><i class="fa-solid fa-arrow-up-from-bracket"></i></button>
        <button type="button" data-md="ul" title="无序列表"><i class="fa-solid fa-list-ul"></i></button>
        <button type="button" data-md="ol" title="有序列表"><i class="fa-solid fa-list-ol"></i></button>
        <button type="button" data-md="table" title="表格"><i class="fa-solid fa-table"></i></button>
        @if($editorShowSummary)
            <button type="button" data-md="summary" title="AI 摘要"><i class="fa-solid fa-wand-magic-sparkles"></i></button>
        @endif
        <label class="editor-file editor-md-import" title="导入本地 .md">
            <i class="fa-solid fa-file-import"></i>
            <span>导入 .md</span>
            <input type="file" data-md-file-picker accept=".md,text/markdown,text/plain">
        </label>
        <input type="file" data-md-image-picker accept="image/*" hidden>
        <span class="editor-toolbar-spacer" aria-hidden="true"></span>
        <span class="editor-toolbar-meta">
            <span data-editor-words-toolbar>0 字</span>
            <span data-editor-paragraphs>0 段</span>
            <span data-editor-reading>1 分钟</span>
            <span class="editor-toolbar-separator" aria-hidden="true"></span>
            <a href="https://makeitdown.io" target="_blank" rel="noopener noreferrer">MakeItDown <i class="fa-solid fa-arrow-up-right-from-square"></i></a>
            <span class="editor-toolbar-separator" aria-hidden="true"></span>
            <span>预览</span>
            <span data-editor-sync-status>已同步</span>
        </span>
    </div>
    <div class="editor-pane">
        <textarea name="{{ $editorName }}"
                  rows="{{ $editorRows }}"
                  id="{{ $editorId }}"
                  data-editor-textarea
                  placeholder="{{ $editorPlaceholder }}">{{ $editorValue }}</textarea>
        <div class="editor-preview" id="{{ $editorPreviewId }}" data-editor-preview>
            <div class="empty">预览会显示在这里</div>
        </div>
    </div>
</div>
