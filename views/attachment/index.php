@extends('layouts.admin')

@section('content')
    <div class="upload-area" id="upload-area">
        <p><i class="fa-solid fa-paperclip"></i> 拖拽文件到此处，或 <button type="button" id="upload-btn" class="btn btn-primary">点击上传</button></p>
        <p class="field-hint">图片上传后会自动转换为 WebP；非图片文件保持原格式。</p>
        <input type="file" id="file-input" multiple style="display:none">
    </div>

    <div class="admin-toolbar">
        <a class="btn" href="/admin/attachments?type=image">图片</a>
        <a class="btn" href="/admin/attachments?type=file">文件</a>
        <a class="btn" href="/admin/attachments">全部</a>
        <span>共 {{ $total }} 个</span>
    </div>

    <div class="attachment-grid">
        @foreach($items as $a)
            <div class="attachment-card" data-id="{{ $a->id }}">
                @if($a->isImage())
                    <img src="{{ $a->fileurl }}" alt="{{ $a->original_name }}" loading="lazy">
                @else
                    <div class="attachment-file-icon"><i class="fa-regular fa-file-lines"></i></div>
                @endif
                <div class="attachment-info">
                    <div class="attachment-name" title="{{ $a->original_name }}">{{ $a->original_name }}</div>
                    <div class="attachment-meta">{{ \App\Core\Helper::bytesToHuman((int)$a->filesize) }}</div>
                    <div class="attachment-actions">
                        <button type="button" class="link-btn copy-url" data-url="{{ $a->fileurl }}">复制URL</button>
                        <button type="button" class="link-btn link-danger delete-att" data-id="{{ $a->id }}">删除</button>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
    {!! $paginator ?? '' !!}

    <script>
    const csrf = '{{ $csrf }}';
    const uploadBtn = document.getElementById('upload-btn');
    const fileInput = document.getElementById('file-input');
    const uploadArea = document.getElementById('upload-area');

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
        const fd = new FormData();
        fd.append('_csrf', csrf);
        for (let i = 0; i < files.length; i++) fd.append('file', files[i]);
        fetch('/admin/attachments/upload', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => {
                if (d.code === 0) {
                    window.adminToast && window.adminToast('上传成功', 'success');
                    setTimeout(() => location.reload(), 500);
                } else {
                    window.adminToast && window.adminToast('上传失败: ' + d.msg, 'error');
                }
            });
    }

    document.querySelectorAll('.copy-url').forEach(btn => {
        btn.addEventListener('click', function() {
            const url = this.dataset.url;
            navigator.clipboard.writeText(window.location.origin + url).then(() => {
                this.textContent = '已复制';
                setTimeout(() => this.textContent = '复制URL', 2000);
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
