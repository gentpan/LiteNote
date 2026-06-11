@extends('layouts.admin')

@section('content')
    @php
        $attSettings = $attachmentSettings ?? [];
        $cdnEnabled = (string)($attSettings['attachment_cdn_enabled'] ?? '0') === '1';
        $webpEnabled = (string)($attSettings['attachment_image_webp_enabled'] ?? '1') === '1';
        $s3Enabled = (string)($attSettings['attachment_s3_enabled'] ?? '0') === '1';
        $backupEnabled = (string)($attSettings['attachment_backup_enabled'] ?? '0') === '1';
        $backupS3Enabled = (string)($attSettings['attachment_backup_s3_enabled'] ?? '1') === '1';
        $backupTime = (string)($attSettings['attachment_backup_time'] ?? '00:00');
        $backupRetentionDays = (string)($attSettings['attachment_backup_retention_days'] ?? '15');
        $backupKeepVersions = (string)($attSettings['attachment_backup_keep_versions'] ?? '10');
        $backupLastStatus = trim((string)($attSettings['attachment_backup_last_status'] ?? ''));
    @endphp

    <div class="settings-page-shell">
        @include('partials.admin-settings-tabs')

        <form method="post" action="/admin/attachments/settings" class="admin-form attachment-settings-panel" data-dirty-watch>
            <input type="hidden" name="_csrf" value="{{ $csrf }}">
            <input type="hidden" name="redirect" value="/admin/settings/attachments">

            <h3 class="settings-group-title"><i class="fa-solid fa-paperclip"></i> 附件存储设置</h3>
            <p class="field-hint attachment-settings-lead">配置图片处理、CDN 访问地址、S3 / Cloudflare R2 同步参数和数据备份。</p>

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
                        <button type="button" class="btn btn-danger" data-s3-clear><i class="fa-solid fa-broom"></i> 清空桶</button>
                    </div>
                    <div class="attachment-s3-status" data-s3-status hidden></div>
                </section>

                <section class="attachment-settings-section">
                    <div class="attachment-settings-section-head">
                        <h4>数据备份</h4>
                        <div class="attachment-backup-head-toggles">
                            <label class="admin-inline-check attachment-setting-toggle attachment-backup-s3-toggle">
                                <input type="hidden" name="attachment_backup_s3_enabled" value="0">
                                <input type="checkbox" name="attachment_backup_s3_enabled" value="1" {{ $backupS3Enabled ? 'checked' : '' }}>
                                同步备份云端
                            </label>
                            <label class="admin-inline-check attachment-setting-toggle">
                                <input type="hidden" name="attachment_backup_enabled" value="0">
                                <input type="checkbox" name="attachment_backup_enabled" value="1" {{ $backupEnabled ? 'checked' : '' }}>
                                每日备份
                            </label>
                        </div>
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
                    </div>
                    <div class="attachment-s3-tools">
                        <button type="button" class="btn" data-backup-now><i class="fa-solid fa-rotate"></i> 立即备份同步</button>
                    </div>
                    <div class="attachment-s3-status" data-backup-status {{ $backupLastStatus !== '' ? '' : 'hidden' }}>{{ $backupLastStatus }}</div>
                </section>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">保存设置</button>
            </div>
        </form>
    </div>

    <script>
    (function () {
        var csrf = '{{ $csrf }}';
        var settingsForm = document.querySelector('.attachment-settings-panel');
        var s3Status = document.querySelector('[data-s3-status]');
        var backupStatus = document.querySelector('[data-backup-status]');
        if (!settingsForm) return;

        function s3FormData() {
            var fd = new FormData(settingsForm);
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
            }).then(function (r) { return r.json(); });
        }

        document.querySelector('[data-s3-test]')?.addEventListener('click', function () {
            var btn = this;
            btn.disabled = true;
            setS3Status('正在测试连接...', 'info');
            postS3Action('/admin/attachments/s3-test')
                .then(function (d) {
                    if (!d || d.code !== 0) throw new Error((d && d.msg) || '连接失败');
                    setS3Status(d.msg || '连接成功', 'success');
                })
                .catch(function (err) { setS3Status(err.message || '连接失败', 'error'); })
                .finally(function () { btn.disabled = false; });
        });

        document.querySelector('[data-s3-clear]')?.addEventListener('click', function () {
            var btn = this;
            if (!window.adminConfirm) return;
            window.adminConfirm({
                title: '清空 S3 / R2 前缀',
                message: '确定删除当前 Bucket 和前缀下的所有对象？此操作不会删除本地附件记录，但远端对象不可恢复。',
                confirmText: '确认清空',
                tone: 'danger'
            }).then(function (ok) {
                if (!ok) return;
                btn.disabled = true;
                setS3Status('正在清空远端对象...', 'info');
                postS3Action('/admin/attachments/s3-clear')
                    .then(function (d) {
                        if (!d || d.code !== 0) throw new Error((d && d.msg) || '清空失败');
                        setS3Status(d.msg || '清空完成', 'success');
                    })
                    .catch(function (err) { setS3Status(err.message || '清空失败', 'error'); })
                    .finally(function () { btn.disabled = false; });
            });
        });

        document.querySelector('[data-backup-now]')?.addEventListener('click', function () {
            var btn = this;
            btn.disabled = true;
            setBackupStatus('正在生成本地备份并同步...', 'info');
            postS3Action('/admin/attachments/backup-now')
                .then(function (d) {
                    if (!d || d.code !== 0) throw new Error((d && d.msg) || '备份失败');
                    setBackupStatus(d.msg || '备份完成', 'success');
                })
                .catch(function (err) { setBackupStatus(err.message || '备份失败', 'error'); })
                .finally(function () { btn.disabled = false; });
        });
    })();
    </script>
@endsection
