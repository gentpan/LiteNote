@php
    $editorId = $editorId ?? 'editor-md';
    $editorName = $editorName ?? 'markdown_content';
    $editorLabel = $editorLabel ?? '内容（Markdown）';
    $editorValue = $editorValue ?? '';
    $editorRows = $editorRows ?? 24;
    $editorPlaceholder = $editorPlaceholder ?? "# 标题\n\n内容...";
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
        @if($editorShowSummary)
            <button type="button" data-md="summary" title="AI 摘要"><i class="fa-solid fa-wand-magic-sparkles"></i></button>
        @endif
        <label class="editor-file" title="导入本地 .md">
            <i class="fa-solid fa-file-arrow-up"></i>
            <input type="file" data-md-file-picker accept=".md,text/markdown,text/plain">
        </label>
        <input type="file" data-md-image-picker accept="image/*" hidden>
    </div>
    <div class="editor-pane">
        <textarea name="{{ $editorName }}"
                  rows="{{ $editorRows }}"
                  id="{{ $editorId }}"
                  data-editor-textarea
                  placeholder="{{ $editorPlaceholder }}"<?= $editorRequired ? ' required' : '' ?>>{{ $editorValue }}</textarea>
        <div class="editor-preview" id="{{ $editorPreviewId }}" data-editor-preview>
            <div class="empty">预览会显示在这里</div>
        </div>
    </div>
</div>
