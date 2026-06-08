@extends('layouts.admin')

@section('content')
    @php
        $attSettings = $attachmentSettings ?? [];
        $cdnEnabled = (string)($attSettings['attachment_cdn_enabled'] ?? '0') === '1';
        $webpEnabled = (string)($attSettings['attachment_image_webp_enabled'] ?? '1') === '1';
        $s3Enabled = (string)($attSettings['attachment_s3_enabled'] ?? '0') === '1';
        $deleteRemote = (string)($attSettings['attachment_s3_delete_remote'] ?? '0') === '1';
        $backupEnabled = (string)($attSettings['attachment_backup_enabled'] ?? '0') === '1';
        $backupS3Enabled = (string)($attSettings['attachment_backup_s3_enabled'] ?? '1') === '1';
        $backupTime = (string)($attSettings['attachment_backup_time'] ?? '00:00');
        $backupRetentionDays = (string)($attSettings['attachment_backup_retention_days'] ?? '15');
        $backupKeepVersions = (string)($attSettings['attachment_backup_keep_versions'] ?? '10');
        $backupLastStatus = trim((string)($attSettings['attachment_backup_last_status'] ?? ''));
        $activeType = $type ?? '';
        $categoryOptions = $categoryOptions ?? [];
        $categoryCounts = $categoryCounts ?? [];
        $allCount = array_sum(array_map('intval', $categoryCounts));
    @endphp
    <link rel="stylesheet" href="/themes/ember/assets/vendor/fancybox/fancybox.css?v={{ \App\Services\ThemeManager::assetVersion('/themes/ember/assets/vendor/fancybox/fancybox.css') }}">

    <div class="upload-area" id="upload-area">
        <p><i class="fa-solid fa-paperclip"></i> 拖拽文件到此处，或 <button type="button" id="upload-btn" class="btn btn-primary">点击上传</button></p>
        <p class="field-hint">图片上传{{ $webpEnabled ? '后会自动转换为 WebP' : '会保留原始格式' }}；音乐、视频、歌词和其他文件保持原格式。</p>
        <input type="file" id="file-input" multiple style="display:none">
    </div>

    <div class="admin-toolbar attachment-toolbar">
        <button type="button" class="btn btn-primary" data-attachment-settings-open><i class="fa-solid fa-sliders"></i> 设置</button>
        <a class="btn {{ $activeType === '' ? 'btn-secondary is-active' : '' }}" href="/admin/attachments">全部 <span>{{ $allCount }}</span></a>
        @foreach($categoryOptions as $key => $label)
            <a class="btn {{ $activeType === $key ? 'btn-secondary is-active' : '' }}" href="/admin/attachments?type={{ $key }}">
                {{ $label }} <span>{{ (int)($categoryCounts[$key] ?? 0) }}</span>
            </a>
        @endforeach
        <span class="attachment-storage-status">
            CDN：{{ $cdnEnabled ? '已启用' : '未启用' }} / S3/R2：{{ $s3Enabled ? '已启用' : '未启用' }}
        </span>
    </div>

    <div class="admin-dialog-backdrop attachment-settings-dialog" data-attachment-settings-dialog hidden>
        <div class="admin-dialog-shell">
            <form method="post" action="/admin/attachments/settings" class="admin-dialog attachment-settings-panel" data-dirty-watch>
                <input type="hidden" name="_csrf" value="{{ $csrf }}">
                <div class="admin-dialog-body">
                    <div class="admin-dialog-layout">
                        <div class="admin-dialog-icon admin-dialog-icon-primary">
                            <i class="fa-solid fa-cloud-arrow-up"></i>
                        </div>
                        <div class="admin-dialog-copy attachment-settings-copy">
                            <h3>附件存储设置</h3>
                            <p>在附件管理页直接配置图片处理、CDN 访问地址，以及 S3 / Cloudflare R2 同步参数。</p>
                        </div>
                    </div>

                    <div class="attachment-settings-grid">
                                <section class="attachment-settings-section">
                                    <div class="attachment-settings-section-head">
                                        <h4>图片处理</h4>
                                        <label class="admin-inline-check attachment-setting-toggle">
                                            <input type="hidden" name="attachment_image_webp_enabled" value="0">
                                            <input type="checkbox" name="attachment_image_webp_enabled" value="1" {{ $webpEnabled ? 'checked' : '' }}>
                                            上传图片自动转换 WebP
                                        </label>
                                    </div>
                                    <p class="field-hint">关闭后保留原图格式，适合需要 GIF 动图、PNG 透明图或原始图片的场景。</p>
                                </section>

                                <section class="attachment-settings-section">
                                    <div class="attachment-settings-section-head">
                                        <h4>CDN 访问</h4>
                                        <label class="admin-inline-check attachment-setting-toggle">
                                            <input type="hidden" name="attachment_cdn_enabled" value="0">
                                            <input type="checkbox" name="attachment_cdn_enabled" value="1" {{ $cdnEnabled ? 'checked' : '' }}>
                                            启用 CDN URL
                                        </label>
                                    </div>
                                    <div class="form-group">
                                        <label>CDN 域名</label>
                                        <input type="url" name="attachment_cdn_url" value="{{ $attSettings['attachment_cdn_url'] ?? '' }}" placeholder="https://cdn.example.com">
                                        <small class="hint">用于把本地 `/uploads/...` 替换成 CDN 域名访问。</small>
                                    </div>
                                </section>

                                <section class="attachment-settings-section">
                                    <div class="attachment-settings-section-head">
                                        <h4>S3 / Cloudflare R2</h4>
                                        <label class="admin-inline-check attachment-setting-toggle">
                                            <input type="hidden" name="attachment_s3_enabled" value="0">
                                            <input type="checkbox" name="attachment_s3_enabled" value="1" {{ $s3Enabled ? 'checked' : '' }}>
                                            启用同步
                                        </label>
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label>Endpoint</label>
                                            <input type="url" name="attachment_s3_endpoint" value="{{ $attSettings['attachment_s3_endpoint'] ?? '' }}" placeholder="https://xxx.r2.cloudflarestorage.com">
                                        </div>
                                        <div class="form-group">
                                            <label>Bucket</label>
                                            <input type="text" name="attachment_s3_bucket" value="{{ $attSettings['attachment_s3_bucket'] ?? '' }}" placeholder="bucket-name">
                                        </div>
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label>Region</label>
                                            <input type="text" name="attachment_s3_region" value="{{ $attSettings['attachment_s3_region'] ?? 'auto' }}" placeholder="auto">
                                        </div>
                                        <div class="form-group">
                                            <label>对象路径前缀</label>
                                            <input type="text" name="attachment_s3_prefix" value="{{ $attSettings['attachment_s3_prefix'] ?? '' }}" placeholder="uploads">
                                        </div>
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label>Access Key</label>
                                            <input type="text" name="attachment_s3_access_key" value="{{ $attSettings['attachment_s3_access_key'] ?? '' }}" autocomplete="off">
                                        </div>
                                        <div class="form-group">
                                            <label>Secret Key</label>
                                            <input type="password" name="attachment_s3_secret_key" value="{{ $attSettings['attachment_s3_secret_key'] ?? '' }}" autocomplete="off">
                                        </div>
                                    </div>
                                    <div class="attachment-s3-tools">
                                        <button type="button" class="btn" data-s3-test><i class="fa-solid fa-plug-circle-check"></i> 测试连接</button>
                                        <button type="button" class="btn" data-s3-command><i class="fa-regular fa-copy"></i> 复制清空命令</button>
                                        <button type="button" class="btn btn-danger" data-s3-clear><i class="fa-solid fa-broom"></i> 清空桶前缀</button>
                                    </div>
                                    <div class="attachment-s3-status" data-s3-status hidden></div>
                                    <label class="admin-inline-check attachment-setting-toggle">
                                        <input type="hidden" name="attachment_s3_delete_remote" value="0">
                                        <input type="checkbox" name="attachment_s3_delete_remote" value="1" {{ $deleteRemote ? 'checked' : '' }}>
                                        删除附件时同步删除远端对象
                                    </label>
                                </section>

                                <section class="attachment-settings-section">
                                    <div class="attachment-settings-section-head">
                                        <h4>数据备份</h4>
                                        <label class="admin-inline-check attachment-setting-toggle">
                                            <input type="hidden" name="attachment_backup_enabled" value="0">
                                            <input type="checkbox" name="attachment_backup_enabled" value="1" {{ $backupEnabled ? 'checked' : '' }}>
                                            启用每日备份
                                        </label>
                                    </div>
                                    <p class="field-hint">每天到达设置时间后生成本地 JSON 数据快照和 SQLite 数据库备份；没有系统 cron 时，会在当天首次访问时补跑。</p>
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label>同步时间</label>
                                            <input type="time" name="attachment_backup_time" value="{{ $backupTime ?: '00:00' }}">
                                        </div>
                                        <div class="form-group">
                                            <label>保留天数</label>
                                            <input type="number" name="attachment_backup_retention_days" min="1" max="365" value="{{ $backupRetentionDays ?: '15' }}">
                                        </div>
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label>至少保留版本数</label>
                                            <input type="number" name="attachment_backup_keep_versions" min="1" max="200" value="{{ $backupKeepVersions ?: '10' }}">
                                        </div>
                                        <div class="form-group attachment-backup-toggle-field">
                                            <label>远端同步</label>
                                            <label class="admin-inline-check attachment-setting-toggle">
                                                <input type="hidden" name="attachment_backup_s3_enabled" value="0">
                                                <input type="checkbox" name="attachment_backup_s3_enabled" value="1" {{ $backupS3Enabled ? 'checked' : '' }}>
                                                同步备份到 S3 / R2
                                            </label>
                                        </div>
                                    </div>
                                    <div class="attachment-s3-tools">
                                        <button type="button" class="btn" data-backup-now><i class="fa-solid fa-rotate"></i> 立即备份同步</button>
                                    </div>
                                    <div class="attachment-s3-status" data-backup-status {{ $backupLastStatus !== '' ? '' : 'hidden' }}>{{ $backupLastStatus }}</div>
                                </section>
                    </div>
                </div>
                <div class="admin-dialog-actions">
                    <button type="submit" class="btn admin-dialog-confirm-primary">保存设置</button>
                    <button type="button" class="btn admin-dialog-cancel" data-attachment-settings-close>取消</button>
                </div>
            </form>
        </div>
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
                @if($a->isImage())
                    <a href="{{ $publicUrl }}" class="attachment-preview-link" data-fancybox="admin-attachments" data-caption="{{ $caption }}">
                        <img src="{{ $publicUrl }}" alt="{{ $caption }}" loading="lazy">
                    </a>
                @elseif($a->isVideo())
                    <a href="{{ $publicUrl }}" class="attachment-preview-link attachment-file-icon attachment-video-preview" data-fancybox="admin-attachments" data-type="html5video" data-caption="{{ $caption }}">
                        <i class="fa-regular fa-circle-play"></i>
                    </a>
                @else
                    <div class="attachment-file-icon attachment-type-{{ $categoryKey }}"><i class="{{ $icon }}"></i></div>
                @endif
                <div class="attachment-info">
                    <div class="attachment-name" title="{{ $caption }}">{{ $caption }}</div>
                    <div class="attachment-meta">
                        <span class="attachment-type-badge attachment-type-{{ $categoryKey }}">{{ $categoryLabel }}</span>
                        <span>{{ \App\Core\Helper::bytesToHuman((int)$a->filesize) }}</span>
                    </div>
                    <div class="attachment-actions">
                        <button type="button" class="link-btn copy-url" data-url="{{ $publicUrl }}">复制URL</button>
                        <button type="button" class="link-btn link-danger delete-att" data-id="{{ $a->id }}">删除</button>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
    {!! $paginator ?? '' !!}

    <script src="/themes/ember/assets/vendor/fancybox/fancybox.umd.js?v={{ \App\Services\ThemeManager::assetVersion('/themes/ember/assets/vendor/fancybox/fancybox.umd.js') }}"></script>
    <script>
    const csrf = '{{ $csrf }}';
    const uploadBtn = document.getElementById('upload-btn');
    const fileInput = document.getElementById('file-input');
    const uploadArea = document.getElementById('upload-area');
    const settingsDialog = document.querySelector('[data-attachment-settings-dialog]');
    const settingsOpen = document.querySelector('[data-attachment-settings-open]');
    const settingsClose = document.querySelector('[data-attachment-settings-close]');
    const settingsForm = document.querySelector('.attachment-settings-panel');
    const s3Status = document.querySelector('[data-s3-status]');
    const backupStatus = document.querySelector('[data-backup-status]');

    function openAttachmentSettings() {
        if (!settingsDialog) return;
        settingsDialog.hidden = false;
        document.body.classList.add('admin-dialog-open');
        const first = settingsDialog.querySelector('input, button, select, textarea');
        if (first) setTimeout(() => first.focus(), 40);
    }

    function closeAttachmentSettings() {
        if (!settingsDialog) return;
        settingsDialog.hidden = true;
        document.body.classList.remove('admin-dialog-open');
    }

    settingsOpen && settingsOpen.addEventListener('click', openAttachmentSettings);
    settingsClose && settingsClose.addEventListener('click', closeAttachmentSettings);
    settingsDialog && settingsDialog.addEventListener('click', e => {
        if (e.target === settingsDialog || e.target.classList.contains('admin-dialog-shell')) closeAttachmentSettings();
    });
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && settingsDialog && !settingsDialog.hidden) closeAttachmentSettings();
    });
    if (window.Fancybox && typeof window.Fancybox.bind === 'function') {
        window.Fancybox.bind('[data-fancybox="admin-attachments"]', {
            dragToClose: true,
            animated: true,
        });
    }

    function s3FormData() {
        const fd = new FormData(settingsForm);
        fd.set('_csrf', csrf);
        return fd;
    }

    function setS3Status(message, type) {
        if (!s3Status) return;
        s3Status.hidden = false;
        s3Status.className = 'attachment-s3-status is-' + (type || 'info');
        s3Status.textContent = message;
    }

    function setBackupStatus(message, type) {
        if (!backupStatus) return;
        backupStatus.hidden = false;
        backupStatus.className = 'attachment-s3-status is-' + (type || 'info');
        backupStatus.textContent = message;
    }

    function postS3Action(url) {
        return fetch(url, {
            method: 'POST',
            body: s3FormData(),
            credentials: 'same-origin'
        }).then(r => r.json());
    }

    document.querySelector('[data-s3-test]')?.addEventListener('click', function () {
        const btn = this;
        btn.disabled = true;
        setS3Status('正在测试连接...', 'info');
        postS3Action('/admin/attachments/s3-test')
            .then(d => {
                if (!d || d.code !== 0) throw new Error((d && d.msg) || '连接失败');
                setS3Status(d.msg || '连接成功', 'success');
            })
            .catch(err => setS3Status(err.message || '连接失败', 'error'))
            .finally(() => { btn.disabled = false; });
    });

    document.querySelector('[data-s3-command]')?.addEventListener('click', function () {
        const btn = this;
        btn.disabled = true;
        postS3Action('/admin/attachments/s3-command')
            .then(d => {
                if (!d || d.code !== 0) throw new Error((d && d.msg) || '生成失败');
                return navigator.clipboard.writeText(d.data.command).then(() => d);
            })
            .then(d => setS3Status(d.msg || '清空命令已复制', 'success'))
            .catch(err => setS3Status(err.message || '生成失败', 'error'))
            .finally(() => { btn.disabled = false; });
    });

    document.querySelector('[data-s3-clear]')?.addEventListener('click', function () {
        if (!window.adminConfirm) return;
        window.adminConfirm({
            title: '清空 S3 / R2 前缀',
            message: '确定删除当前 Bucket 和前缀下的所有对象？此操作不会删除本地附件记录，但远端对象不可恢复。',
            confirmText: '确认清空',
            tone: 'danger'
        }).then(ok => {
            if (!ok) return;
            const btn = this;
            btn.disabled = true;
            setS3Status('正在清空远端对象...', 'info');
            postS3Action('/admin/attachments/s3-clear')
                .then(d => {
                    if (!d || d.code !== 0) throw new Error((d && d.msg) || '清空失败');
                    setS3Status(d.msg || '清空完成', 'success');
                })
                .catch(err => setS3Status(err.message || '清空失败', 'error'))
                .finally(() => { btn.disabled = false; });
        });
    });

    document.querySelector('[data-backup-now]')?.addEventListener('click', function () {
        const btn = this;
        btn.disabled = true;
        setBackupStatus('正在生成本地备份并同步...', 'info');
        postS3Action('/admin/attachments/backup-now')
            .then(d => {
                if (!d || d.code !== 0) throw new Error((d && d.msg) || '备份失败');
                setBackupStatus(d.msg || '备份完成', 'success');
            })
            .catch(err => setBackupStatus(err.message || '备份失败', 'error'))
            .finally(() => { btn.disabled = false; });
    });

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
