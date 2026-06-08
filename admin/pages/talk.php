@extends('layouts.admin')

@section('content')
    @php
        $talkKeywordBadges = static function (string $content): string {
            preg_match_all('/#([\p{L}\p{N}_-]+)/u', $content, $matches);
            $keywords = array_values(array_unique(array_filter(array_map('trim', $matches[1] ?? []))));
            if (empty($keywords)) {
                return '<span class="muted">-</span>';
            }
            return implode('', array_map(
                static fn(string $keyword): string => '<span class="talk-keyword-badge">#' . htmlspecialchars($keyword, ENT_QUOTES, 'UTF-8') . '</span>',
                $keywords
            ));
        };
        $rowNo = (($page ?? 1) - 1) * ($perPage ?? 20);
    @endphp
    <table class="admin-table admin-action-table talk-admin-table">
        <thead>
            <tr><th>序号</th><th>内容</th><th>关键词</th><th>公开</th><th>时间</th><th>操作</th></tr>
        </thead>
        <tbody>
            @foreach($list as $s)
            @php $rowNo++; @endphp
            <tr>
                <td>{{ $rowNo }}</td>
                <td><div class="comment-cell" data-talk-content>{{ \App\Core\Helper::truncate($s->content, 100) }}</div></td>
                <td data-talk-keywords>{!! $talkKeywordBadges((string)($s->content ?? '')) !!}</td>
                <td data-talk-public>{!! $s->is_public ? '<span class="status status-published">公开</span>' : '<span class="status status-draft">隐藏</span>' !!}</td>
                <td data-talk-time>{!! \App\Core\Helper::dateTimeTag($s->published_at ?: $s->created_at) !!}</td>
                <td>
                    <div class="admin-action-bar">
                        <button type="button"
                                class="admin-action-btn admin-action-toggle"
                                title="{{ $s->is_public ? '设为隐藏' : '恢复公开' }}"
                                aria-label="{{ $s->is_public ? '设为隐藏' : '恢复公开' }}"
                                data-talk-toggle
                                data-id="{{ $s->id }}">
                            <i class="fa-solid {{ $s->is_public ? 'fa-eye-slash' : 'fa-eye' }}"></i>
                        </button>
                        <button type="button"
                                class="admin-action-btn admin-action-edit"
                                title="编辑"
                                aria-label="编辑"
                                data-talk-edit
                                data-id="{{ $s->id }}">
                            <i class="fa-regular fa-pen-to-square"></i>
                        </button>
                        <button type="submit"
                                form="talk-delete-form-{{ $s->id }}"
                                class="admin-action-btn admin-action-delete"
                                title="删除"
                                aria-label="删除">
                            <i class="fa-regular fa-trash-can"></i>
                        </button>
                    </div>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    @foreach($list as $s)
        <form id="talk-delete-form-{{ $s->id }}" method="post" action="/admin/talk/delete" class="hidden"
              data-confirm="确定删除这条说说？此操作不可撤销。"
              data-confirm-title="删除说说"
              data-confirm-text="确认删除">
            <input type="hidden" name="_csrf" value="{{ $csrf }}">
            <input type="hidden" name="id" value="{{ $s->id }}">
        </form>
    @endforeach
    {!! $paginator ?? '' !!}

    <div class="admin-dialog-backdrop talk-edit-dialog" data-talk-edit-dialog hidden>
        <div class="admin-dialog-shell">
            <form method="post" action="" class="admin-dialog talk-edit-dialog-panel" data-talk-edit-form>
                <input type="hidden" name="_csrf" value="{{ $csrf }}">
                <input type="hidden" name="post_type" value="talk" data-talk-field="post_type">
                <input type="hidden" name="music_id" value="0" data-talk-field="music_id">
                <div class="admin-dialog-body">
                    <div class="admin-dialog-layout">
                        <div class="admin-dialog-icon admin-dialog-icon-primary">
                            <i class="fa-regular fa-pen-to-square"></i>
                        </div>
                        <div class="admin-dialog-copy talk-edit-dialog-copy">
                            <h3>编辑滔客</h3>
                            <p>调整内容、图片、心情和展示状态。</p>
                            <div class="talk-edit-form-grid">
                                <label>
                                    <span>内容</span>
                                    <textarea name="content" rows="5" required data-talk-field="content"></textarea>
                                </label>
                                <label>
                                    <span>图片 URL</span>
                                    <input type="text" name="images" data-talk-field="images" placeholder="多个图片用英文逗号分隔">
                                </label>
                                <div class="form-row">
                                    <label>
                                        <span>心情</span>
                                        <input type="text" name="mood" data-talk-field="mood" placeholder="例如：开心">
                                    </label>
                                    <label>
                                        <span>发布时间</span>
                                        <input type="text" name="published_at" data-talk-field="published_at" placeholder="2026-06-05 18:00:00">
                                    </label>
                                </div>
                                <label class="admin-inline-check talk-edit-public">
                                    <input type="hidden" name="is_public" value="0">
                                    <input type="checkbox" name="is_public" value="1" data-talk-field="is_public">
                                    公开展示
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="admin-dialog-actions">
                    <button type="submit" class="btn btn-primary" data-talk-save>保存</button>
                    <button type="button" class="btn admin-dialog-cancel" data-talk-edit-cancel>取消</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    (function () {
        var dialog = document.querySelector('[data-talk-edit-dialog]');
        var form = document.querySelector('[data-talk-edit-form]');
        if (!dialog || !form) return;

        var row = null;
        var fields = {
            postType: form.querySelector('[data-talk-field="post_type"]'),
            musicId: form.querySelector('[data-talk-field="music_id"]'),
            content: form.querySelector('[data-talk-field="content"]'),
            images: form.querySelector('[data-talk-field="images"]'),
            mood: form.querySelector('[data-talk-field="mood"]'),
            publishedAt: form.querySelector('[data-talk-field="published_at"]'),
            isPublic: form.querySelector('[data-talk-field="is_public"]')
        };

        function openDialog(data, sourceRow) {
            row = sourceRow;
            form.action = '/admin/talk/' + data.id + '/edit';
            fields.postType.value = data.post_type || 'talk';
            fields.musicId.value = data.music_id || '0';
            fields.content.value = data.content || '';
            fields.images.value = data.images || '';
            fields.mood.value = data.mood || '';
            fields.publishedAt.value = data.published_at || '';
            fields.isPublic.checked = String(data.is_public || '0') === '1';
            dialog.hidden = false;
            document.body.classList.add('admin-dialog-open');
            setTimeout(function () { fields.content.focus(); }, 0);
        }

        function closeDialog() {
            dialog.hidden = true;
            document.body.classList.remove('admin-dialog-open');
            row = null;
        }

        document.querySelectorAll('[data-talk-edit]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var id = btn.dataset.id;
                if (!id) return;
                btn.disabled = true;
                fetch('/admin/talk/' + encodeURIComponent(id) + '/edit', {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                    .then(function (r) { return r.json(); })
                    .then(function (res) {
                        if (!res || res.code !== 0) throw new Error((res && res.msg) || '读取失败');
                        openDialog(res.data, btn.closest('tr'));
                    })
                    .catch(function (err) {
                        window.adminToast ? window.adminToast(err.message || '读取失败', 'error') : alert(err.message || '读取失败');
                    })
                    .finally(function () { btn.disabled = false; });
            });
        });

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var save = form.querySelector('[data-talk-save]');
            var fd = new FormData(form);
            save.disabled = true;
            fetch(form.action, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: fd
            })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (!res || res.code !== 0) throw new Error((res && res.msg) || '保存失败');
                    if (row && res.data) {
                        row.querySelector('[data-talk-content]').textContent = res.data.content_preview || '';
                        row.querySelector('[data-talk-keywords]').innerHTML = renderKeywords(res.data.keywords || []);
                        updatePublicState(row, Number(res.data.is_public) === 1);
                        row.querySelector('[data-talk-time]').textContent = res.data.published_at || '';
                    }
                    closeDialog();
                    window.adminToast && window.adminToast(res.msg || '已保存', 'success');
                })
                .catch(function (err) {
                    window.adminToast ? window.adminToast(err.message || '保存失败', 'error') : alert(err.message || '保存失败');
                })
                .finally(function () { save.disabled = false; });
        });

        function escapeHtml(value) {
            return String(value).replace(/[&<>"']/g, function (ch) {
                return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[ch];
            });
        }

        function renderKeywords(keywords) {
            if (!keywords || !keywords.length) return '<span class="muted">-</span>';
            return keywords.map(function (keyword) {
                return '<span class="talk-keyword-badge">#' + escapeHtml(keyword) + '</span>';
            }).join('');
        }

        function updatePublicState(sourceRow, isPublic) {
            var cell = sourceRow.querySelector('[data-talk-public]');
            var toggle = sourceRow.querySelector('[data-talk-toggle]');
            if (cell) {
                cell.innerHTML = isPublic
                    ? '<span class="status status-published">公开</span>'
                    : '<span class="status status-draft">隐藏</span>';
            }
            if (toggle) {
                toggle.title = isPublic ? '设为隐藏' : '恢复公开';
                toggle.setAttribute('aria-label', toggle.title);
                toggle.innerHTML = isPublic ? '<i class="fa-solid fa-eye-slash"></i>' : '<i class="fa-solid fa-eye"></i>';
            }
        }

        document.querySelectorAll('[data-talk-toggle]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var id = btn.dataset.id;
                if (!id) return;
                var fd = new FormData();
                fd.append('_csrf', '{{ $csrf }}');
                btn.disabled = true;
                fetch('/admin/talk/' + encodeURIComponent(id) + '/toggle', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: fd
                })
                    .then(function (r) { return r.json(); })
                    .then(function (res) {
                        if (!res || res.code !== 0) throw new Error((res && res.msg) || '操作失败');
                        updatePublicState(btn.closest('tr'), Number(res.data && res.data.is_public) === 1);
                        window.adminToast && window.adminToast(res.msg || '已更新', 'success');
                    })
                    .catch(function (err) {
                        window.adminToast ? window.adminToast(err.message || '操作失败', 'error') : alert(err.message || '操作失败');
                    })
                    .finally(function () { btn.disabled = false; });
            });
        });

        dialog.addEventListener('click', function (e) {
            if (e.target === dialog || e.target.classList.contains('admin-dialog-shell')) closeDialog();
        });
        document.querySelector('[data-talk-edit-cancel]').addEventListener('click', closeDialog);
        document.addEventListener('keydown', function (e) {
            if (!dialog.hidden && e.key === 'Escape') closeDialog();
        });
    })();
    </script>
@endsection
