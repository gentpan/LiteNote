(function () {
    'use strict';

    window.LiteNoteIdentityShell = {
        boot: function (options) {
            options = options || {};
            var prefix = options.prefix;
            if (!prefix) return;

            var IDENTITY_KEY = 'litenote_comment_identity';
            var clsDialog = prefix + '-identity-dialog';
            var clsPreview = prefix + '-id-preview';
            var clsCaptcha = prefix + '-id-captcha';
            var clsCaptchaImg = prefix + '-id-captcha-img';

            function frontCsrf() { return window.LiteNoteAuth.csrf(); }
            function gravatarUrl(email, size) { return window.LiteNoteAuth.gravatarUrl(email, size); }
            function grayGravatar(size) { return window.LiteNoteAuth.grayGravatar(size); }
            function loadIdentity() { try { var raw = localStorage.getItem(IDENTITY_KEY); return raw ? JSON.parse(raw) : null; } catch (e) { return null; } }
            function saveIdentity(identity) { try { localStorage.setItem(IDENTITY_KEY, JSON.stringify(identity)); } catch (e) {} }
            function clearIdentity() { try { localStorage.removeItem(IDENTITY_KEY); } catch (e) {} }

            // 免验证码白名单(与 ember 同一 localStorage 键,跨主题一致):
            // 白名单 = 有审核通过的评论,或已通过身份表单验证码;后端 /api/visitor/stats 返回 trusted 为准
            var TRUSTED_KEY = 'litenote_trusted_emails';
            function loadTrustedEmails() { try { var v = JSON.parse(localStorage.getItem(TRUSTED_KEY) || '[]'); return Array.isArray(v) ? v : []; } catch (e) { return []; } }
            function isEmailTrusted(email) { email = String(email || '').trim().toLowerCase(); return !!email && loadTrustedEmails().indexOf(email) !== -1; }
            function addTrustedEmail(email) {
                email = String(email || '').trim().toLowerCase();
                if (!email) return;
                var list = loadTrustedEmails();
                if (list.indexOf(email) === -1) { list.push(email); try { localStorage.setItem(TRUSTED_KEY, JSON.stringify(list)); } catch (e) {} }
            }

            function updateSideIdentity(identity) {
                var wrap = document.querySelector('[data-side-identity]');
                if (!wrap) return;
                var hasIdentity = !!(identity && (identity.nickname || identity.email));
                wrap.classList.toggle('has-identity', hasIdentity);
                var img = wrap.querySelector('[data-side-identity-avatar]');
                var nameEl = wrap.querySelector('[data-side-identity-name]');
                var statEl = wrap.querySelector('[data-side-identity-stat]');
                var avatar = identity && (identity.avatar_url || gravatarUrl(identity.email, 80));
                if (img) { if (avatar) { img.src = avatar; img.hidden = false; } else { img.removeAttribute('src'); img.hidden = true; } }
                if (nameEl) nameEl.textContent = (identity && identity.nickname) ? identity.nickname : '';
                if (!hasIdentity) { if (statEl) statEl.textContent = '设置评论身份，留下你的足迹'; return; }
                if (statEl && identity.email) {
                    statEl.textContent = '统计中…';
                    fetch('/api/visitor/stats?email=' + encodeURIComponent(identity.email), { credentials: 'same-origin' })
                        .then(function (r) { return r.json(); })
                        .then(function (d) { var n = (d && d.comments) || 0; statEl.textContent = n > 0 ? ('已留下 ' + n + ' 条评论 · 欢迎回来 👋') : '期待你的第一条评论'; })
                        .catch(function () { statEl.textContent = '欢迎回来 👋'; });
                } else if (statEl) { statEl.textContent = '欢迎回来 👋'; }
            }

            var identitySaveCb = null;
            function openIdentityDialog(options) {
                options = options || {};
                identitySaveCb = typeof options.onSave === 'function' ? options.onSave : null;
                var identity = loadIdentity() || {};
                var dialog = document.querySelector('.' + clsDialog);
                if (!dialog) {
                    dialog = document.createElement('div');
                    dialog.className = clsDialog + ' login-overlay';
                    dialog.innerHTML = '<div class="login-modal"><button type="button" class="login-modal-close" data-id-close aria-label="关闭"><i class="fa-solid fa-circle-xmark" aria-hidden="true"></i></button><div class="login-modal-head"><img class="' + clsPreview + ' login-modal-avatar" alt=""><div><p class="login-modal-title">评论身份</p><p class="login-modal-subtitle">保存后评论会自动使用这份资料</p></div></div><form class="login-modal-form" data-id-form><label class="login-modal-field"><i class="fa-regular fa-circle-user" aria-hidden="true"></i><input name="nickname" placeholder="昵称 *" required></label><label class="login-modal-field"><i class="fa-regular fa-envelope" aria-hidden="true"></i><input name="email" type="email" placeholder="邮箱 *" required></label><label class="login-modal-field ' + clsCaptcha + '" data-id-captcha hidden><i class="fa-solid fa-shield-halved" aria-hidden="true"></i><input name="captcha" placeholder="验证码 *" autocomplete="off" maxlength="4"><img class="' + clsCaptchaImg + '" data-id-captcha-img src="" alt="点击刷新验证码" title="看不清?点击刷新"></label><label class="login-modal-field"><i class="fa-solid fa-link" aria-hidden="true"></i><input name="website" placeholder="网站(选填)"></label><button type="submit" class="login-modal-submit">保存</button><button type="button" class="login-modal-passkey" data-id-clear>清除身份</button></form></div>';
                    document.body.appendChild(dialog);
                    dialog.addEventListener('click', function (e) { if (e.target === dialog) closeIdentityDialog(); });
                    dialog.querySelector('[data-id-close]').addEventListener('click', closeIdentityDialog);
                    dialog.querySelector('[data-id-clear]').addEventListener('click', function () { clearIdentity(); updateSideIdentity(null); applyIdentityToForms(null); closeIdentityDialog(); });
                    dialog.querySelector('[data-id-captcha-img]').addEventListener('click', function () { this.src = '/captcha?t=' + Date.now(); });
                    dialog.querySelector('[name=email]').addEventListener('input', function (e) {
                        dialog.querySelector('.' + clsPreview).src = gravatarUrl(e.target.value, 80) || grayGravatar(80);
                        refreshIdentityCaptcha(dialog, e.target.value);
                    });
                    dialog.querySelector('[data-id-form]').addEventListener('submit', function (e) {
                        e.preventDefault();
                        var f = e.currentTarget;
                        var next = { nickname: f.nickname.value.trim(), email: f.email.value.trim(), website: f.website.value.trim() };
                        if (!next.nickname && !next.email) { closeIdentityDialog(); return; }
                        var finishSave = function () {
                            next.avatar_url = gravatarUrl(next.email, 80);
                            addTrustedEmail(next.email);
                            saveIdentity(next); updateSideIdentity(next); applyIdentityToForms(next);
                            var cb = identitySaveCb; identitySaveCb = null;
                            closeIdentityDialog();
                            if (cb) cb(next);
                        };
                        var capWrap = dialog.querySelector('[data-id-captcha]');
                        if (!capWrap || capWrap.hidden) { finishSave(); return; }
                        // 非白名单邮箱:验证码交后端校验,通过后该邮箱进白名单(任何设备生效)
                        var capVal = (f.captcha.value || '').trim();
                        if (capVal.length < 4) { f.captcha.focus(); return; }
                        var saveBtn = f.querySelector('.login-modal-submit');
                        if (saveBtn) saveBtn.disabled = true;
                        var body = new FormData();
                        body.append('email', next.email);
                        body.append('captcha', capVal);
                        body.append('_csrf', frontCsrf());
                        fetch('/comment/verify-identity', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': frontCsrf() }, body: body, credentials: 'same-origin' })
                            .then(function (r) { return r.json(); })
                            .then(function (d) {
                                if (saveBtn) saveBtn.disabled = false;
                                if (d && d.ok) { finishSave(); return; }
                                var img = dialog.querySelector('[data-id-captcha-img]');
                                if (img) img.src = '/captcha?t=' + Date.now();
                                f.captcha.value = '';
                                f.captcha.placeholder = (d && d.msg) || '验证码错误，请重新输入';
                                f.captcha.focus();
                            })
                            .catch(function () { if (saveBtn) saveBtn.disabled = false; });
                    });
                }
                dialog.querySelector('[name=nickname]').value = identity.nickname || '';
                dialog.querySelector('[name=email]').value = identity.email || '';
                dialog.querySelector('[name=website]').value = identity.website || '';
                var capInput = dialog.querySelector('[name=captcha]');
                if (capInput) { capInput.value = ''; capInput.placeholder = '验证码 *'; }
                dialog.querySelector('.' + clsPreview).src = identity.avatar_url || gravatarUrl(identity.email, 80) || grayGravatar(80);
                refreshIdentityCaptcha(dialog, identity.email || '');
                dialog.hidden = false; dialog.classList.add('is-open');
                document.body.classList.add('login-modal-open');
            }

            // 验证码显隐(与 ember 同策略):默认显示;白名单邮箱(本地或后端确认)隐藏;管理员不走此弹窗
            function refreshIdentityCaptcha(dialog, email) {
                var wrap = dialog.querySelector('[data-id-captcha]');
                if (!wrap) return;
                email = String(email || '').trim();
                function show(visible) {
                    var wasHidden = wrap.hidden;
                    wrap.hidden = !visible;
                    if (visible) {
                        var img = dialog.querySelector('[data-id-captcha-img]');
                        if (img && (wasHidden || !img.getAttribute('src'))) img.src = '/captcha?t=' + Date.now();
                    }
                }
                if (email && email.indexOf('@') !== -1 && isEmailTrusted(email)) { show(false); return; }
                show(true);
                if (!email || email.indexOf('@') === -1) return;
                fetch('/api/visitor/stats?email=' + encodeURIComponent(email), { credentials: 'same-origin' })
                    .then(function (r) { return r.json(); })
                    .then(function (d) {
                        if (d && d.trusted) {
                            addTrustedEmail(email);
                            var cur = dialog.querySelector('[name=email]');
                            if (cur && (cur.value || '').trim() === email) show(false);
                        }
                    })
                    .catch(function () {});
            }
            function closeIdentityDialog() {
                identitySaveCb = null;
                var d = document.querySelector('.' + clsDialog);
                if (d) { d.hidden = true; d.classList.remove('is-open'); }
                document.body.classList.remove('login-modal-open');
            }

            function hasUsableIdentity() {
                var id = loadIdentity();
                return !!(id && id.nickname && id.email);
            }
            function applyIdentityToForms(identity) {
                document.querySelectorAll('.comment-form').forEach(function (form) {
                    if (form.dataset.commentAdmin === '1') return;
                    var n = form.querySelector('[name=nickname]'), em = form.querySelector('[name=email]'), w = form.querySelector('[name=website]');
                    if (n) n.value = (identity && identity.nickname) || '';
                    if (em) em.value = (identity && identity.email) || '';
                    if (w) w.value = (identity && identity.website) || '';
                });
            }
            function bindCommentForms() {
                document.querySelectorAll('.comment-form').forEach(function (form) {
                    if (form.dataset.lnCommentBound) return; form.dataset.lnCommentBound = '1';
                    if (form.dataset.commentAdmin === '1') return;
                    var ta = form.querySelector('[name=content]');
                    if (ta) {
                        ta.addEventListener('focus', function () {
                            if (hasUsableIdentity()) return;
                            openIdentityDialog({ onSave: function () { try { ta.focus(); } catch (e) {} } });
                        });
                    }
                    // 原生提交前:存身份;非白名单邮箱(没验证过验证码)先弹身份弹窗,验证通过后再真正提交
                    form.addEventListener('submit', function (e) {
                        var n = form.querySelector('[name=nickname]'), em = form.querySelector('[name=email]'), w = form.querySelector('[name=website]');
                        var id = { nickname: n ? n.value.trim() : '', email: em ? em.value.trim() : '', website: w ? w.value.trim() : '' };
                        if (id.nickname || id.email) { id.avatar_url = gravatarUrl(id.email, 80); saveIdentity(id); }
                        if (form.dataset.lnCaptchaPass === '1') { form.dataset.lnCaptchaPass = ''; return; }
                        if (!isEmailTrusted(id.email)) {
                            e.preventDefault();
                            openIdentityDialog({ onSave: function () {
                                form.dataset.lnCaptchaPass = '1';
                                if (form.requestSubmit) form.requestSubmit();
                                else form.submit();
                            } });
                        }
                    });
                });
                // 滚动到评论区,访客无身份时自动弹一次身份表单
                var section = document.querySelector('.comment-form');
                if (section && !section.dataset.lnPromptBound && section.dataset.commentAdmin !== '1') {
                    section.dataset.lnPromptBound = '1';
                    if (!hasUsableIdentity() && 'IntersectionObserver' in window) {
                        var ob = new IntersectionObserver(function (entries) {
                            entries.forEach(function (en) {
                                if (!en.isIntersecting) return;
                                ob.disconnect();
                                if (!hasUsableIdentity()) openIdentityDialog();
                            });
                        }, { rootMargin: '0px 0px -20% 0px', threshold: 0.16 });
                        ob.observe(section);
                    }
                }
            }

            function loginCsrf() { return window.LiteNoteAuth.loginCsrf(); }
            function loginWithPasskey() { return window.LiteNoteAuth.loginWithPasskey(); }
            function loginOverlay() { return document.querySelector('[data-login-overlay]'); }
            function loginErr(m) { var e = document.querySelector('[data-login-error]'); if (e) { e.textContent = m || ''; e.hidden = !m; } }
            function openLogin() { var o = loginOverlay(); if (!o) return; o.hidden = false; document.body.classList.add('login-modal-open'); var u = o.querySelector('[name=username]'); if (u) setTimeout(function () { try { u.focus(); } catch (e) {} }, 60); }
            function closeLogin() { var o = loginOverlay(); if (o) { o.hidden = true; document.body.classList.remove('login-modal-open'); loginErr(''); } }

            document.addEventListener('click', function (e) {
                var t = e.target;
                if (t.closest('[data-login-open]')) { e.preventDefault(); openLogin(); return; }
                if (t.closest('[data-login-close]')) { e.preventDefault(); closeLogin(); return; }
                if (t.closest('[data-identity-open]')) { e.preventDefault(); openIdentityDialog(); return; }
                var o = loginOverlay(); if (o && !o.hidden && t === o) closeLogin();
            });
            document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { closeLogin(); closeIdentityDialog(); } });

            // footer 注入,脚本运行时元素已存在,直接初始化
            updateSideIdentity(loadIdentity());
            applyIdentityToForms(loadIdentity());
            bindCommentForms();
            var loginForm = document.querySelector('[data-login-form]');
            if (loginForm) {
                loginForm.addEventListener('submit', function (e) {
                    e.preventDefault(); loginErr('');
                    var btn = loginForm.querySelector('.login-modal-submit'); if (btn) btn.disabled = true;
                    var body = new URLSearchParams();
                    body.set('_csrf', loginCsrf()); body.set('username', (loginForm.username.value || '').trim()); body.set('password', loginForm.password.value || '');
                    fetch('/admin/login', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' }, credentials: 'same-origin', body: body.toString() })
                        .then(function (res) { return res.json().catch(function () { return {}; }).then(function (d) { return { ok: res.ok, data: d }; }); })
                        .then(function (r) { if (r.ok && r.data && r.data.ok) { window.location.href = r.data.redirect || '/admin'; } else { loginErr((r.data && r.data.message) || '用户名或密码错误'); if (btn) btn.disabled = false; } })
                        .catch(function (err) { loginErr('登录失败：' + err.message); if (btn) btn.disabled = false; });
                });
                var pk = document.querySelector('[data-login-passkey]');
                if (pk) pk.addEventListener('click', function () { loginErr(''); loginWithPasskey().then(function (r) { if (r && r.success !== false) window.location.href = '/admin'; else loginErr((r && r.message) || 'Passkey 登录失败'); }).catch(function (err) { loginErr('Passkey 登录失败：' + err.message); }); });
            }
            try {
                var params = new URLSearchParams(window.location.search || '');
                if (params.get('login') === '1') {
                    openLogin();
                    if (params.get('password_changed') === '1') {
                        loginErr('密码已修改，请重新登录');
                    }
                }
            } catch (e) {}

            document.querySelectorAll('.comment-load-more').forEach(function(btn) {
                if (btn.dataset.lnBound) return;
                btn.dataset.lnBound = '1';
                var section = btn.closest('.comments');
                if (!section) return;
                var ul = section.querySelector('.comment-list');
                if (!ul) return;
                var busy = false;
                btn.addEventListener('click', function() {
                    if (busy) return;
                    var postId = btn.dataset.postId;
                    var offset = parseInt(btn.dataset.offset, 10) || 0;
                    var limit = parseInt(btn.dataset.limit, 10) || 50;
                    if (!postId) return;
                    busy = true;
                    btn.disabled = true;
                    var originalText = btn.textContent;
                    btn.textContent = '加载中...';
                    fetch('/post/' + encodeURIComponent(postId) + '/comments?offset=' + offset + '&limit=' + limit, {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    })
                        .then(function(r) { return r.json(); })
                        .then(function(data) {
                            if (data && data.code === 0 && data.html) {
                                var wrap = document.createElement('div');
                                wrap.innerHTML = data.html;
                                Array.prototype.slice.call(wrap.children).forEach(function(node) {
                                    ul.appendChild(node);
                                });
                                if (data.hasMore) {
                                    btn.dataset.offset = String(data.nextOffset || (offset + (data.count || 0)));
                                    btn.disabled = false;
                                    btn.textContent = originalText;
                                } else if (btn.parentNode) {
                                    btn.parentNode.removeChild(btn);
                                }
                            } else {
                                btn.disabled = false;
                                btn.textContent = originalText;
                            }
                        })
                        .catch(function() {
                            btn.disabled = false;
                            btn.textContent = originalText;
                        })
                        .finally(function() { busy = false; });
                });
            });
        }
    };
})();
