@extends('layouts.admin')

@section('content')
    <div class="settings-page-shell profile-page-shell">
    <form method="post" action="/admin/profile" class="admin-form profile-form" data-dirty-watch>
        <input type="hidden" name="_csrf" value="{{ $csrf }}">

        <div class="profile-header">
            <img class="avatar-preview" id="avatarPreview"
                 src="{{ $user->getAvatarUrl(96) }}"
                 alt="{{ $user->nickname ?: $user->username }}"
                 width="96" height="96">
            <div class="profile-avatar-tools">
                <p class="muted small">支持上传头像或直接填写头像 URL。留空时使用 Gravatar 头像。</p>
                <div class="avatar-upload-row">
                    <input type="file" id="avatarUploadInput" accept="image/jpeg,image/png,image/gif,image/webp" hidden>
                    <button type="button" class="btn" id="avatarUploadBtn">
                        <i class="fa-solid fa-upload"></i> 上传头像
                    </button>
                    <span class="muted small" id="avatarUploadStatus"></span>
                </div>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>用户名</label>
                <input type="text" name="username" value="{{ $user->username }}" required minlength="3" maxlength="32" pattern="[A-Za-z0-9_.-]{3,32}" autocomplete="username">
                <p class="field-hint">3-32 位，只允许字母、数字、下划线、点和短横线。修改后下次登录请使用新用户名。</p>
            </div>
            <div class="form-group">
                <label>昵称</label>
                <input type="text" name="nickname" value="{{ $user->nickname }}">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>邮箱 <small class="muted">用于 Gravatar 头像</small></label>
                <input type="email" name="email" value="{{ $user->email }}">
            </div>
            <div class="form-group">
                <label>头像 URL <small class="muted">可粘贴外部图片地址，也可由上传自动填入</small></label>
                <input type="text" name="avatar" value="{{ $user->avatar }}" id="avatarInput" placeholder="https://... 或留空">
            </div>
        </div>

        {{-- ============== 社交链接 ============== --}}
        <h3 class="settings-group-title"><i class="fa-solid fa-share-nodes"></i> 社交链接</h3>
        <div class="settings-section">
            <p class="muted small">每行一个社交平台,前台 <code>author block</code> 会展示这些链接。图标填 FontAwesome 代码,例如 <code>fa-brands fa-github</code> 或完整 <code>&lt;i class="fa-brands fa-github"&gt;&lt;/i&gt;</code>。</p>

            <div id="socials-list"></div>

            <div class="socials-toolbar">
                <button type="button" class="btn" id="addSocialBtn">
                    <i class="fa-solid fa-plus"></i> 添加链接
                </button>
                <span class="muted small">快速添加:</span>
                <div class="socials-presets" id="socialsPresets">
                    @foreach(\App\Models\User::presetSocials() as $preset)
                        <button type="button" class="preset-chip"
                                data-key="{{ $preset['key'] }}"
                                data-icon="{{ $preset['icon'] }}"
                                data-label="{{ $preset['label'] }}">
                            <i class="{{ $preset['icon'] }}"></i> {{ $preset['label'] }}
                        </button>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">保存全部</button>
        </div>
    </form>

    <script>
    // 实时预览头像
    document.getElementById('avatarInput')?.addEventListener('input', function (e) {
        var v = e.target.value.trim();
        var preview = document.getElementById('avatarPreview');
        if (v) preview.src = v;
    });

    // 上传头像并自动填入头像 URL
    (function () {
        var input = document.getElementById('avatarUploadInput');
        var btn = document.getElementById('avatarUploadBtn');
        var status = document.getElementById('avatarUploadStatus');
        var avatarInput = document.getElementById('avatarInput');
        var preview = document.getElementById('avatarPreview');
        var csrf = '{{ $csrf }}';
        if (!input || !btn || !avatarInput || !preview) return;

        btn.addEventListener('click', function () {
            input.click();
        });

        input.addEventListener('change', function () {
            if (!input.files || !input.files[0]) {
                return;
            }
            var file = input.files[0];

            btn.disabled = true;
            status.textContent = '上传中...';
            var upload = window.adminUploadFile
                ? window.adminUploadFile({
                    url: '/admin/profile/avatar',
                    fields: { _csrf: csrf },
                    fileField: 'avatar',
                    file: file,
                    successMessage: '头像已上传'
                })
                : (function () {
                    var fd = new FormData();
                    fd.append('_csrf', csrf);
                    fd.append('avatar', file);
                    return fetch('/admin/profile/avatar', {
                        method: 'POST',
                        body: fd,
                        credentials: 'same-origin'
                    }).then(function (r) { return r.json(); });
                })();
            upload
                .then(function (res) {
                    if (!res || res.code !== 0) {
                        throw new Error((res && res.msg) || '上传失败');
                    }
                    var url = res.data.url;
                    avatarInput.value = url;
                    preview.src = url;
                    status.textContent = '已上传，保存全部后生效';
                })
                .catch(function (err) {
                    status.textContent = err.message || '上传失败';
                })
                .finally(function () {
                    btn.disabled = false;
                    input.value = '';
                });
        });
    })();

    // ===== 社交链接动态行 =====
    (function () {
        var list = document.getElementById('socials-list');
        var addBtn = document.getElementById('addSocialBtn');
        var presets = document.querySelectorAll('.preset-chip');
        if (!list) return;

        function escapeAttr(s) {
            return String(s).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        }

        function renderRow(idx, data) {
            data = data || { key: '', url: '', icon: '', label: '', qr: '' };
            var label = data.label || data.key || '';
            var wrap = document.createElement('div');
            wrap.className = 'social-row';
            wrap.innerHTML = ''
                + '<div class="social-row-grid">'
                +   '<div class="social-row-preview">' + renderIcon(data.icon) + '</div>'
                +   '<input type="hidden" name="socials[' + idx + '][key]" value="' + escapeAttr(data.key) + '" class="input-key">'
                +   '<input type="text" name="socials[' + idx + '][label]" placeholder="平台名称" value="' + escapeAttr(label) + '" class="input-label">'
                +   '<input type="text" name="socials[' + idx + '][url]"   placeholder="https://…" value="' + escapeAttr(data.url) + '" class="input-url">'
                +   '<input type="text" name="socials[' + idx + '][icon]"  placeholder="fa-brands fa-github" value="' + escapeAttr(data.icon) + '" class="input-icon">'
                +   '<div class="social-row-qr" data-social-qr hidden>'
                +       '<input type="text" name="socials[' + idx + '][qr]" placeholder="二维码图片 URL" value="' + escapeAttr(data.qr || '') + '" class="input-qr">'
                +       '<input type="file" class="input-qr-file" accept="image/jpeg,image/png,image/gif,image/webp" hidden>'
                +       '<button type="button" class="btn-icon social-qr-upload" title="上传二维码" aria-label="上传二维码"><i class="fa-solid fa-upload"></i></button>'
                +   '</div>'
                +   '<button type="button" class="btn-icon btn-remove" title="删除" aria-label="删除"><i class="fa-solid fa-trash"></i></button>'
                + '</div>';
            wrap.querySelector('.btn-remove').addEventListener('click', function () {
                wrap.remove();
                reindex();
            });
            bindQrUpload(wrap);
            list.appendChild(wrap);
            updateQrVisibility(wrap);
        }

        // 输入 icon 时实时预览
        list.addEventListener('input', function (e) {
            if (e.target.classList && e.target.classList.contains('input-icon')) {
                var row = e.target.closest('.social-row');
                var preview = row.querySelector('.social-row-preview');
                preview.innerHTML = renderIcon(e.target.value);
                updateQrVisibility(row);
            } else if (e.target.classList && (e.target.classList.contains('input-label') || e.target.classList.contains('input-url'))) {
                updateQrVisibility(e.target.closest('.social-row'));
            }
        });

        function isQrSource(source) {
            source = String(source || '').toLowerCase();
            return source.indexOf('telegram') >= 0
                || source.indexOf('wechat') >= 0
                || source.indexOf('weixin') >= 0
                || source.indexOf('微信') >= 0;
        }

        function isWechatSource(source) {
            source = String(source || '').toLowerCase();
            return source.indexOf('wechat') >= 0
                || source.indexOf('weixin') >= 0
                || source.indexOf('微信') >= 0;
        }

        function updateQrVisibility(row) {
            if (!row) return;
            var key = row.querySelector('.input-key')?.value || '';
            var label = row.querySelector('.input-label')?.value || '';
            var urlInput = row.querySelector('.input-url');
            var url = urlInput?.value || '';
            var icon = row.querySelector('.input-icon')?.value || '';
            var qr = row.querySelector('[data-social-qr]');
            if (!qr) return;
            var source = [key, label, url, icon].join(' ');
            var visible = isQrSource(source);
            var isWechat = isWechatSource(source);
            qr.hidden = !visible;
            row.classList.toggle('has-qr-field', visible);
            row.classList.toggle('is-wechat-social', isWechat);
            if (urlInput) {
                urlInput.required = false;
                if (isWechat) {
                    urlInput.value = '';
                }
            }
        }

        function bindQrUpload(row) {
            var fileInput = row.querySelector('.input-qr-file');
            var uploadBtn = row.querySelector('.social-qr-upload');
            var qrInput = row.querySelector('.input-qr');
            var csrf = '{{ $csrf }}';
            if (!fileInput || !uploadBtn || !qrInput) return;
            uploadBtn.addEventListener('click', function () {
                fileInput.click();
            });
            fileInput.addEventListener('change', function () {
                if (!fileInput.files || !fileInput.files[0]) return;
                var original = uploadBtn.innerHTML;
                uploadBtn.disabled = true;
                uploadBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
                var file = fileInput.files[0];
                var upload = window.adminUploadFile
                    ? window.adminUploadFile({
                        url: '/admin/posts/upload-image',
                        fields: { _csrf: csrf, purpose: 'social-qr' },
                        fileField: 'image',
                        file: file,
                        successMessage: '二维码已上传'
                    })
                    : (function () {
                        var fd = new FormData();
                        fd.append('_csrf', csrf);
                        fd.append('purpose', 'social-qr');
                        fd.append('image', file);
                        return fetch('/admin/posts/upload-image', {
                            method: 'POST',
                            body: fd,
                            credentials: 'same-origin'
                        }).then(function (r) { return r.json(); });
                    })();
                upload
                    .then(function (res) {
                        if (!res || res.code !== 0) {
                            throw new Error((res && res.msg) || '上传失败');
                        }
                        qrInput.value = res.data.url || '';
                    })
                    .catch(function (err) {
                        window.adminToast && window.adminToast(err.message || '二维码上传失败', 'error');
                    })
                    .finally(function () {
                        uploadBtn.disabled = false;
                        uploadBtn.innerHTML = original;
                        fileInput.value = '';
                    });
            });
        }

        function renderIcon(icon) {
            var safe = String(icon || '').trim();
            if (!safe) return '<span class="muted small">↑ 输入图标</span>';
            // 用户填了 <i>...</i> 直接显示
            if (safe.indexOf('<i') === 0) return safe;
            // 否则当 class 拼
            if (/^[a-zA-Z0-9 _\-]+$/.test(safe)) return '<i class="' + safe + '"></i>';
            return '<span class="text-danger" title="非法图标">⚠</span>';
        }

        function inferKey(row) {
            var keyInput = row.querySelector('.input-key');
            if (keyInput && keyInput.value.trim()) return;

            var label = (row.querySelector('.input-label')?.value || '').trim().toLowerCase();
            var url = (row.querySelector('.input-url')?.value || '').trim().toLowerCase();
            var icon = (row.querySelector('.input-icon')?.value || '').trim().toLowerCase();
            var source = [label, url, icon].join(' ');
            var key = '';

            if (source.indexOf('github') >= 0) key = 'github';
            else if (source.indexOf('twitter') >= 0 || source.indexOf('x.com') >= 0 || source.indexOf('fa-x-twitter') >= 0 || label === 'x') key = 'x';
            else if (source.indexOf('mail') >= 0 || source.indexOf('邮箱') >= 0 || source.indexOf('mailto:') >= 0 || source.indexOf('envelope') >= 0) key = 'email';
            else if (source.indexOf('rss') >= 0 || source.indexOf('feed') >= 0) key = 'rss';
            else if (source.indexOf('telegram') >= 0) key = 'telegram';
            else if (source.indexOf('mastodon') >= 0) key = 'mastodon';
            else if (source.indexOf('weibo') >= 0 || source.indexOf('微博') >= 0) key = 'weibo';
            else if (source.indexOf('wechat') >= 0 || source.indexOf('微信') >= 0) key = 'wechat';
            else if (source.indexOf('bilibili') >= 0 || source.indexOf('哔哩') >= 0) key = 'bilibili';
            else if (label) key = label.replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
            else if (url) key = url.replace(/^https?:\/\//, '').replace(/^www\./, '').split(/[/.?#]/)[0];

            if (keyInput) keyInput.value = key;
        }

        function reindex() {
            // 重新编号 socials[i] 避免删除后出现空隙
            var rows = list.querySelectorAll('.social-row');
            rows.forEach(function (row, i) {
                row.querySelectorAll('input').forEach(function (inp) {
                    var name = inp.getAttribute('name');
                    if (!name) return;
                    inp.setAttribute('name', name.replace(/socials\[\d+\]/, 'socials[' + i + ']'));
                });
            });
        }

        list.closest('form')?.addEventListener('submit', function () {
            list.querySelectorAll('.social-row').forEach(function (row) {
                inferKey(row);
                updateQrVisibility(row);
            });
        });

        // 添加空白行
        addBtn.addEventListener('click', function () {
            renderRow(list.children.length, {});
            reindex();
        });

        // 快速添加预设
        presets.forEach(function (btn) {
            btn.addEventListener('click', function () {
                renderRow(list.children.length, {
                    key:   btn.dataset.key,
                    icon:  btn.dataset.icon,
                    label: btn.dataset.label,
                    url:   '',
                });
                reindex();
                // 滚动到新行
                list.lastElementChild?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            });
        });

        // 预填已有数据(用原生 PHP 输出 JSON,绕开模板引擎指令)
        var existing = <?= json_encode($user->getSocialLinks(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        if (existing.length === 0) {
            // 没数据时给一个空行引导
            renderRow(0, {});
        } else {
            existing.forEach(function (s, i) { renderRow(i, s); });
        }
    })();
    </script>

    <div class="settings-group-title profile-section-title profile-section-title-actions">
        <span><i class="fa-solid fa-key"></i> Passkey 登录</span>
        <button type="button" class="btn btn-primary" id="passkeyRegisterBtn">
            <i class="fa-solid fa-fingerprint"></i> 绑定 Passkey
        </button>
    </div>
    <div class="settings-section profile-settings-section">
        <p class="muted small">
            当前已绑定 {{ (int)($passkeyCount ?? 0) }} 个 Passkey。绑定后可在后台登录页直接使用系统 Passkey 登录。
        </p>
        @if(!empty($passkeys))
            <table class="admin-table passkey-table">
                <thead>
                    <tr>
                        <th>绑定时间</th>
                        <th>最近使用</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($passkeys as $passkey)
                        <tr>
                            <td>{!! \App\Core\Helper::dateTimeTag((string)($passkey['created_at'] ?? '')) !!}</td>
                            <td>
                                @if(!empty($passkey['last_used_at']))
                                    {!! \App\Core\Helper::dateTimeTag((string)$passkey['last_used_at']) !!}
                                @else
                                    <span class="muted">从未</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <script>
    (function () {
        var btn = document.getElementById('passkeyRegisterBtn');
        if (!btn) return;
        btn.addEventListener('click', function () {
            var original = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> 绑定中';
            registerPasskey()
                .then(function (res) {
                    window.adminToast && window.adminToast(res.message || 'Passkey 已绑定', 'success');
                    setTimeout(function () { window.location.reload(); }, 600);
                })
                .catch(function (err) {
                    window.adminToast && window.adminToast(err.message || 'Passkey 绑定失败', 'error');
                })
                .finally(function () {
                    btn.disabled = false;
                    btn.innerHTML = original;
                });
        });
    })();
    </script>

    <h3 class="settings-group-title profile-section-title"><i class="fa-solid fa-lock"></i> 修改密码</h3>
    <form method="post" action="/admin/profile/password" class="admin-form profile-form" data-dirty-watch>
        <input type="hidden" name="_csrf" value="{{ $csrf }}">
        <div class="form-group">
            <label>原密码</label>
            <input type="password" name="old_password" required>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>新密码(≥6 位)</label>
                <input type="password" name="new_password" required minlength="6">
            </div>
            <div class="form-group">
                <label>确认新密码</label>
                <input type="password" name="confirm_password" required minlength="6">
            </div>
        </div>
        <button type="submit" class="btn btn-primary">修改密码</button>
    </form>
    </div>
@endsection
