@extends('layouts.admin')

@section('content')
    @php
        $attSettings = $attachmentSettings ?? [];
        $webpEnabled = (string)($attSettings['attachment_image_webp_enabled'] ?? '1') === '1';
        $activeType = $type ?? '';
        $categoryOptions = $categoryOptions ?? [];
        $categoryCounts = $categoryCounts ?? [];
        $allCount = array_sum(array_map('intval', $categoryCounts));
    @endphp
    <div class="upload-area" id="upload-area">
        <button type="button" id="upload-btn" class="btn btn-primary"><i class="fa-solid fa-arrow-up-from-bracket"></i> 点击上传</button>
        <p class="field-hint"><i class="fa-solid fa-paperclip"></i> 拖拽文件到此处，图片上传{{ $webpEnabled ? '后会自动转换为 WebP' : '会保留原始格式' }}；音乐、视频、歌词和其他文件保持原格式。</p>
        <input type="file" id="file-input" multiple style="display:none">
    </div>

    <div class="admin-toolbar attachment-toolbar">
        <a class="btn btn-primary" href="/admin/settings/attachments" title="附件存储设置"><i class="fa-solid fa-gear"></i> 设置</a>
        <a class="btn {{ $activeType === '' ? 'btn-secondary is-active' : '' }}" href="/admin/attachments">全部 <span>{{ $allCount }}</span></a>
        @foreach($categoryOptions as $key => $label)
            <a class="btn {{ $activeType === $key ? 'btn-secondary is-active' : '' }}" href="/admin/attachments?type={{ $key }}">
                {{ $label }} <span>{{ (int)($categoryCounts[$key] ?? 0) }}</span>
            </a>
        @endforeach
    </div>

    <div class="attachment-grid">
        @foreach($items as $a)
            @php
                $categoryKey = $a->categoryKey();
                $categoryLabel = $a->categoryLabel();
                $caption = trim((string)$a->original_name) ?: (string)$a->filename;
                $publicUrl = preg_match('#^https?://#i', (string)$a->fileurl) ? (string)$a->fileurl : \App\Core\Helper::url((string)$a->fileurl);
                $lightboxable = $a->isImage() || $a->isVideo();
                $icon = match ($categoryKey) {
                    'video' => 'fa-regular fa-circle-play',
                    'audio' => 'fa-solid fa-music',
                    'lyrics' => 'fa-regular fa-file-lines',
                    'x' => 'fa-brands fa-x-twitter',
                    default => 'fa-regular fa-file-lines',
                };
            @endphp
            <div class="attachment-card" data-id="{{ $a->id }}">
                <div class="attachment-card-head">
                    <span class="attachment-type-badge attachment-type-{{ $categoryKey }}">{{ $categoryLabel }}</span>
                    <span class="attachment-name" title="{{ $caption }}">{{ $caption }}</span>
                </div>
                @if($a->isImage())
                    <a href="{{ $publicUrl }}" class="attachment-preview-link">
                        <img src="{{ $publicUrl }}" alt="{{ $caption }}" loading="lazy" data-litezoom-caption="{{ $caption }}">
                    </a>
                @elseif($a->isVideo())
                    <a href="{{ $publicUrl }}" class="attachment-preview-link attachment-file-icon attachment-video-preview" target="_blank" rel="noopener" title="{{ $caption }}">
                        <i class="fa-regular fa-circle-play"></i>
                    </a>
                @else
                    <div class="attachment-file-icon attachment-type-{{ $categoryKey }}"><i class="{{ $icon }}"></i></div>
                @endif
                <div class="attachment-info">
                    <div class="attachment-meta">
                        <span>{{ \App\Core\Helper::bytesToHuman((int)$a->filesize) }}</span>
                        <div class="attachment-actions">
                            <button type="button" class="admin-action-btn admin-action-copy copy-url" data-url="{{ $publicUrl }}" title="复制 URL" aria-label="复制 URL">
                                <i class="fa-regular fa-copy"></i>
                            </button>
                            <button type="button" class="admin-action-btn admin-action-delete delete-att" data-id="{{ $a->id }}" title="删除" aria-label="删除">
                                <i class="fa-regular fa-trash-can"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
    {!! $paginator ?? '' !!}

    @php $__liteZoomJs = '/themes/ember/assets/litezoom.min.js'; @endphp
    <script src="{{ $__liteZoomJs }}?v={{ @filemtime(BASE_PATH . $__liteZoomJs) ?: time() }}"></script>
    <script>
    const csrf = '{{ $csrf }}';
    const uploadBtn = document.getElementById('upload-btn');
    const fileInput = document.getElementById('file-input');
    const uploadArea = document.getElementById('upload-area');

    if (window.LiteZoom && typeof window.LiteZoom.bind === 'function') {
        window.LiteZoom.bind('.attachment-grid .attachment-preview-link img', {
            mode: 'full',
            group: function() { return 'admin-attachments'; },
            caption: function(img) {
                return (img.getAttribute('data-litezoom-caption') || img.getAttribute('alt') || '').trim();
            }
        });
    }

    uploadBtn.addEventListener('click', () => fileInput.click());
    fileInput.addEventListener('change', e => uploadFiles(e.target.files));

    ['dragenter', 'dragover'].forEach(ev => {
        uploadArea.addEventListener(ev, e => { e.preventDefault(); uploadArea.classList.add('drag'); });
    });
    ['dragleave', 'drop'].forEach(ev => {
        uploadArea.addEventListener(ev, e => { e.preventDefault(); uploadArea.classList.remove('drag'); });
    });
    uploadArea.addEventListener('drop', e => uploadFiles(e.dataTransfer.files));

    function uploadFiles(files) {
        const queue = Array.prototype.slice.call(files || []);
        if (!queue.length) return;
        const uploader = window.adminUploadFile;
        const jobs = queue.map(file => {
            if (!uploader) {
                const fd = new FormData();
                fd.append('_csrf', csrf);
                fd.append('file', file);
                return fetch('/admin/attachments/upload', { method: 'POST', body: fd })
                    .then(r => r.json())
                    .then(d => {
                        if (!d || d.code !== 0) throw new Error((d && d.msg) || '上传失败');
                        return d;
                    });
            }
            return uploader({
                url: '/admin/attachments/upload',
                fields: { _csrf: csrf },
                fileField: 'file',
                file: file,
                successMessage: '附件已上传'
            });
        });
        Promise.all(jobs)
            .then(() => setTimeout(() => location.reload(), 500))
            .catch(err => window.adminToast && window.adminToast(err.message || '上传失败', 'error'));
    }

    document.querySelectorAll('.copy-url').forEach(btn => {
        btn.addEventListener('click', function() {
            const url = this.dataset.url;
            const fullUrl = /^https?:\/\//i.test(url) ? url : window.location.origin + url;
            navigator.clipboard.writeText(fullUrl).then(() => {
                const icon = this.querySelector('i');
                if (icon) icon.className = 'fa-solid fa-check';
                setTimeout(() => {
                    if (icon) icon.className = 'fa-regular fa-copy';
                }, 2000);
            });
        });
    });
    document.querySelectorAll('.delete-att').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            if (!window.adminConfirm) return;
            window.adminConfirm({
                title: '删除附件',
                message: '确定删除这个附件？文件记录和本地文件都会被移除，此操作不可撤销。',
                confirmText: '确认删除',
                tone: 'danger'
            }).then(ok => {
                if (!ok) return;
                const fd = new FormData();
                fd.append('_csrf', csrf);
                fd.append('id', id);
                fetch('/admin/attachments/delete', { method: 'POST', body: fd })
                    .then(r => r.json())
                    .then(d => {
                        if (d.code === 0) location.reload();
                        else window.adminToast && window.adminToast('删除失败', 'error');
                    });
            });
        });
    });
    </script>
@endsection
