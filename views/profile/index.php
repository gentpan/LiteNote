@extends('layouts.admin')

@section('content')
    <h3>个人资料</h3>
    <form method="post" action="/admin/profile" class="admin-form" data-dirty-watch>
        <input type="hidden" name="_csrf" value="{{ $csrf }}">

        <div class="profile-header">
            <img class="avatar-preview" id="avatarPreview"
                 src="{{ $user->getAvatarUrl(96) }}"
                 alt="{{ $user->nickname ?: $user->username }}"
                 width="96" height="96">
            <div class="profile-avatar-tools">
                <p class="muted small">支持上传头像或直接填写头像 URL。留空时使用 Gravatar 头像。</p>
                <div class="avatar-upload-row">
                    <input type="file" id="avatarUploadInput" accept="image/jpeg,image/png,image/gif,image/webp">
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
                <input type="text" value="{{ $user->username }}" disabled>
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
            if (!input.files || !input.files[0]) {
                status.textContent = '请先选择图片';
                return;
            }
            var fd = new FormData();
            fd.append('_csrf', csrf);
            fd.append('avatar', input.files[0]);

            btn.disabled = true;
            status.textContent = '上传中...';
            fetch('/admin/profile/avatar', {
                method: 'POST',
                body: fd,
                credentials: 'same-origin'
            })
                .then(function (r) { return r.json(); })
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
            data = data || { key: '', url: '', icon: '', label: '' };
            var wrap = document.createElement('div');
            wrap.className = 'social-row';
            wrap.innerHTML = ''
                + '<div class="social-row-grid">'
                +   '<input type="text" name="socials[' + idx + '][key]"   placeholder="平台 key(github / x / email …)" value="' + escapeAttr(data.key) + '" class="input-key">'
                +   '<input type="text" name="socials[' + idx + '][url]"   placeholder="https://…" value="' + escapeAttr(data.url) + '" class="input-url">'
                +   '<input type="text" name="socials[' + idx + '][icon]"  placeholder="fa-brands fa-github" value="' + escapeAttr(data.icon) + '" class="input-icon">'
                +   '<input type="text" name="socials[' + idx + '][label]" placeholder="显示名(选填)" value="' + escapeAttr(data.label) + '" class="input-label">'
                +   '<button type="button" class="btn-icon btn-remove" title="删除" aria-label="删除"><i class="fa-solid fa-trash"></i></button>'
                + '</div>'
                + '<div class="social-row-preview">' + renderIcon(data.icon) + '</div>';
            wrap.querySelector('.btn-remove').addEventListener('click', function () {
                wrap.remove();
                reindex();
            });
            list.appendChild(wrap);
        }

        // 输入 icon 时实时预览
        list.addEventListener('input', function (e) {
            if (e.target.classList && e.target.classList.contains('input-icon')) {
                var row = e.target.closest('.social-row');
                var preview = row.querySelector('.social-row-preview');
                preview.innerHTML = renderIcon(e.target.value);
            }
        });

        function renderIcon(icon) {
            var safe = String(icon || '').trim();
            if (!safe) return '<span class="muted small">↑ 输入图标</span>';
            // 用户填了 <i>...</i> 直接显示
            if (safe.indexOf('<i') === 0) return safe;
            // 否则当 class 拼
            if (/^[a-zA-Z0-9 _\-]+$/.test(safe)) return '<i class="' + safe + '"></i>';
            return '<span class="text-danger" title="非法图标">⚠</span>';
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

    <h3 class="settings-group-title"><i class="fa-solid fa-key"></i> Passkey 登录</h3>
    <div class="settings-section">
        <p class="muted small">
            当前已绑定 {{ (int)($passkeyCount ?? 0) }} 个 Passkey。绑定后可在后台登录页直接使用系统 Passkey 登录。
        </p>
        <div class="form-row">
            <div class="form-group">
                <label>设备名称</label>
                <input type="text" id="passkeyDeviceName" value="我的设备" data-no-dirty>
            </div>
            <div class="form-group passkey-bind-action">
                <label>&nbsp;</label>
                <button type="button" class="btn btn-primary" id="passkeyRegisterBtn">
                    <i class="fa-solid fa-fingerprint"></i> 绑定 Passkey
                </button>
            </div>
        </div>
    </div>

    <script>
    (function () {
        var btn = document.getElementById('passkeyRegisterBtn');
        var nameInput = document.getElementById('passkeyDeviceName');
        if (!btn) return;
        btn.addEventListener('click', function () {
            var original = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> 绑定中';
            registerPasskey((nameInput && nameInput.value.trim()) || '我的设备')
                .then(function (res) {
                    window.adminToast && window.adminToast(res.message || 'Passkey 已绑定', 'success');
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

    <h3>修改密码</h3>
    <form method="post" action="/admin/profile/password" class="admin-form" data-dirty-watch>
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
@endsection
