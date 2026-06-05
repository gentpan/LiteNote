@extends('layouts.admin')

@section('content')
    <div class="admin-toolbar">
        <button class="btn btn-primary" id="add-link-btn" type="button">+ 添加友链</button>
    </div>

    <form id="new-link-form" method="post" action="/admin/links/save" class="admin-form hidden">
        <h3 id="link-form-title" class="admin-form-title">添加友链</h3>
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

    <table class="admin-table">
        <thead>
            <tr><th>ID</th><th>名称</th><th>URL</th><th>RSS</th><th>排序</th><th>启用</th><th>操作</th></tr>
        </thead>
        <tbody>
            @foreach($links as $l)
            <tr>
                <td>{{ $l->id }}</td>
                <td>{{ $l->name }}</td>
                <td><a href="{{ $l->url }}" target="_blank" rel="nofollow noopener">{{ $l->url }}</a></td>
                <td>
                    @if($l->rss_url)
                        @if(($rssStatus[$l->id] ?? false))
                            <span class="status status-published"><span class="admin-check-icon" aria-hidden="true"><i class="fa-solid fa-check"></i></span> 可用</span>
                        @else
                            <span class="status status-draft"><i class="fa-solid fa-xmark"></i> 抓取失败</span>
                        @endif
                        <code style="display:block;font-size:11px">{{ $l->rss_url }}</code>
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

        function resetForm() {
            f.id.value = ''; f.name.value = ''; f.url.value = ''; f.sort.value = '0';
            f.logo.value = ''; f.rss.value = ''; f.desc.value = ''; f.enabled.checked = true;
            title.textContent = '添加友链';
            submitBtn.textContent = '保存';
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
                title.textContent = '编辑友链';
                submitBtn.textContent = '更新';
                form.classList.remove('hidden');
                form.scrollIntoView({ behavior: 'smooth', block: 'center' });
                f.name.focus();
            });
        });

        document.querySelectorAll('.refresh-rss-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (btn.disabled) return;
                var original = btn.innerHTML;
                var originalTitle = btn.getAttribute('title') || '刷新 RSS';
                btn.disabled = true;
                btn.setAttribute('title', '刷新中');
                btn.setAttribute('aria-label', '刷新中');
                btn.innerHTML = linkLoadingSpinnerSvg();
                var fd = new FormData();
                fd.append('_csrf', csrf);
                fd.append('id', btn.dataset.id);
                fetch('/admin/links/refresh', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: fd
                }).then(function (r) { return r.json(); }).then(function (data) {
                    if (data && data.code === 0) {
                        btn.setAttribute('title', '已刷新');
                        btn.setAttribute('aria-label', '已刷新');
                        btn.innerHTML = '<span class="admin-check-icon" aria-hidden="true"><i class="fa-solid fa-check"></i></span>';
                        setTimeout(function () { location.reload(); }, 700);
                    } else {
                        btn.disabled = false;
                        btn.setAttribute('title', originalTitle);
                        btn.setAttribute('aria-label', originalTitle);
                        btn.innerHTML = original;
                        window.adminToast && window.adminToast((data && data.msg) || '刷新失败', 'error');
                    }
                }).catch(function () {
                    btn.disabled = false;
                    btn.setAttribute('title', originalTitle);
                    btn.setAttribute('aria-label', originalTitle);
                    btn.innerHTML = original;
                    window.adminToast && window.adminToast('网络错误，刷新失败', 'error');
                });
            });
        });
    })();
    </script>
@endsection
