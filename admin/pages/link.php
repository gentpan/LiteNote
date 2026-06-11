@extends('layouts.admin')

@section('content')
    <div class="admin-toolbar link-admin-toolbar">
        <button class="btn btn-primary" id="add-link-btn" type="button">+ 添加友链</button>
        <button class="btn" id="select-all-links-btn" type="button">
            <i class="fa-regular fa-square-check"></i> 全部选择
        </button>
        <button class="btn" id="refresh-all-rss-btn" type="button">
            <i class="fa-solid fa-arrows-rotate"></i> 刷新全部 RSS
        </button>
        <button class="btn btn-danger" id="bulk-delete-links-btn" type="button" disabled>
            <i class="fa-regular fa-trash-can"></i> 删除选中
        </button>
        <span class="muted" id="link-selected-count">已选择 0 条</span>
    </div>

    <div class="link-rss-progress hidden" id="link-rss-progress" aria-live="polite">
        <div class="link-rss-progress-head">
            <strong>RSS 刷新进度</strong>
            <span id="link-rss-progress-text">准备刷新</span>
        </div>
        <div class="link-rss-progress-track">
            <span id="link-rss-progress-bar"></span>
        </div>
        <div class="link-rss-progress-meta">
            <span id="link-rss-progress-current">等待开始</span>
            <span id="link-rss-progress-summary"></span>
        </div>
        <ul class="link-rss-errors hidden" id="link-rss-errors"></ul>
    </div>

    <form id="new-link-form" method="post" action="/admin/links/save" class="admin-form hidden">
        <h3 id="link-form-title" class="admin-form-title"><i class="fa-solid fa-link"></i> 添加友链</h3>
        <input type="hidden" name="_csrf" value="{{ $csrf }}">
        <input type="hidden" name="id" id="link-id" value="">
        <div class="form-row">
            <div class="form-group">
                <label>名称 *</label>
                <input type="text" name="name" id="link-name" required>
            </div>
            <div class="form-group flex-2">
                <label>URL *</label>
                <input type="url" name="url" id="link-url" required>
            </div>
            <div class="form-group">
                <label>排序</label>
                <input type="number" name="sort" id="link-sort" value="0">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Logo URL</label>
                <input type="text" name="logo" id="link-logo">
            </div>
            <div class="form-group flex-2">
                <label>RSS URL（可选，用于抓取最新文章）</label>
                <input type="url" name="rss_url" id="link-rss" placeholder="https://example.com/feed.xml">
            </div>
        </div>
        <div class="form-group">
            <label>描述</label>
            <input type="text" name="description" id="link-desc">
        </div>
        <div class="form-group">
            <input type="hidden" name="is_enabled" value="0">
            <label><input type="checkbox" name="is_enabled" id="link-enabled" value="1" checked> 启用</label>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary" id="link-submit-btn">保存</button>
            <button type="button" class="btn" id="link-cancel-btn">取消</button>
        </div>
    </form>

    <table class="admin-table admin-action-table admin-action-table-medium link-admin-table">
        <thead>
            <tr>
                <th class="admin-select-col">
                    <input type="checkbox" id="link-check-all" aria-label="全部选择">
                </th>
                <th>ID</th>
                <th>名称</th>
                <th>URL</th>
                <th>RSS</th>
                <th>排序</th>
                <th>启用</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            @foreach($links as $l)
                @php
                    $status = $rssStatus[$l->id] ?? null;
                @endphp
                <tr data-link-row="{{ $l->id }}" data-link-name="{{ $l->name }}">
                    <td class="admin-select-col">
                        <input type="checkbox" class="link-row-check" value="{{ $l->id }}" aria-label="选择 {{ $l->name }}">
                    </td>
                    <td>{{ $l->id }}</td>
                    <td>
                        <span class="link-name-text">{{ $l->name }}</span>
                        @if($l->rss_url)
                            @php $isPendingRssRefresh = ($status['error'] ?? '') === '还没有刷新缓存，请点击刷新 RSS'; @endphp
                            <span class="link-mobile-rss-state" aria-label="RSS 状态">
                                @if(!empty($status['ok']))
                                    <span class="status status-published"><span class="admin-check-icon" aria-hidden="true"><i class="fa-solid fa-check"></i></span> 可用</span>
                                @else
                                    <span class="status status-draft"><i class="fa-solid {{ $isPendingRssRefresh ? 'fa-clock' : 'fa-xmark' }}"></i> {{ $isPendingRssRefresh ? '未刷新' : '抓取失败' }}</span>
                                @endif
                            </span>
                        @endif
                    </td>
                    <td><a href="{{ $l->url }}" target="_blank" rel="nofollow noopener">{{ $l->url }}</a></td>
                    <td data-rss-status class="link-rss-cell">
                        @if($l->rss_url)
                            <div class="link-rss-inline">
                            @if(!empty($status['ok']))
                                <span class="status status-published"><span class="admin-check-icon" aria-hidden="true"><i class="fa-solid fa-check"></i></span> 可用</span>
                                <small class="rss-result">已读取 {{ (int)($status['count'] ?? 0) }} 条{{ !empty($status['from_cache']) ? '，来自缓存' : '' }}</small>
                            @else
                                <span class="status status-draft"><i class="fa-solid {{ $isPendingRssRefresh ? 'fa-clock' : 'fa-xmark' }}"></i> {{ $isPendingRssRefresh ? '未刷新' : '抓取失败' }}</span>
                                <small class="rss-result rss-result-error">{{ $status['error'] ?? '无法更新，未返回有效内容' }}</small>
                            @endif
                            <small class="rss-updated">
                                @if(!empty($status['updated_at']))
                                    {{ date('m-d H:i', (int)$status['updated_at']) }}
                                @else
                                    未刷新
                                @endif
                            </small>
                            <code class="rss-url">{{ $l->rss_url }}</code>
                            </div>
                        @else
                            <span class="muted">无</span>
                        @endif
                    </td>
                    <td>{{ $l->sort }}</td>
                    <td>{!! $l->is_enabled ? '<span class="admin-check-icon admin-check-icon-sm" aria-hidden="true"><i class="fa-solid fa-check"></i></span>' : '<i class="fa-solid fa-xmark"></i>' !!}</td>
                    <td>
                        <div class="admin-action-bar">
                            <button type="button"
                                class="admin-action-btn admin-action-edit edit-link-btn"
                                title="编辑"
                                aria-label="编辑"
                                data-id="{{ $l->id }}"
                                data-name="{{ $l->name }}"
                                data-url="{{ $l->url }}"
                                data-logo="{{ $l->logo }}"
                                data-description="{{ $l->description }}"
                                data-rss="{{ $l->rss_url }}"
                                data-sort="{{ $l->sort }}"
                                data-enabled="{{ $l->is_enabled }}">
                                <i class="fa-regular fa-pen-to-square"></i>
                            </button>
                            @if($l->rss_url)
                                <button type="button"
                                        class="admin-action-btn admin-action-refresh refresh-rss-btn"
                                        data-id="{{ $l->id }}"
                                        data-name="{{ $l->name }}"
                                        title="刷新 RSS"
                                        aria-label="刷新 RSS">
                                    <i class="fa-solid fa-rotate"></i>
                                </button>
                            @endif
                            <form method="post" action="/admin/links/delete" style="display:inline"
                                  data-confirm="确定删除这个友链？此操作不可撤销。"
                                  data-confirm-title="删除友链"
                                  data-confirm-text="确认删除">
                                <input type="hidden" name="_csrf" value="{{ $csrf }}">
                                <input type="hidden" name="id" value="{{ $l->id }}">
                                <button type="submit"
                                        class="admin-action-btn admin-action-delete"
                                        title="删除"
                                        aria-label="删除">
                                    <i class="fa-regular fa-trash-can"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <script>
    (function () {
        var csrf = '{{ $csrf }}';
        var form = document.getElementById('new-link-form');
        var title = document.getElementById('link-form-title');
        var submitBtn = document.getElementById('link-submit-btn');
        var checkAll = document.getElementById('link-check-all');
        var selectAllBtn = document.getElementById('select-all-links-btn');
        var bulkDeleteBtn = document.getElementById('bulk-delete-links-btn');
        var refreshAllBtn = document.getElementById('refresh-all-rss-btn');
        var selectedCount = document.getElementById('link-selected-count');
        var progressBox = document.getElementById('link-rss-progress');
        var progressText = document.getElementById('link-rss-progress-text');
        var progressBar = document.getElementById('link-rss-progress-bar');
        var progressCurrent = document.getElementById('link-rss-progress-current');
        var progressSummary = document.getElementById('link-rss-progress-summary');
        var errorList = document.getElementById('link-rss-errors');

        function linkLoadingSpinnerSvg() {
            return '<span class="site-loading-spinner admin-loading-spinner" aria-hidden="true">'
                + '<svg stroke="#0052d9" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">'
                + '<g>'
                + '<circle cx="12" cy="12" r="9.5" fill="none" stroke-width="2.35" stroke-linecap="round">'
                + '<animate attributeName="stroke-dasharray" dur="1.5s" calcMode="spline" values="0 150;42 150;42 150;42 150" keyTimes="0;0.475;0.95;1" keySplines="0.42,0,0.58,1;0.42,0,0.58,1;0.42,0,0.58,1" repeatCount="indefinite"/>'
                + '<animate attributeName="stroke-dashoffset" dur="1.5s" calcMode="spline" values="0;-16;-59;-59" keyTimes="0;0.475;0.95;1" keySplines="0.42,0,0.58,1;0.42,0,0.58,1;0.42,0,0.58,1" repeatCount="indefinite"/>'
                + '</circle>'
                + '<animateTransform attributeName="transform" type="rotate" dur="2s" values="0 12 12;360 12 12" repeatCount="indefinite"/>'
                + '</g>'
                + '</svg>'
                + '</span>';
        }

        function escapeHtml(value) {
            return String(value || '').replace(/[&<>"']/g, function (ch) {
                return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'}[ch];
            });
        }

        function confirmAction(options) {
            if (window.adminConfirm) {
                return window.adminConfirm(options);
            }
            return Promise.resolve(window.confirm(options.message || '确定执行此操作？'));
        }

        function requestJson(url, fd) {
            return fetch(url, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: fd
            }).then(function (response) {
                return response.json().catch(function () {
                    return { code: 1, msg: '服务器返回了无效响应' };
                }).then(function (data) {
                    if (!response.ok || !data || data.code !== 0) {
                        var err = new Error((data && (data.msg || data.error)) || '请求失败');
                        err.payload = data || {};
                        throw err;
                    }
                    return data;
                });
            });
        }

        var f = {
            id:      document.getElementById('link-id'),
            name:    document.getElementById('link-name'),
            url:     document.getElementById('link-url'),
            sort:    document.getElementById('link-sort'),
            logo:    document.getElementById('link-logo'),
            rss:     document.getElementById('link-rss'),
            desc:    document.getElementById('link-desc'),
            enabled: document.getElementById('link-enabled')
        };

        function setFormTitle(text, icon) {
            title.innerHTML = '<i class="' + (icon || 'fa-solid fa-link') + '"></i> ' + escapeHtml(text);
        }

        function resetForm() {
            f.id.value = ''; f.name.value = ''; f.url.value = ''; f.sort.value = '0';
            f.logo.value = ''; f.rss.value = ''; f.desc.value = ''; f.enabled.checked = true;
            setFormTitle('添加友链', 'fa-solid fa-link');
            submitBtn.textContent = '保存';
        }

        function rowChecks() {
            return Array.prototype.slice.call(document.querySelectorAll('.link-row-check'));
        }

        function selectedIds() {
            return rowChecks().filter(function (box) { return box.checked; }).map(function (box) { return box.value; });
        }

        function updateSelectedState() {
            var boxes = rowChecks();
            var selected = selectedIds();
            selectedCount.textContent = '已选择 ' + selected.length + ' 条';
            bulkDeleteBtn.disabled = selected.length === 0;
            if (checkAll) {
                checkAll.checked = boxes.length > 0 && selected.length === boxes.length;
                checkAll.indeterminate = selected.length > 0 && selected.length < boxes.length;
            }
            if (selectAllBtn) {
                selectAllBtn.innerHTML = selected.length === boxes.length && boxes.length > 0
                    ? '<i class="fa-regular fa-square"></i> 取消选择'
                    : '<i class="fa-regular fa-square-check"></i> 全部选择';
            }
        }

        function setButtonLoading(btn, loading, originalHtml) {
            if (!btn) return originalHtml || '';
            if (loading) {
                btn.dataset.originalHtml = btn.innerHTML;
                btn.disabled = true;
                btn.classList.add('is-saving');
                btn.innerHTML = linkLoadingSpinnerSvg();
                return btn.dataset.originalHtml;
            }
            btn.disabled = false;
            btn.classList.remove('is-saving');
            btn.innerHTML = originalHtml || btn.dataset.originalHtml || btn.innerHTML;
            delete btn.dataset.originalHtml;
            return '';
        }

        function updateRssStatus(btn, data, failedMessage) {
            var row = btn.closest('[data-link-row]');
            var cell = row ? row.querySelector('[data-rss-status]') : null;
            if (!cell) return;
            var updatedText = data && data.updated_at ? formatRssUpdatedAt(Number(data.updated_at)) : '未刷新';
            updateMobileRssState(row, data, updatedText);
            if (data && data.code === 0) {
                cell.innerHTML = '<div class="link-rss-inline">'
                    + '<span class="status status-published"><span class="admin-check-icon" aria-hidden="true"><i class="fa-solid fa-check"></i></span> 可用</span>'
                    + '<small class="rss-result">已读取 ' + Number(data.count || 0) + ' 条</small>'
                    + '<small class="rss-updated">' + escapeHtml(updatedText) + '</small>'
                    + '<code class="rss-url">' + escapeHtml(data.rss_url || '') + '</code>'
                    + '</div>';
            } else {
                cell.innerHTML = '<div class="link-rss-inline">'
                    + '<span class="status status-draft"><i class="fa-solid fa-xmark"></i> 抓取失败</span>'
                    + '<small class="rss-result rss-result-error">' + escapeHtml(failedMessage || '无法更新，未返回有效内容') + '</small>'
                    + '<small class="rss-updated">' + escapeHtml(updatedText) + '</small>'
                    + '<code class="rss-url">' + escapeHtml((data && data.rss_url) || '') + '</code>'
                    + '</div>';
            }
        }

        function updateMobileRssState(row, data, updatedText) {
            if (!row) return;
            var target = row.querySelector('.link-mobile-rss-state');
            if (!target) return;
            if (data && data.code === 0) {
                target.innerHTML = '<span class="status status-published"><span class="admin-check-icon" aria-hidden="true"><i class="fa-solid fa-check"></i></span> 可用</span>';
            } else {
                target.innerHTML = '<span class="status status-draft"><i class="fa-solid fa-xmark"></i> 抓取失败</span>';
            }
        }

        function formatRssUpdatedAt(timestamp) {
            if (!timestamp) return '未刷新';
            var d = new Date(timestamp * 1000);
            var pad = function (n) { return n < 10 ? '0' + n : String(n); };
            return pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes());
        }

        function refreshOne(btn, showToast) {
            if (!btn || btn.disabled) return Promise.resolve({ ok: false, skipped: true });
            var original = setButtonLoading(btn, true);
            var fd = new FormData();
            fd.append('_csrf', csrf);
            fd.append('id', btn.dataset.id || '');

            return requestJson('/admin/links/refresh', fd).then(function (data) {
                setButtonLoading(btn, false, original);
                btn.setAttribute('title', '已刷新');
                btn.setAttribute('aria-label', '已刷新');
                updateRssStatus(btn, data, '');
                if (showToast && window.adminToast) {
                    window.adminToast(data.msg || 'RSS 缓存已刷新', 'success');
                }
                return { ok: true, data: data };
            }).catch(function (err) {
                var payload = err.payload || {};
                var message = payload.error || payload.msg || err.message || '刷新失败';
                setButtonLoading(btn, false, original);
                updateRssStatus(btn, payload, message);
                if (showToast && window.adminToast) {
                    window.adminToast(message, 'error');
                }
                return {
                    ok: false,
                    name: btn.dataset.name || ('ID ' + (btn.dataset.id || '')),
                    message: message
                };
            });
        }

        function resetProgress(total) {
            progressBox.classList.remove('hidden');
            errorList.classList.add('hidden');
            errorList.innerHTML = '';
            progressText.textContent = '0 / ' + total;
            progressBar.style.width = '0%';
            progressCurrent.textContent = '准备刷新';
            progressSummary.textContent = '';
        }

        function addProgressError(name, message) {
            errorList.classList.remove('hidden');
            var li = document.createElement('li');
            li.innerHTML = '<strong>' + escapeHtml(name) + '</strong><span>' + escapeHtml(message) + '</span>';
            errorList.appendChild(li);
        }

        document.getElementById('add-link-btn').addEventListener('click', function () {
            if (form.classList.contains('hidden') || f.id.value !== '') {
                resetForm();
                form.classList.remove('hidden');
                f.name.focus();
            } else {
                form.classList.add('hidden');
            }
        });

        document.getElementById('link-cancel-btn').addEventListener('click', function () {
            form.classList.add('hidden');
        });

        document.querySelectorAll('.edit-link-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var d = btn.dataset;
                f.id.value = d.id;
                f.name.value = d.name || '';
                f.url.value = d.url || '';
                f.sort.value = d.sort || '0';
                f.logo.value = d.logo || '';
                f.rss.value = d.rss || '';
                f.desc.value = d.description || '';
                f.enabled.checked = d.enabled === '1';
                setFormTitle('编辑友链', 'fa-regular fa-pen-to-square');
                submitBtn.textContent = '更新';
                form.classList.remove('hidden');
                form.scrollIntoView({ behavior: 'smooth', block: 'center' });
                f.name.focus();
            });
        });

        if (checkAll) {
            checkAll.addEventListener('change', function () {
                rowChecks().forEach(function (box) { box.checked = checkAll.checked; });
                updateSelectedState();
            });
        }

        if (selectAllBtn) {
            selectAllBtn.addEventListener('click', function () {
                var boxes = rowChecks();
                var shouldCheck = selectedIds().length !== boxes.length;
                boxes.forEach(function (box) { box.checked = shouldCheck; });
                updateSelectedState();
            });
        }

        document.addEventListener('change', function (event) {
            if (event.target && event.target.classList.contains('link-row-check')) {
                updateSelectedState();
            }
        });

        bulkDeleteBtn.addEventListener('click', function () {
            var ids = selectedIds();
            if (!ids.length) {
                window.adminToast && window.adminToast('请选择要删除的友链', 'error');
                return;
            }
            confirmAction({
                title: '批量删除友链',
                message: '确定删除选中的 ' + ids.length + ' 条友链？此操作不可撤销。',
                tone: 'danger',
                confirmText: '确认删除'
            }).then(function (ok) {
                if (!ok) return;
                var fd = new FormData();
                fd.append('_csrf', csrf);
                ids.forEach(function (id) { fd.append('ids[]', id); });
                bulkDeleteBtn.disabled = true;
                requestJson('/admin/links/bulk-delete', fd).then(function (data) {
                    ids.forEach(function (id) {
                        var row = document.querySelector('[data-link-row="' + id + '"]');
                        if (row) row.remove();
                    });
                    updateSelectedState();
                    window.adminToast && window.adminToast(data.msg || '已删除选中友链', 'success');
                }).catch(function (err) {
                    updateSelectedState();
                    window.adminToast && window.adminToast(err.message || '批量删除失败', 'error');
                });
            });
        });

        document.querySelectorAll('.refresh-rss-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                refreshOne(btn, true);
            });
        });

        refreshAllBtn.addEventListener('click', function () {
            var buttons = Array.prototype.slice.call(document.querySelectorAll('.refresh-rss-btn'));
            if (!buttons.length) {
                window.adminToast && window.adminToast('没有配置 RSS 的友链', 'error');
                return;
            }
            confirmAction({
                title: '刷新全部 RSS',
                message: '将依次刷新 ' + buttons.length + ' 个 RSS，并显示无法更新的原因。',
                tone: 'primary',
                confirmText: '开始刷新'
            }).then(function (ok) {
                if (!ok) return;
                var total = buttons.length;
                var done = 0;
                var success = 0;
                var failed = 0;
                resetProgress(total);
                refreshAllBtn.disabled = true;
                bulkDeleteBtn.disabled = true;

                buttons.reduce(function (chain, btn) {
                    return chain.then(function () {
                        progressCurrent.textContent = '正在刷新：' + (btn.dataset.name || ('ID ' + btn.dataset.id));
                        return refreshOne(btn, false).then(function (result) {
                            done += 1;
                            if (result.ok) {
                                success += 1;
                            } else {
                                failed += 1;
                                addProgressError(result.name || (btn.dataset.name || '未知友链'), result.message || '无法更新');
                            }
                            progressText.textContent = done + ' / ' + total;
                            progressBar.style.width = Math.round(done / total * 100) + '%';
                            progressSummary.textContent = '成功 ' + success + '，失败 ' + failed;
                        });
                    });
                }, Promise.resolve()).then(function () {
                    progressCurrent.textContent = '正在更新订阅聚合缓存';
                    var fd = new FormData();
                    fd.append('_csrf', csrf);
                    return requestJson('/admin/links/refresh-aggregate', fd).then(function (data) {
                        progressCurrent.textContent = '刷新完成，聚合缓存 ' + Number(data.count || 0) + ' 条';
                        window.adminToast && window.adminToast('RSS 刷新完成：成功 ' + success + '，失败 ' + failed, failed ? 'error' : 'success');
                    }).catch(function (err) {
                        progressCurrent.textContent = 'RSS 已刷新，但订阅聚合缓存更新失败';
                        addProgressError('订阅聚合缓存', err.message || '无法更新');
                        window.adminToast && window.adminToast(err.message || '订阅聚合缓存更新失败', 'error');
                    });
                }).finally(function () {
                    refreshAllBtn.disabled = false;
                    updateSelectedState();
                });
            });
        });

        updateSelectedState();
    })();
    </script>
@endsection
