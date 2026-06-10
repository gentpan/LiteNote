(function() {
    'use strict';

    var root = document.documentElement;
    var themeKey = 'litenote-theme';

    function preferredMode() {
        try {
            var saved = localStorage.getItem(themeKey);
            if (saved === 'light' || saved === 'dark') {
                return saved;
            }
        } catch (e) {}
        return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }

    function setMode(mode, persist) {
        mode = mode === 'dark' ? 'dark' : 'light';
        root.setAttribute('data-theme', mode);
        document.querySelectorAll('[data-theme-toggle]').forEach(function(button) {
            var next = mode === 'dark' ? '\u{6D45}\u{8272}\u{6A21}\u{5F0F}' : '\u{6DF1}\u{8272}\u{6A21}\u{5F0F}';
            button.setAttribute('aria-pressed', mode === 'dark' ? 'true' : 'false');
            button.setAttribute('aria-label', next);
            button.setAttribute('title', next);
            var label = button.querySelector('[data-theme-label]');
            if (label) {
                label.textContent = next;
            }
        });
        if (persist) {
            try { localStorage.setItem(themeKey, mode); } catch (e) {}
        }
    }

    setMode(preferredMode(), false);

    document.addEventListener('click', function(event) {
        var themeToggle = event.target.closest ? event.target.closest('[data-theme-toggle]') : null;
        if (themeToggle) {
            setMode(root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark', true);
            return;
        }

        var copyButton = event.target.closest ? event.target.closest('[data-copy-url]') : null;
        if (copyButton) {
            var value = copyButton.getAttribute('data-copy-url') || location.href;
            var url = value.charAt(0) === '/' ? location.origin + value : value;
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(url).then(function() {
                    toast('\u{5DF2}\u{590D}\u{5236}');
                }).catch(function() {
                    toast(url);
                });
            } else {
                toast(url);
            }
            return;
        }

        var replyButton = event.target.closest ? event.target.closest('.comment-reply-btn') : null;
        if (replyButton) {
            var form = document.querySelector('.comment-form');
            if (!form) return;
            var parent = form.querySelector('[name=parent_id]');
            var textarea = form.querySelector('textarea[name=content]');
            if (parent) {
                parent.value = replyButton.getAttribute('data-parent-id') || '0';
            }
            if (textarea) {
                var nickname = replyButton.getAttribute('data-nickname') || '';
                textarea.placeholder = nickname ? '\u{56DE}\u{590D} ' + nickname + '...' : '\u{5199}\u{8BC4}\u{8BBA}...';
                textarea.focus();
            }
        }
    });

    document.querySelectorAll('a[href^="#"]').forEach(function(link) {
        link.addEventListener('click', function(event) {
            var target = document.querySelector(link.getAttribute('href'));
            if (!target) return;
            event.preventDefault();
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });

    document.querySelectorAll('[data-toast-message]').forEach(function(node) {
        toast(node.getAttribute('data-toast-message') || '');
    });

    function runWhenIdle(fn) {
        if ('requestIdleCallback' in window) {
            window.requestIdleCallback(fn, { timeout: 900 });
            return;
        }
        window.setTimeout(function() {
            fn({ timeRemaining: function() { return 16; }, didTimeout: true });
        }, 80);
    }

    function bindDeferredFavicons() {
        var queue = Array.prototype.slice.call(document.querySelectorAll('img[data-favicon-src]')).filter(function(img) {
            if (img.dataset.faviconDeferredReady === '1') {
                return false;
            }
            img.dataset.faviconDeferredReady = '1';
            return true;
        });
        if (!queue.length) return;

        function loadIcon(img) {
            var src = img.getAttribute('data-favicon-src');
            if (!src || !img.isConnected) return;
            var probe = new Image();
            probe.decoding = 'async';
            probe.referrerPolicy = 'no-referrer';
            probe.onload = function() {
                img.src = src;
                img.classList.remove('is-deferred');
                img.classList.add('is-loaded');
                img.removeAttribute('data-favicon-src');
            };
            probe.onerror = function() {
                img.classList.remove('is-deferred');
                img.classList.add('is-failed');
                img.removeAttribute('data-favicon-src');
            };
            probe.src = src;
        }

        function pump(deadline) {
            var budget = deadline && typeof deadline.timeRemaining === 'function' ? deadline.timeRemaining() : 16;
            while (queue.length && (budget > 4 || (deadline && deadline.didTimeout))) {
                loadIcon(queue.shift());
                budget = deadline && typeof deadline.timeRemaining === 'function' ? deadline.timeRemaining() : budget - 4;
            }
            if (queue.length) runWhenIdle(pump);
        }

        runWhenIdle(pump);
    }
    bindDeferredFavicons();

    function toast(message) {
        if (!message) return;
        var item = document.createElement('div');
        item.className = 'kami-toast';
        item.textContent = message;
        document.body.appendChild(item);
        requestAnimationFrame(function() {
            item.classList.add('is-visible');
        });
        setTimeout(function() {
            item.classList.remove('is-visible');
            setTimeout(function() { item.remove(); }, 180);
        }, 1800);
    }
})();

/* kami: 访客身份卡 + 进入后台/登录 + 登录 dialog(密码+Passkey) —— 与 ember 同套,自含 md5/gravatar */
(function () {
    'use strict';
    var IDENTITY_KEY = 'litenote_comment_identity';

    function md5(input) {
        function cmn(q, a, b, x, s, t) { a = add32(add32(a, q), add32(x, t)); return add32((a << s) | (a >>> (32 - s)), b); }
        function ff(a, b, c, d, x, s, t) { return cmn((b & c) | ((~b) & d), a, b, x, s, t); }
        function gg(a, b, c, d, x, s, t) { return cmn((b & d) | (c & (~d)), a, b, x, s, t); }
        function hh(a, b, c, d, x, s, t) { return cmn(b ^ c ^ d, a, b, x, s, t); }
        function ii(a, b, c, d, x, s, t) { return cmn(c ^ (b | (~d)), a, b, x, s, t); }
        function add32(a, b) { return (a + b) & 0xffffffff; }
        function md5blk(s) { var blocks = [], i; for (i = 0; i < 64; i += 4) { blocks[i >> 2] = s.charCodeAt(i) + (s.charCodeAt(i + 1) << 8) + (s.charCodeAt(i + 2) << 16) + (s.charCodeAt(i + 3) << 24); } return blocks; }
        function md5cycle(x, k) {
            var a = x[0], b = x[1], c = x[2], d = x[3];
            a = ff(a, b, c, d, k[0], 7, -680876936); d = ff(d, a, b, c, k[1], 12, -389564586);
            c = ff(c, d, a, b, k[2], 17, 606105819); b = ff(b, c, d, a, k[3], 22, -1044525330);
            a = ff(a, b, c, d, k[4], 7, -176418897); d = ff(d, a, b, c, k[5], 12, 1200080426);
            c = ff(c, d, a, b, k[6], 17, -1473231341); b = ff(b, c, d, a, k[7], 22, -45705983);
            a = ff(a, b, c, d, k[8], 7, 1770035416); d = ff(d, a, b, c, k[9], 12, -1958414417);
            c = ff(c, d, a, b, k[10], 17, -42063); b = ff(b, c, d, a, k[11], 22, -1990404162);
            a = ff(a, b, c, d, k[12], 7, 1804603682); d = ff(d, a, b, c, k[13], 12, -40341101);
            c = ff(c, d, a, b, k[14], 17, -1502002290); b = ff(b, c, d, a, k[15], 22, 1236535329);
            a = gg(a, b, c, d, k[1], 5, -165796510); d = gg(d, a, b, c, k[6], 9, -1069501632);
            c = gg(c, d, a, b, k[11], 14, 643717713); b = gg(b, c, d, a, k[0], 20, -373897302);
            a = gg(a, b, c, d, k[5], 5, -701558691); d = gg(d, a, b, c, k[10], 9, 38016083);
            c = gg(c, d, a, b, k[15], 14, -660478335); b = gg(b, c, d, a, k[4], 20, -405537848);
            a = gg(a, b, c, d, k[9], 5, 568446438); d = gg(d, a, b, c, k[14], 9, -1019803690);
            c = gg(c, d, a, b, k[3], 14, -187363961); b = gg(b, c, d, a, k[8], 20, 1163531501);
            a = gg(a, b, c, d, k[13], 5, -1444681467); d = gg(d, a, b, c, k[2], 9, -51403784);
            c = gg(c, d, a, b, k[7], 14, 1735328473); b = gg(b, c, d, a, k[12], 20, -1926607734);
            a = hh(a, b, c, d, k[5], 4, -378558); d = hh(d, a, b, c, k[8], 11, -2022574463);
            c = hh(c, d, a, b, k[11], 16, 1839030562); b = hh(b, c, d, a, k[14], 23, -35309556);
            a = hh(a, b, c, d, k[1], 4, -1530992060); d = hh(d, a, b, c, k[4], 11, 1272893353);
            c = hh(c, d, a, b, k[7], 16, -155497632); b = hh(b, c, d, a, k[10], 23, -1094730640);
            a = hh(a, b, c, d, k[13], 4, 681279174); d = hh(d, a, b, c, k[0], 11, -358537222);
            c = hh(c, d, a, b, k[3], 16, -722521979); b = hh(b, c, d, a, k[6], 23, 76029189);
            a = hh(a, b, c, d, k[9], 4, -640364487); d = hh(d, a, b, c, k[12], 11, -421815835);
            c = hh(c, d, a, b, k[15], 16, 530742520); b = hh(b, c, d, a, k[2], 23, -995338651);
            a = ii(a, b, c, d, k[0], 6, -198630844); d = ii(d, a, b, c, k[7], 10, 1126891415);
            c = ii(c, d, a, b, k[14], 15, -1416354905); b = ii(b, c, d, a, k[5], 21, -57434055);
            a = ii(a, b, c, d, k[12], 6, 1700485571); d = ii(d, a, b, c, k[3], 10, -1894986606);
            c = ii(c, d, a, b, k[10], 15, -1051523); b = ii(b, c, d, a, k[1], 21, -2054922799);
            a = ii(a, b, c, d, k[8], 6, 1873313359); d = ii(d, a, b, c, k[15], 10, -30611744);
            c = ii(c, d, a, b, k[6], 15, -1560198380); b = ii(b, c, d, a, k[13], 21, 1309151649);
            a = ii(a, b, c, d, k[4], 6, -145523070); d = ii(d, a, b, c, k[11], 10, -1120210379);
            c = ii(c, d, a, b, k[2], 15, 718787259); b = ii(b, c, d, a, k[9], 21, -343485551);
            x[0] = add32(a, x[0]); x[1] = add32(b, x[1]); x[2] = add32(c, x[2]); x[3] = add32(d, x[3]);
        }
        function md51(s) {
            s = unescape(encodeURIComponent(s));
            var n = s.length, state = [1732584193, -271733879, -1732584194, 271733878], i, tail = new Array(16).fill(0);
            for (i = 64; i <= n; i += 64) md5cycle(state, md5blk(s.substring(i - 64, i)));
            s = s.substring(i - 64);
            for (i = 0; i < s.length; i++) tail[i >> 2] |= s.charCodeAt(i) << ((i % 4) << 3);
            tail[i >> 2] |= 0x80 << ((i % 4) << 3);
            if (i > 55) { md5cycle(state, tail); tail = new Array(16).fill(0); }
            tail[14] = n * 8;
            md5cycle(state, tail);
            return state;
        }
        function hex(x) { var out = '', i, j; for (i = 0; i < x.length; i++) for (j = 0; j < 4; j++) out += ('0' + ((x[i] >> (j * 8 + 4)) & 15).toString(16)).slice(-1) + ('0' + ((x[i] >> (j * 8)) & 15).toString(16)).slice(-1); return out; }
        return hex(md51(input));
    }

    function gravatarUrl(email, size) {
        email = String(email || '').trim().toLowerCase();
        if (!email) return '';
        return 'https://gravatar.bluecdn.com/avatar/' + md5(email) + '?s=' + (size || 80) + '&d=identicon&r=g&v=1.3';
    }
    // 无邮箱时的灰色默认头像(gravatar mystery-person),不回退到博主头像
    function grayGravatar(size) {
        return 'https://gravatar.bluecdn.com/avatar/00000000000000000000000000000000?s=' + (size || 80) + '&d=mp&r=g&v=1.3';
    }
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
        var dialog = document.querySelector('.kami-identity-dialog');
        if (!dialog) {
            dialog = document.createElement('div');
            dialog.className = 'kami-identity-dialog login-overlay';
            dialog.innerHTML = '<div class="login-modal"><button type="button" class="login-modal-close" data-id-close aria-label="关闭"><i class="fa-solid fa-xmark"></i></button><div class="login-modal-head"><img class="kami-id-preview login-modal-avatar" alt=""><div><p class="login-modal-title">评论身份</p><p class="login-modal-subtitle">保存后评论会自动使用这份资料</p></div></div><form class="login-modal-form" data-id-form><label class="login-modal-field"><i class="fa-regular fa-user"></i><input name="nickname" placeholder="昵称 *" required></label><label class="login-modal-field"><i class="fa-regular fa-envelope"></i><input name="email" type="email" placeholder="邮箱 *" required></label><label class="login-modal-field kami-id-captcha" data-id-captcha hidden><i class="fa-solid fa-shield-halved"></i><input name="captcha" placeholder="验证码 *" autocomplete="off" maxlength="4"><img class="kami-id-captcha-img" data-id-captcha-img src="" alt="点击刷新验证码" title="看不清?点击刷新"></label><label class="login-modal-field"><i class="fa-solid fa-link"></i><input name="website" placeholder="网站(选填)"></label><button type="submit" class="login-modal-submit">保存</button><button type="button" class="login-modal-passkey" data-id-clear>清除身份</button></form></div>';
            document.body.appendChild(dialog);
            dialog.addEventListener('click', function (e) { if (e.target === dialog) closeIdentityDialog(); });
            dialog.querySelector('[data-id-close]').addEventListener('click', closeIdentityDialog);
            dialog.querySelector('[data-id-clear]').addEventListener('click', function () { clearIdentity(); updateSideIdentity(null); applyIdentityToForms(null); closeIdentityDialog(); });
            dialog.querySelector('[data-id-captcha-img]').addEventListener('click', function () { this.src = '/captcha?t=' + Date.now(); });
            dialog.querySelector('[name=email]').addEventListener('input', function (e) {
                dialog.querySelector('.kami-id-preview').src = gravatarUrl(e.target.value, 80) || grayGravatar(80);
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
                fetch('/comment/verify-identity', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: body, credentials: 'same-origin' })
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
        dialog.querySelector('.kami-id-preview').src = identity.avatar_url || gravatarUrl(identity.email, 80) || grayGravatar(80);
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
        var d = document.querySelector('.kami-identity-dialog');
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

    function loginCsrf() { var f = document.querySelector('[data-login-form] input[name=_csrf]') || document.querySelector('input[name=_csrf]'); return f ? f.value : ''; }
    function b64urlToBytes(v) { v = String(v || '').replace(/-/g, '+').replace(/_/g, '/'); while (v.length % 4) v += '='; return Uint8Array.from(atob(v), function (c) { return c.charCodeAt(0); }); }
    function bytesToB64url(b) { var a = new Uint8Array(b), s = ''; for (var i = 0; i < a.length; i++) s += String.fromCharCode(a[i]); return btoa(s).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/g, ''); }
    function pkJson(res) { var t = res.headers.get('content-type') || ''; if (t.indexOf('application/json') === -1) { return res.text().then(function () { throw new Error('Passkey 接口返回非 JSON'); }); } return res.json().then(function (d) { if (!res.ok || d.success === false) throw new Error(d.message || d.error || 'Passkey 请求失败'); return d; }); }
    function loginWithPasskey() {
        if (!window.PublicKeyCredential || !navigator.credentials) return Promise.reject(new Error('当前浏览器不支持 Passkey'));
        return fetch('/admin/passkey/login-options', { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
            .then(pkJson).then(function (options) {
                var allow = (options.allowCredentials || []).map(function (it) { return { type: it.type || 'public-key', id: b64urlToBytes(it.id) }; });
                return navigator.credentials.get({ publicKey: { challenge: b64urlToBytes(options.challenge), timeout: options.timeout, rpId: options.rpId, allowCredentials: allow, userVerification: options.userVerification || 'preferred' } });
            }).then(function (assertion) {
                var data = { id: assertion.id, rawId: bytesToB64url(assertion.rawId), response: { clientDataJSON: bytesToB64url(assertion.response.clientDataJSON), authenticatorData: bytesToB64url(assertion.response.authenticatorData), signature: bytesToB64url(assertion.response.signature), userHandle: assertion.response.userHandle ? bytesToB64url(assertion.response.userHandle) : '' } };
                return fetch('/admin/passkey/login', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': loginCsrf(), 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin', body: JSON.stringify({ credential: JSON.stringify(data) }) }).then(pkJson);
            });
    }
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
})();
