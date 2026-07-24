// LiteNote front theme interactions.
(function() {
    'use strict';

    function frontCsrf() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        if (meta && meta.content) return meta.content;
        var field = document.querySelector('input[name="_csrf"]');
        return field ? field.value : '';
    }

    function frontAjaxHeaders() {
        return { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': frontCsrf() };
    }

    function siteLoadingSpinnerSvg(extraClass) {
        var cls = 'site-loading-spinner' + (extraClass ? ' ' + extraClass : '');
        return '<span class="' + cls + '" aria-hidden="true"></span>';
    }
    window.siteLoadingSpinnerSvg = siteLoadingSpinnerSvg;

    // Front theme switcher: user choice wins; otherwise follow the system.
    (function() {
        var storageKey = 'litenote-theme';
        var media = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;

        function systemTheme() {
            return media && media.matches ? 'dark' : 'light';
        }

        function savedTheme() {
            try {
                var value = localStorage.getItem(storageKey);
                return value === 'dark' || value === 'light' ? value : '';
            } catch (e) {
                return '';
            }
        }

        function setTheme(theme, persist) {
            document.documentElement.setAttribute('data-theme', theme);
            document.querySelectorAll('[data-theme-toggle]').forEach(function(button) {
                var isDark = theme === 'dark';
                button.setAttribute('aria-pressed', isDark ? 'true' : 'false');
                button.setAttribute('aria-label', isDark ? '切换浅色模式' : '切换深色模式');
                button.setAttribute('title', isDark ? '切换浅色模式' : '切换深色模式');
                var label = button.querySelector('[data-theme-label]');
                if (label) {
                    label.textContent = isDark ? '浅色模式' : '深色模式';
                }
            });

            if (persist) {
                try { localStorage.setItem(storageKey, theme); } catch (e) {}
            }
        }

        function currentTheme() {
            return document.documentElement.getAttribute('data-theme') || savedTheme() || systemTheme();
        }

        setTheme(savedTheme() || currentTheme(), false);

        document.addEventListener('click', function(event) {
            var button = event.target.closest ? event.target.closest('[data-theme-toggle]') : null;
            if (!button) return;
            setTheme(currentTheme() === 'dark' ? 'light' : 'dark', true);
        });

        if (media && media.addEventListener) {
            media.addEventListener('change', function() {
                if (!savedTheme()) {
                    setTheme(systemTheme(), false);
                }
            });
        }
    })();

    (function() {
        function overlay() {
            return document.querySelector('[data-search-overlay]');
        }

        function openSearch() {
            var panel = overlay();
            if (!panel) return;
            panel.hidden = false;
            document.body.classList.add('site-search-open');
            window.setTimeout(function() {
                var input = panel.querySelector('[data-search-input]');
                if (input) {
                    input.focus();
                    input.select();
                }
            }, 30);
        }

        function closeSearch() {
            var panel = overlay();
            if (!panel) return;
            panel.hidden = true;
            document.body.classList.remove('site-search-open');
        }

        document.addEventListener('click', function(event) {
            var target = event.target;
            if (!target || !target.closest) return;

            if (target.closest('[data-search-toggle]')) {
                event.preventDefault();
                openSearch();
                return;
            }

            if (target.closest('[data-search-close]')) {
                event.preventDefault();
                closeSearch();
                return;
            }

            if (document.body.classList.contains('site-search-open') && !target.closest('[data-search-overlay]')) {
                closeSearch();
                return;
            }

            var panel = target.closest('[data-search-overlay]');
            if (panel && target === panel) {
                closeSearch();
            }
        });

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeSearch();
                return;
            }

            if ((event.metaKey || event.ctrlKey) && String(event.key).toLowerCase() === 'k') {
                event.preventDefault();
                openSearch();
            }
        });
    })();

    (function() {
        var dock = document.querySelector('.side-quick-actions');
        var rail = document.querySelector('.side-quick-rail');
        if (!dock || !window.PointerEvent) return;

        var pointerId = null;
        var startX = 0;
        var startY = 0;
        var dockX = 0;
        var dockY = 0;
        var dragging = false;
        var suppressClick = false;
        var dragThreshold = 6;
        var edgeGap = 8;

        function clamp(value, min, max) {
            return Math.min(Math.max(value, min), max);
        }

        function viewport() {
            return {
                width: window.innerWidth || document.documentElement.clientWidth || 0,
                height: window.innerHeight || document.documentElement.clientHeight || 0
            };
        }

        function promoteDockToFixed() {
            if (dock.style.position === 'fixed') return;
            var rect = dock.getBoundingClientRect();
            dock.style.position = 'fixed';
            dock.style.left = rect.left + 'px';
            dock.style.top = rect.top + 'px';
            dock.style.right = 'auto';
            dock.style.transform = 'none';
            // 拖出后 rail 不再占命中列,避免右侧留下透明拦截层
            if (rail) {
                rail.style.pointerEvents = 'none';
                rail.style.width = '0';
                rail.style.paddingRight = '0';
            }
        }

        function clampDockPosition() {
            if (!dock.style.left || !dock.style.top) return;
            var rect = dock.getBoundingClientRect();
            var vp = viewport();
            var nextX = clamp(rect.left, edgeGap, Math.max(edgeGap, vp.width - rect.width - edgeGap));
            var nextY = clamp(rect.top, edgeGap, Math.max(edgeGap, vp.height - rect.height - edgeGap));
            dock.style.left = nextX + 'px';
            dock.style.top = nextY + 'px';
            dock.style.right = 'auto';
            dock.style.transform = 'none';
        }

        function capturePointer(id) {
            if (!dock.setPointerCapture) return;
            try { dock.setPointerCapture(id); } catch (e) {}
        }

        function releasePointer(id) {
            if (!dock.releasePointerCapture) return;
            try { dock.releasePointerCapture(id); } catch (e) {}
        }

        function resetDragState() {
            if (pointerId !== null) {
                releasePointer(pointerId);
            }
            pointerId = null;
            dragging = false;
            dock.classList.remove('is-dragging');
        }

        dock.addEventListener('pointerdown', function(event) {
            if (event.button !== undefined && event.button !== 0) return;
            pointerId = event.pointerId;
            startX = event.clientX;
            startY = event.clientY;
            var rect = dock.getBoundingClientRect();
            dockX = rect.left;
            dockY = rect.top;
            dragging = false;
            suppressClick = false;
        });

        dock.addEventListener('pointermove', function(event) {
            if (pointerId !== event.pointerId) return;
            if (event.pointerType === 'mouse' && event.buttons !== 1) {
                resetDragState();
                return;
            }

            var dx = event.clientX - startX;
            var dy = event.clientY - startY;
            if (!dragging && Math.hypot(dx, dy) < dragThreshold) return;

            if (!dragging) {
                promoteDockToFixed();
                var rect0 = dock.getBoundingClientRect();
                dockX = rect0.left - dx;
                dockY = rect0.top - dy;
                capturePointer(pointerId);
            }

            dragging = true;
            suppressClick = true;
            dock.classList.add('is-dragging');

            var rect = dock.getBoundingClientRect();
            var vp = viewport();
            var nextX = clamp(dockX + dx, edgeGap, Math.max(edgeGap, vp.width - rect.width - edgeGap));
            var nextY = clamp(dockY + dy, edgeGap, Math.max(edgeGap, vp.height - rect.height - edgeGap));

            dock.style.left = nextX + 'px';
            dock.style.top = nextY + 'px';
            dock.style.right = 'auto';
            dock.style.transform = 'none';
            event.preventDefault();
        });

        function endDrag(event) {
            if (pointerId !== event.pointerId) return;
            resetDragState();
        }

        dock.addEventListener('pointerup', endDrag);
        dock.addEventListener('pointercancel', endDrag);
        window.addEventListener('pointerup', endDrag, true);
        window.addEventListener('pointercancel', endDrag, true);

        dock.addEventListener('click', function(event) {
            if (!suppressClick) return;
            event.preventDefault();
            event.stopPropagation();
            suppressClick = false;
        }, true);

        window.addEventListener('resize', clampDockPosition);
    })();

    (function() {
        var storageKey = 'litenote-accent';
        var accents = ['ember', 'sky', 'mint', 'violet', 'rose'];

        function isAccent(value) {
            return accents.indexOf(value) !== -1;
        }

        function savedAccent() {
            try {
                var value = localStorage.getItem(storageKey);
                return isAccent(value) ? value : 'ember';
            } catch (e) {
                return 'ember';
            }
        }

        function setAccent(accent, persist) {
            accent = isAccent(accent) ? accent : 'ember';
            if (accent === 'ember') {
                document.documentElement.removeAttribute('data-accent');
            } else {
                document.documentElement.setAttribute('data-accent', accent);
            }

            document.querySelectorAll('[data-accent-option]').forEach(function(button) {
                var active = button.dataset.accentOption === accent;
                button.classList.toggle('is-active', active);
                button.setAttribute('aria-pressed', active ? 'true' : 'false');
            });

            if (persist) {
                try { localStorage.setItem(storageKey, accent); } catch (e) {}
            }
        }

        setAccent(savedAccent(), false);

        document.addEventListener('click', function(event) {
            var button = event.target.closest ? event.target.closest('[data-accent-option]') : null;
            if (!button) return;
            setAccent(button.dataset.accentOption || 'ember', true);
        });
    })();

})();

// 前台脚本
(function() {
    'use strict';

    // 平滑滚动到锚点
    document.querySelectorAll('a[href^="#"]').forEach(function(a) {
        a.addEventListener('click', function(e) {
            var target = document.querySelector(this.getAttribute('href'));
            if (target) {
                e.preventDefault();
                target.scrollIntoView({ behavior: 'smooth' });
            }
        });
    });

    var commentIdentityKey = 'litenote_comment_identity';

    function loadCommentIdentity() {
        try {
            var raw = window.localStorage && localStorage.getItem(commentIdentityKey);
            return raw ? JSON.parse(raw) : null;
        } catch (e) {
            return null;
        }
    }

    function saveCommentIdentity(form, avatarUrl) {
        if (form.dataset.commentAdmin === '1') {
            return null;
        }

        var nick = form.querySelector('[name=nickname]');
        var email = form.querySelector('[name=email]');
        var website = form.querySelector('[name=website]');
        var identity = {
            nickname: nick ? nick.value.trim() : '',
            email: email ? email.value.trim() : '',
            website: website ? website.value.trim() : ''
        };
        var previous = loadCommentIdentity();
        if (avatarUrl) {
            identity.avatar_url = avatarUrl;
        } else if (previous && previous.avatar_url) {
            identity.avatar_url = previous.avatar_url;
        } else if (identity.email) {
            identity.avatar_url = gravatarUrl(identity.email, 80);
        }

        if (!identity.nickname && !identity.email && !identity.website) {
            return null;
        }

        try {
            localStorage.setItem(commentIdentityKey, JSON.stringify(identity));
        } catch (e) {}
        updateNavIdentity(identity);
        return identity;
    }

    function gravatarUrl(email, size) {
        email = String(email || '').trim().toLowerCase();
        if (!email) return '';
        return 'https://gravatar.bluecdn.com/avatar/' + md5(email) + '?s=' + (size || 80);
    }

    // 没有邮箱时的灰色默认头像(gravatar mystery-person),不再回退到博主头像
    function grayGravatar(size) {
        return 'https://gravatar.bluecdn.com/avatar/00000000000000000000000000000000?s=' + (size || 80);
    }

    function normalizeCommentWebsite(value) {
        value = String(value || '').trim();
        if (!value) return '';
        if (!/^https?:\/\//i.test(value)) {
            value = 'https://' + value;
        }
        try {
            var url = new URL(value);
            return (url.protocol === 'http:' || url.protocol === 'https:') ? url.href : '';
        } catch (e) {
            return '';
        }
    }

    function commentAuthorNode(comment) {
        var strong = document.createElement('strong');
        strong.textContent = (comment && comment.nickname) || '读者';
        var website = normalizeCommentWebsite(comment && comment.website);
        if (!website) return strong;

        var link = document.createElement('a');
        link.className = 'comment-author-link';
        link.href = website;
        link.target = '_blank';
        link.rel = 'nofollow noopener';
        link.appendChild(strong);
        return link;
    }

    function md5(input) {
        function cmn(q, a, b, x, s, t) { a = add32(add32(a, q), add32(x, t)); return add32((a << s) | (a >>> (32 - s)), b); }
        function ff(a, b, c, d, x, s, t) { return cmn((b & c) | ((~b) & d), a, b, x, s, t); }
        function gg(a, b, c, d, x, s, t) { return cmn((b & d) | (c & (~d)), a, b, x, s, t); }
        function hh(a, b, c, d, x, s, t) { return cmn(b ^ c ^ d, a, b, x, s, t); }
        function ii(a, b, c, d, x, s, t) { return cmn(c ^ (b | (~d)), a, b, x, s, t); }
        function add32(a, b) { return (a + b) & 0xffffffff; }
        function md5blk(s) {
            var blocks = [], i;
            for (i = 0; i < 64; i += 4) {
                blocks[i >> 2] = s.charCodeAt(i) + (s.charCodeAt(i + 1) << 8) + (s.charCodeAt(i + 2) << 16) + (s.charCodeAt(i + 3) << 24);
            }
            return blocks;
        }
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
        function hex(x) {
            var out = '', i, j;
            for (i = 0; i < x.length; i++) for (j = 0; j < 4; j++) out += ('0' + ((x[i] >> (j * 8 + 4)) & 15).toString(16)).slice(-1) + ('0' + ((x[i] >> (j * 8)) & 15).toString(16)).slice(-1);
            return out;
        }
        return hex(md51(input));
    }

    function fillCommentIdentity(form) {
        if (form.dataset.commentAdmin === '1') {
            refreshCommentComposerStatus(form);
            return;
        }

        var identity = loadCommentIdentity();
        if (!identity) {
            syncCommentIdentityUi(form, null, false);
            refreshCommentComposerStatus(form);
            return;
        }

        applyCommentIdentityToForm(form, identity);
        syncCommentIdentityUi(form, identity, false);
        refreshCommentComposerStatus(form);
    }

    function hasUsableCommentIdentity() {
        var identity = loadCommentIdentity();
        return !!(identity && identity.nickname && identity.email);
    }

    function applyCommentIdentityToForm(form, identity) {
        if (!form || form.dataset.commentAdmin === '1' || !identity) {
            return;
        }
        [
            ['nickname', identity.nickname],
            ['email', identity.email],
            ['website', identity.website]
        ].forEach(function(item) {
            var input = form.querySelector('[name=' + item[0] + ']');
            if (input) {
                input.value = item[1] || '';
            }
        });
    }

    function commentGreeting() {
        var hour = new Date().getHours();
        if (hour < 5) return '夜深了';
        if (hour < 9) return '早上好';
        if (hour < 12) return '上午好';
        if (hour < 14) return '中午好';
        if (hour < 18) return '下午好';
        if (hour < 23) return '晚上好';
        return '夜深了';
    }

    function commentIdentityForForm(form) {
        if (!form) return null;
        if (form.dataset.commentAdmin === '1') {
            var adminNick = form.querySelector('[name=nickname]');
            return {
                nickname: (adminNick && adminNick.value.trim()) || '管理员',
                admin: true
            };
        }
        var identity = loadCommentIdentity();
        if (identity && identity.nickname && identity.email) {
            return identity;
        }
        return null;
    }

    function refreshCommentComposerStatus(form) {
        if (!form) return;
        var textarea = form.querySelector('[name=content]');
        if (!textarea) return;

        var status = form.querySelector('.comment-composer-status');
        if (status) status.remove();

        var info = commentIdentityForForm(form);
        var greeting = commentGreeting();

        if (info) {
            var name = info.nickname || (info.admin ? '管理员' : '访客');
            function stablePrompt(list, key) {
                var promptIndex = form.dataset[key];
                if (promptIndex === undefined) {
                    promptIndex = String(Math.floor(Math.random() * list.length));
                    form.dataset[key] = promptIndex;
                }
                return list[parseInt(promptIndex, 10) % list.length];
            }
            if (form.classList.contains('music-share-comment-form')) {
                var musicPrompts = info.admin ? [
                    '管理员，补充一句这首歌的感受...',
                    '管理员，给这首歌留一条回应...',
                    '管理员，记录一下这首歌想起的片刻...',
                    '管理员，回复一条乐评...'
                ] : [
                    name + '，听到这首歌你想起了什么？',
                    name + '，发表一下你的感想',
                    name + '，你有相似的歌曲推荐吗？',
                    name + '，这首歌最打动你的是哪一句？',
                    name + '，给这首歌留一句乐评吧'
                ];
                textarea.placeholder = stablePrompt(musicPrompts, 'musicPromptIndex');
            } else {
                var adminPrompts = [
                    '管理员，' + greeting + '，写下你的回应...',
                    '管理员，补充一句说明...',
                    '管理员，给访客一个回复...',
                    '管理员，记录一下新的想法...',
                    '管理员，继续这段对话...'
                ];
                var visitorPrompts = [
                    'Hi, ' + name + '，' + greeting + '，写评论...',
                    name + '，想聊点什么？',
                    name + '，留下你的看法...',
                    name + '，补充一句你的想法...',
                    name + '，这条内容让你想到什么？'
                ];
                textarea.placeholder = stablePrompt(info.admin ? adminPrompts : visitorPrompts, 'commentPromptIndex');
            }
            form.classList.add('has-composer-identity');
            form.classList.remove('needs-composer-identity');
        } else {
            textarea.placeholder = form.classList.contains('music-share-comment-form') ? '保存评论身份后再写一条乐评...' : '写评论前先保存评论身份...';
            form.classList.remove('has-composer-identity');
            form.classList.add('needs-composer-identity');
        }
    }

    function commentScopeNeedsIdentity(scope) {
        var form = scope ? scope.querySelector('.comment-form') : null;
        return !(form && form.dataset.commentAdmin === '1');
    }

    function syncCommentIdentityUi(form, identity, editing) {
        if (form.dataset.commentAdmin === '1') {
            return;
        }

        var hasIdentity = !!(identity && (identity.nickname || identity.email || identity.website));
        form.classList.toggle('has-saved-identity', hasIdentity);
        form.classList.toggle('is-editing-identity', !!editing || !hasIdentity);

        var toggle = form.querySelector('[data-comment-profile-toggle]');
        if (toggle) {
            toggle.hidden = !hasIdentity;
            toggle.setAttribute('aria-expanded', editing ? 'true' : 'false');
        }

        var avatar = form.querySelector('[data-comment-profile-avatar]');
        if (avatar) {
            avatar.src = (identity && identity.avatar_url) || avatar.dataset.commentAvatarDefault || avatar.src;
            avatar.alt = (identity && identity.nickname) || '';
        }

        syncCommentIdentitySummary(form, identity, editing);
    }

    function syncCommentIdentitySummary(form, identity, editing) {
        var summary = form.querySelector('.comment-identity-summary');
        if (summary) summary.remove();
    }

    function syncAllCommentForms() {
        document.querySelectorAll('.comment-form').forEach(function(form) {
            fillCommentIdentity(form);
            refreshCommentComposerStatus(form);
            form.classList.toggle('is-captcha-trusted', isEmailTrusted(formEmailValue(form)));
            checkEmailTrust(formEmailValue(form));
        });
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
        if (img) {
            if (avatar) { img.src = avatar; img.hidden = false; }
            else { img.removeAttribute('src'); img.hidden = true; }
        }
        if (nameEl) nameEl.textContent = (identity && identity.nickname) ? identity.nickname : '';
        if (!hasIdentity) {
            if (statEl) statEl.textContent = '设置评论身份，留下你的足迹';
            return;
        }
        if (statEl && identity.email) {
            statEl.textContent = '统计中…';
            fetch('/api/visitor/stats?email=' + encodeURIComponent(identity.email), { credentials: 'same-origin' })
                .then(function(r) { return r.json(); })
                .then(function(d) {
                    var n = (d && d.comments) || 0;
                    statEl.textContent = n > 0 ? ('已留下 ' + n + ' 条评论 · 欢迎回来 👋') : '期待你的第一条评论';
                })
                .catch(function() { statEl.textContent = '欢迎回来 👋'; });
        } else if (statEl) {
            statEl.textContent = '欢迎回来 👋';
        }
    }

    function updateNavIdentity(identity) {
        updateSideIdentity(identity);
    }

    function clearCommentIdentity() {
        try { localStorage.removeItem(commentIdentityKey); } catch (e) {}
        updateNavIdentity(null);
        document.querySelectorAll('.comment-form').forEach(function(form) {
            if (form.dataset.commentAdmin === '1') return;
            form.classList.remove('has-saved-identity');
            form.classList.add('is-editing-identity');
            var summary = form.querySelector('.comment-identity-summary');
            if (summary) summary.remove();
            refreshCommentComposerStatus(form);
        });
    }

    var navIdentitySaveCallback = null;

    function showNavIdentityHint() {
        var anchor = document.querySelector('[data-nav-identity]');
        if (!anchor) {
            frontToast('评论身份已保存，可从菜单头像修改资料', 'success');
            return;
        }
        var old = document.querySelector('.nav-identity-hint');
        if (old) old.remove();
        var hint = document.createElement('div');
        hint.className = 'nav-identity-hint';
        hint.textContent = '修改资料从这里修改';
        document.body.appendChild(hint);
        var rect = anchor.getBoundingClientRect();
        var left = Math.max(16, rect.left + rect.width / 2);
        hint.style.left = left + 'px';
        hint.style.top = (rect.bottom + 12) + 'px';
        requestAnimationFrame(function() {
            hint.classList.add('is-visible');
        });
        window.setTimeout(function() {
            hint.classList.remove('is-visible');
            window.setTimeout(function() {
                if (hint.parentNode) hint.remove();
            }, 180);
        }, 2600);
    }

    function openNavIdentityDialog(options) {
        options = options || {};
        navIdentitySaveCallback = typeof options.onSave === 'function' ? options.onSave : null;
        var identity = loadCommentIdentity() || {};
        var dialog = document.querySelector('.nav-identity-dialog');
        if (!dialog) {
            dialog = document.createElement('div');
            dialog.className = 'nav-identity-dialog';
            dialog.innerHTML = '<div class="nav-identity-panel" role="dialog" aria-modal="true" tabindex="-1"><div class="nav-identity-head"><img class="nav-identity-preview" alt=""><div><p class="nav-identity-title">评论身份</p><p class="nav-identity-subtitle">保存后评论表单会自动使用这份资料</p></div></div><form class="nav-identity-form"><label class="nav-identity-field"><i class="fa-regular fa-circle-user" aria-hidden="true"></i><input name="nickname" placeholder="昵称 *" required></label><label class="nav-identity-field"><i class="fa-regular fa-envelope" aria-hidden="true"></i><input name="email" type="email" placeholder="邮箱 *" required></label><label class="nav-identity-field"><i class="fa-solid fa-link" aria-hidden="true"></i><input name="website" placeholder="网站(选填)"></label><label class="nav-identity-field nav-identity-captcha" data-nav-identity-captcha hidden><i class="fa-solid fa-shield-halved" aria-hidden="true"></i><input name="captcha" placeholder="验证码 *" autocomplete="off" maxlength="4"><img class="nav-identity-captcha-img" data-nav-identity-captcha-img src="" alt="点击刷新验证码" title="看不清?点击刷新"></label><p class="nav-identity-captcha-tip" data-nav-identity-captcha-tip hidden>首次用此邮箱评论需填验证码，通过后以后免验。</p><div class="nav-identity-buttons"><button type="button" data-nav-identity-clear>清除</button><button type="button" data-nav-identity-close>取消</button><button type="submit">保存</button></div></form></div>';
            document.body.appendChild(dialog);
            dialog.addEventListener('click', function(e) {
                if (e.target === dialog) closeNavIdentityDialog();
            });
            dialog.querySelector('[data-nav-identity-close]').addEventListener('click', closeNavIdentityDialog);
            dialog.querySelector('[data-nav-identity-clear]').addEventListener('click', function() {
                clearCommentIdentity();
                closeNavIdentityDialog();
            });
            dialog.querySelector('[data-nav-identity-captcha-img]').addEventListener('click', function() {
                this.src = '/captcha?t=' + Date.now();
            });
            dialog.querySelector('.nav-identity-form').addEventListener('submit', function(e) {
                e.preventDefault();
                var form = e.currentTarget;
                var next = {
                    nickname: form.nickname.value.trim(),
                    email: form.email.value.trim(),
                    website: form.website.value.trim()
                };
                if (!next.nickname || !next.email) { return; }
                var finishSave = function() {
                    next.avatar_url = gravatarUrl(next.email, 80);
                    try { localStorage.setItem(commentIdentityKey, JSON.stringify(next)); } catch (err) {}
                    addTrustedEmail(next.email);
                    updateNavIdentity(next);
                    syncAllCommentForms();
                    closeNavIdentityDialog(false);
                    showNavIdentityHint();
                    if (navIdentitySaveCallback) {
                        var cb = navIdentitySaveCallback;
                        navIdentitySaveCallback = null;
                        cb(next);
                    }
                };
                var captchaWrap = dialog.querySelector('[data-nav-identity-captcha]');
                var needCaptcha = captchaWrap && !captchaWrap.hidden;
                if (!needCaptcha) { finishSave(); return; }
                // 非白名单邮箱:把验证码交后端校验,通过后该邮箱进白名单
                var captchaVal = (form.captcha.value || '').trim();
                if (captchaVal.length < 4) { form.captcha.focus(); return; }
                var saveBtn = form.querySelector('button[type=submit]');
                if (saveBtn) saveBtn.disabled = true;
                var body = new FormData();
                body.append('email', next.email);
                body.append('captcha', captchaVal);
                body.append('_csrf', frontCsrf());
                fetch('/comment/verify-identity', { method: 'POST', headers: frontAjaxHeaders(), body: body, credentials: 'same-origin' })
                    .then(function(r) { return r.json(); })
                    .then(function(d) {
                        if (saveBtn) saveBtn.disabled = false;
                        if (d && d.ok) { finishSave(); return; }
                        frontToast((d && d.msg) || '验证码错误', 'error');
                        var img = dialog.querySelector('[data-nav-identity-captcha-img]');
                        if (img) img.src = '/captcha?t=' + Date.now();
                        form.captcha.value = '';
                        form.captcha.focus();
                    })
                    .catch(function() {
                        if (saveBtn) saveBtn.disabled = false;
                        frontToast('网络错误,请重试', 'error');
                    });
            });
            dialog.querySelector('[name=email]').addEventListener('input', function(e) {
                var preview = dialog.querySelector('.nav-identity-preview');
                preview.src = gravatarUrl(e.target.value, 80) || grayGravatar(80);
                navIdentityRefreshCaptcha(dialog, e.target.value);
            });
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') closeNavIdentityDialog();
            });
        }
        dialog.querySelector('[name=nickname]').value = identity.nickname || '';
        dialog.querySelector('[name=email]').value = identity.email || '';
        dialog.querySelector('[name=website]').value = identity.website || '';
        var capInput = dialog.querySelector('[name=captcha]');
        if (capInput) capInput.value = '';
        dialog.querySelector('.nav-identity-preview').src = identity.avatar_url || gravatarUrl(identity.email, 80) || grayGravatar(80);
        navIdentityRefreshCaptcha(dialog, identity.email || '');
        dialog.classList.add('is-open');
        var panel = dialog.querySelector('.nav-identity-panel');
        if (panel) panel.focus();
    }

    // 身份表单里的验证码:默认显示(后台开了验证码时),只有确认邮箱在白名单才隐藏(免验)
    function navIdentityRefreshCaptcha(dialog, email) {
        var wrap = dialog.querySelector('[data-nav-identity-captcha]');
        var tip = dialog.querySelector('[data-nav-identity-captcha-tip]');
        if (!wrap) return;
        email = (email || '').trim();
        function show(visible) {
            var wasHidden = wrap.hidden;
            wrap.hidden = !visible;
            if (tip) tip.hidden = !visible;
            if (visible) {
                var img = dialog.querySelector('[data-nav-identity-captcha-img]');
                // 从隐藏变可见(或还没加载过)时刷新验证码图,保证初次显示就有图
                if (img && (wasHidden || !img.getAttribute('src'))) img.src = '/captcha?t=' + Date.now();
            }
        }
        // 已确认白名单邮箱:免验,隐藏
        if (email && email.indexOf('@') !== -1 && isEmailTrusted(email)) { show(false); return; }
        // 其余情况(没填邮箱 / 新邮箱):默认显示
        show(true);
        if (!email || email.indexOf('@') === -1) return;
        fetch('/api/visitor/stats?email=' + encodeURIComponent(email), { credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (d && d.trusted) {
                    addTrustedEmail(email);
                    applyCaptchaTrustToForms();
                    var cur = dialog.querySelector('[name=email]');
                    if (cur && (cur.value || '').trim() === email) show(false);
                }
            })
            .catch(function() {});
    }

    function closeNavIdentityDialog(clearCallback) {
        if (clearCallback !== false) {
            navIdentitySaveCallback = null;
        }
        var dialog = document.querySelector('.nav-identity-dialog');
        if (dialog) dialog.classList.remove('is-open');
    }

    // 可信邮箱(后端确认有审核通过评论)免验证码:本地缓存一份,提交时跳过验证码并隐藏验证码框。
    var trustedEmailsKey = 'litenote_trusted_emails';
    function loadTrustedEmails() {
        try { var v = JSON.parse(localStorage.getItem(trustedEmailsKey) || '[]'); return Array.isArray(v) ? v : []; }
        catch (e) { return []; }
    }
    function isEmailTrusted(email) {
        email = (email || '').trim().toLowerCase();
        return !!email && loadTrustedEmails().indexOf(email) !== -1;
    }
    function addTrustedEmail(email) {
        email = (email || '').trim().toLowerCase();
        if (!email) return;
        var list = loadTrustedEmails();
        if (list.indexOf(email) === -1) {
            list.push(email);
            try { localStorage.setItem(trustedEmailsKey, JSON.stringify(list)); } catch (e) {}
        }
    }
    function formEmailValue(form) {
        var em = form.querySelector('[name=email]');
        if (em && em.value.trim()) return em.value.trim();
        var id = loadCommentIdentity() || {};
        return id.email || '';
    }
    function applyCaptchaTrustToForms(root) {
        (root || document).querySelectorAll('.comment-form').forEach(function(form) {
            form.classList.toggle('is-captcha-trusted', isEmailTrusted(formEmailValue(form)));
        });
    }
    // 换设备/清缓存后本地不知道该邮箱是否在白名单:问一次后端(复用访客统计接口,审核通过数>0 即白名单),
    // 命中则写入本地并隐藏验证码,做到"白名单邮箱在任何设备都不显示验证码"。
    var trustChecking = {};
    function checkEmailTrust(email) {
        email = (email || '').trim().toLowerCase();
        if (!email || email.indexOf('@') === -1) return;
        if (isEmailTrusted(email)) return;
        if (trustChecking[email]) return;
        trustChecking[email] = true;
        fetch('/api/visitor/stats?email=' + encodeURIComponent(email), { credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (d && (d.comments || 0) > 0) {
                    addTrustedEmail(email);
                    applyCaptchaTrustToForms();
                }
            })
            .catch(function() {})
            .then(function() { delete trustChecking[email]; });
    }

    function bindNavIdentityOrb(root) {
        root = root || document;
        updateNavIdentity(loadCommentIdentity());
        root.querySelectorAll('[data-nav-identity]').forEach(function(orb) {
            if (orb.dataset.lnMenuBound) return; orb.dataset.lnMenuBound = '1';
            var avatar = orb.querySelector('.nav-avatar');
            var hideTimer = 0;
            var suppressHover = false;
            var suppressStorageKey = 'litenote_nav_identity_suppress_until';
            function isSuppressingHover() {
                if (suppressHover) return true;
                try {
                    var until = parseInt(sessionStorage.getItem(suppressStorageKey) || '0', 10) || 0;
                    return until > Date.now();
                } catch (e) {
                    return false;
                }
            }
            function clearStoredSuppress() {
                try { sessionStorage.removeItem(suppressStorageKey); } catch (e) {}
            }
            try {
                var storedSuppressUntil = parseInt(sessionStorage.getItem(suppressStorageKey) || '0', 10) || 0;
                if (storedSuppressUntil <= Date.now()) clearStoredSuppress();
                else window.setTimeout(clearStoredSuppress, storedSuppressUntil - Date.now());
            } catch (e) {}
            function clearHideTimer() {
                window.clearTimeout(hideTimer);
                window.clearTimeout(orb.__lnIdentityHideTimer || 0);
                orb.__lnIdentityHideTimer = 0;
            }
            function scheduleHide() {
                clearHideTimer();
                hideTimer = window.setTimeout(function() {
                    orb.classList.remove('is-menu-open');
                    orb.__lnIdentityHideTimer = 0;
                }, 2000);
                orb.__lnIdentityHideTimer = hideTimer;
            }
            orb.__lnCloseIdentityMenu = function() {
                clearHideTimer();
                orb.classList.remove('is-menu-open');
            };
            function showMenu() {
                if (isSuppressingHover()) return;
                var shell = document.getElementById('nav-shell');
                if (shell) shell.classList.remove('nav-open');
                orb.classList.add('is-menu-open');
                scheduleHide();
            }
            if (avatar) {
                avatar.addEventListener('mouseenter', showMenu);
                avatar.addEventListener('mouseleave', function() {
                    suppressHover = false;
                    clearStoredSuppress();
                });
                avatar.addEventListener('click', function(e) {
                    suppressHover = true;
                    orb.classList.add('is-avatar-navigating');
                    if (window.location.pathname === '/' || window.location.pathname === '') {
                        e.preventDefault();
                        window.location.assign(avatar.href || '/');
                        return;
                    }
                    try { sessionStorage.setItem(suppressStorageKey, String(Date.now() + 1600)); } catch (err) {}
                });
            }
            orb.querySelectorAll('.nav-identity-action').forEach(function(action) {
                action.addEventListener('mouseenter', clearHideTimer);
                action.addEventListener('mouseleave', scheduleHide);
            });
        });
        root.querySelectorAll('[data-nav-identity-edit]').forEach(function(btn) {
            if (btn.dataset.lnBound) return; btn.dataset.lnBound = '1';
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                openNavIdentityDialog();
            });
        });
        // 移动端底部「更多」上浮菜单开关
        root.querySelectorAll('[data-nav-more]').forEach(function(btn) {
            if (btn.dataset.lnBound) return; btn.dataset.lnBound = '1';
            var menu = btn.parentElement ? btn.parentElement.querySelector('[data-nav-more-menu]') : null;
            function setOpen(open) {
                btn.classList.toggle('is-open', open);
                btn.setAttribute('aria-expanded', open ? 'true' : 'false');
                if (menu) menu.classList.toggle('is-open', open);
            }
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                setOpen(!btn.classList.contains('is-open'));
            });
            document.addEventListener('click', function(e) {
                if (!btn.classList.contains('is-open')) return;
                if (btn.contains(e.target) || (menu && menu.contains(e.target))) return;
                setOpen(false);
            });
        });
    }

    // AJAX 提交后把新评论插入列表(说说 / 文章两种结构)
    function appendComment(form, c) {
        var talkInput = form.querySelector('[name=talk_id]');
        var isTalk = talkInput && parseInt(talkInput.value, 10) > 0;
        var musicInput = form.querySelector('[name=music_id]');
        var isMusic = musicInput && parseInt(musicInput.value, 10) > 0;
        var parentId = parseInt(c.parent_id, 10) || 0;
        var isPending = !!c.pending;

        function markPending(el, meta) {
            if (!isPending || !el) return;
            el.classList.add('is-pending');
            var badge = document.createElement('span');
            badge.className = 'comment-pending-note';
            badge.textContent = '等待审核，仅你可见';
            if (meta) {
                meta.appendChild(badge);
            } else {
                el.appendChild(badge);
            }
        }

        // 找到回复应归属的顶层评论 li(父级本身是回复时,取其所属顶层)
        function rootLiOf(scope, pid) {
            var pli = scope.querySelector('[data-id="' + pid + '"]');
            if (!pli) return null;
            var pl = pli.parentElement;
            if (pl && (pl.classList.contains('comment-reply-list') || pl.classList.contains('talk-reply-list'))) {
                return pl.parentElement;
            }
            return pli;
        }
        function replyListIn(rootLi, cls) {
            var rl = rootLi.querySelector(':scope > .' + cls);
            if (!rl) {
                rl = document.createElement('ul');
                rl.className = cls === 'talk-reply-list' ? 'comment-reply-list talk-reply-list' : cls;
                rootLi.appendChild(rl);
            }
            return rl;
        }

        if (isTalk) {
            var container = form.closest('.talk-comments') || form.parentNode;
            var li = document.createElement('li');
            li.className = 'comment-item' + (parentId > 0 ? ' comment-reply' : '');
            li.setAttribute('data-id', c.id);
            var avatarImg = document.createElement('img');
            avatarImg.className = 'comment-avatar';
            avatarImg.src = c.avatar_url || grayGravatar(parentId > 0 ? 28 : 32);
            avatarImg.alt = c.nickname || '读者';
            avatarImg.loading = 'lazy';
            avatarImg.width = parentId > 0 ? 28 : 32;
            avatarImg.height = parentId > 0 ? 28 : 32;
            var body = document.createElement('div');
            body.className = 'comment-body';
            var meta = document.createElement('div');
            meta.className = 'comment-meta';
            meta.appendChild(commentAuthorNode(c));
            var rtn = (parentId > 0) ? (form.dataset.replyTo || '') : '';
            if (rtn) {
                var ar = document.createElement('span'); ar.className = 'reply-arrow'; ar.textContent = ' › ';
                var tg = document.createElement('span'); tg.className = 'reply-target'; tg.textContent = rtn;
                meta.appendChild(ar); meta.appendChild(tg);
            }
            var time = document.createElement('span'); time.className = 'comment-time'; time.innerHTML = ' · ' + c.time;
            var bodyS = document.createElement('div'); bodyS.className = 'comment-content talk-comment-content'; bodyS.textContent = c.content;
            meta.appendChild(time);
            body.appendChild(meta);
            body.appendChild(bodyS);
            li.appendChild(avatarImg);
            li.appendChild(body);
            markPending(li, meta);

            if (parentId > 0) {
                var root = rootLiOf(container, parentId);
                if (root) { replyListIn(root, 'talk-reply-list').appendChild(li); }
                else { var l0 = container.querySelector('.talk-comment-list'); if (l0) l0.appendChild(li); }
            } else {
                var list = container.querySelector('.talk-comment-list');
                if (!list) { list = document.createElement('ul'); list.className = 'comment-list talk-comment-list'; container.insertBefore(list, form); }
                list.appendChild(li);
            }
            if (!isPending && container.id) {
                var toggle = document.querySelector('.talk-comment-toggle[data-target="' + container.id + '"] span');
                if (toggle) toggle.textContent = (parseInt(toggle.textContent, 10) || 0) + 1;
            }
            return li;
        } else {
            var section = form.closest('.comments') || form.parentNode;
            var listScope = section;
            if (isMusic) {
                var homeMusicCard = form.closest('.home-music-card');
                if (homeMusicCard) {
                    section.classList.add('is-open');
                    homeMusicCard.classList.add('is-comments-open');
                    var homeEmpty = section.querySelector('.music-comment-empty');
                    if (homeEmpty) homeEmpty.remove();
                    var homeList = section.querySelector(':scope > .comment-list');
                    if (!homeList) {
                        homeList = document.createElement('ul');
                        homeList.className = 'comment-list';
                        section.insertBefore(homeList, form);
                    }

                    var homeItem = document.createElement('li');
                    homeItem.className = 'comment-item';
                    homeItem.setAttribute('data-id', c.id);
                    homeItem.dataset.fullContent = c.content || '';
                    homeItem.setAttribute('tabindex', '0');
                    homeItem.setAttribute('role', 'button');

                    var homeAvatar = document.createElement('img');
                    homeAvatar.className = 'music-share-comment-avatar';
                    homeAvatar.src = c.avatar_url || '';
                    homeAvatar.alt = c.nickname || '';
                    homeAvatar.loading = 'lazy';
                    homeAvatar.width = 40;
                    homeAvatar.height = 40;

                    var homeBody = document.createElement('div');
                    homeBody.className = 'comment-body';
                    var homeMeta = document.createElement('div');
                    homeMeta.className = 'comment-meta';
                    var homeTime = document.createElement('span');
                    homeTime.className = 'ct';
                    homeTime.innerHTML = c.time || '';
                    var homeContent = document.createElement('div');
                    homeContent.className = 'comment-content';
                    homeContent.textContent = c.content || '';

                    homeMeta.appendChild(commentAuthorNode(c));
                    homeMeta.appendChild(homeTime);
                    homeBody.appendChild(homeMeta);
                    homeBody.appendChild(homeContent);
                    homeItem.appendChild(homeAvatar);
                    homeItem.appendChild(homeBody);
                    markPending(homeItem, homeMeta);
                    homeList.appendChild(homeItem);
                    bindMusicCommentCard(homeItem);

                    if (!isPending) {
                        var homeNextCount = (parseInt(section.dataset.commentCount || '0', 10) || 0) + 1;
                        section.dataset.commentCount = String(homeNextCount);
                        section.querySelectorAll('[data-music-comment-count]').forEach(function(countEl) {
                            countEl.textContent = String(homeNextCount);
                        });
                        syncMusicCommentCount(musicInput ? musicInput.value : '', homeNextCount);
                    }
                    return homeItem;
                }
                listScope = section.querySelector('.music-comment-thread.is-active') || section;
                var empty = listScope.querySelector('.music-comment-empty');
                if (empty) empty.remove();
                var musicList = listScope.querySelector('.music-song-comment-list');
                if (!musicList) {
                    musicList = document.createElement('ul');
                    musicList.className = 'comment-list music-song-comment-list';
                    listScope.insertBefore(musicList, listScope.firstChild);
                }
                var musicItem = document.createElement('li');
                musicItem.className = 'comment-item music-song-comment';
                musicItem.setAttribute('data-id', c.id);

                var musicAvatar = document.createElement('span');
                musicAvatar.className = 'comment-avatar music-song-comment-avatar';
                var musicAvatarImg = document.createElement('img');
                musicAvatarImg.src = c.avatar_url || '';
                musicAvatarImg.alt = c.nickname || '';
                musicAvatarImg.loading = 'lazy';
                musicAvatarImg.width = 48;
                musicAvatarImg.height = 48;
                musicAvatar.appendChild(musicAvatarImg);

                var musicBody = document.createElement('div');
                musicBody.className = 'comment-body music-song-comment-body';
                var musicMeta = document.createElement('div');
                musicMeta.className = 'comment-meta music-song-comment-meta';
                var musicTime = document.createElement('span');
                musicTime.innerHTML = c.time;
                musicMeta.appendChild(commentAuthorNode(c));
                musicMeta.appendChild(musicTime);
                var musicContent = document.createElement('div');
                musicContent.className = 'comment-content music-song-comment-content';
                musicContent.textContent = c.content;
                musicBody.appendChild(musicMeta);
                musicBody.appendChild(musicContent);
                musicItem.appendChild(musicAvatar);
                musicItem.appendChild(musicBody);
                markPending(musicItem, musicMeta);
                musicList.appendChild(musicItem);

                if (!isPending) {
                    var nextCountOnly = (parseInt(listScope.dataset.commentCount || '0', 10) || 0) + 1;
                    listScope.dataset.commentCount = String(nextCountOnly);
                    var countElOnly = section.querySelector('[data-music-comment-count]');
                    if (countElOnly) countElOnly.textContent = String(nextCountOnly);
                    syncMusicCommentCount(musicInput ? musicInput.value : '', nextCountOnly);
                    var activeTrackOnly = document.querySelector('[data-music-track].is-active');
                    if (activeTrackOnly) {
                        activeTrackOnly.dataset.comments = String(nextCountOnly);
                        var trackCountOnly = activeTrackOnly.querySelector('[data-music-track-comments]');
                        if (trackCountOnly) trackCountOnly.textContent = String(nextCountOnly);
                    }
                }
                return musicItem;
            }
            var item = document.createElement('li');
            item.className = 'comment-item' + (parentId > 0 ? ' comment-reply' : '');
            item.setAttribute('data-id', c.id);
            var rtn2 = (parentId > 0) ? (form.dataset.replyTo || '') : '';
            var metaExtra = rtn2 ? '<span class="reply-arrow">›</span><span class="reply-target"></span>' : '';
            item.innerHTML = '<img class="comment-avatar" src="" alt="" loading="lazy" width="' + (parentId > 0 ? '28' : '32') + '" height="' + (parentId > 0 ? '28' : '32') + '"><div class="comment-body"><div class="comment-meta"><strong></strong>' + metaExtra + ' <span class="ct"></span></div><div class="comment-content"></div></div>';
            var itemAvatar = item.querySelector('.comment-avatar');
            if (itemAvatar) {
                itemAvatar.src = c.avatar_url || grayGravatar(parentId > 0 ? 28 : 32);
                itemAvatar.alt = c.nickname || '读者';
            }
            item.querySelector('strong').replaceWith(commentAuthorNode(c));
            if (rtn2) item.querySelector('.reply-target').textContent = rtn2;
            item.querySelector('.ct').innerHTML = '· ' + c.time;
            if (c.location) {
                var meta = item.querySelector('.comment-meta');
                var geo = document.createElement('span');
                geo.className = 'comment-location';
                var loc = document.createElement('span');
                loc.textContent = c.location;
                geo.appendChild(loc);
                if (meta) meta.appendChild(geo);
            }
            markPending(item, item.querySelector('.comment-meta'));
            item.querySelector('.comment-content').textContent = c.content;

            if (parentId > 0) {
                var root2 = rootLiOf(listScope, parentId);
                if (root2) { replyListIn(root2, 'comment-reply-list').appendChild(item); }
                else { var u0 = listScope.querySelector('.comment-list'); if (u0) u0.appendChild(item); }
            } else {
                var ul = listScope.querySelector('.comment-list');
                if (!ul) {
                    ul = document.createElement('ul');
                    ul.className = 'comment-list';
                    listScope.insertBefore(ul, listScope.contains(form) ? form : null);
                }
                ul.appendChild(item);
            }
            if (isMusic && !isPending && listScope.dataset) {
                var nextCount = (parseInt(listScope.dataset.commentCount || '0', 10) || 0) + 1;
                listScope.dataset.commentCount = String(nextCount);
                var countEl = section.querySelector('[data-music-comment-count]');
                if (countEl) countEl.textContent = String(nextCount);
                syncMusicCommentCount(musicInput ? musicInput.value : '', nextCount);
                var activeTrack = document.querySelector('[data-music-track].is-active');
                if (activeTrack) {
                    activeTrack.dataset.comments = String(nextCount);
                    var trackCount = activeTrack.querySelector('[data-music-track-comments]');
                    if (trackCount) trackCount.textContent = String(nextCount);
                }
            }
            return item;
        }
    }

    function scrollToSubmittedComment(item) {
        if (!item) return;
        item.classList.add('is-newly-submitted');
        window.setTimeout(function() {
            item.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }, 80);
        window.setTimeout(function() {
            item.classList.remove('is-newly-submitted');
        }, 2400);
    }

    // 评论提交后的统一 toast 提示
    function commentFlash(form, msg, type) {
        frontToast(msg, type === 'success' ? 'success' : 'error');
    }

    function setCommentSubmitLoading(form, loading) {
        var btn = form.querySelector('button[type=submit]') || form.querySelector('button');
        if (loading) form.dataset.commentSubmitting = '1';
        else delete form.dataset.commentSubmitting;
        if (!btn) return;
        btn.disabled = !!loading;
        btn.classList.toggle('is-loading', !!loading);
        if (loading) btn.setAttribute('aria-busy', 'true');
        else btn.removeAttribute('aria-busy');
    }

    function replyMentionPrefix(nickname) {
        nickname = String(nickname || '').trim().replace(/\s+/g, '');
        return nickname ? '@' + nickname + ' ' : '';
    }

    function stripReplyMentionPrefix(value, prefix) {
        value = String(value || '');
        prefix = String(prefix || '');
        if (prefix && value.indexOf(prefix) === 0) {
            return value.slice(prefix.length);
        }
        return value.replace(/^@\S+\s+/u, '');
    }

    function setReplyMention(form, textarea, nickname) {
        if (!textarea) return;
        var oldPrefix = form.dataset.replyPrefix || '';
        var nextPrefix = replyMentionPrefix(nickname);
        var value = textarea.value || '';
        if (oldPrefix && value.indexOf(oldPrefix) === 0) {
            value = value.slice(oldPrefix.length);
        } else if (form.dataset.replyTo && value.indexOf('@') === 0) {
            value = value.replace(/^@\S+\s*/u, '');
        }
        textarea.value = nextPrefix + value;
        form.dataset.replyPrefix = nextPrefix;
        window.setTimeout(function() {
            var pos = nextPrefix.length;
            textarea.focus();
            textarea.setSelectionRange(pos, pos);
        }, 80);
    }

    // 评论表单(可对动态加载的内容重复调用)
    function bindCommentForm(form) {
        if (form.dataset.lnBound) return; form.dataset.lnBound = '1';
        form.noValidate = true;
        fillCommentIdentity(form);
        refreshCommentComposerStatus(form);
        form.classList.toggle('is-captcha-trusted', isEmailTrusted(formEmailValue(form)));
        checkEmailTrust(formEmailValue(form));

        var profileToggle = form.querySelector('[data-comment-profile-toggle]');
        if (profileToggle) {
            profileToggle.addEventListener('click', function() {
                openNavIdentityDialog({
                    onSave: function(savedIdentity) {
                        applyCommentIdentityToForm(form, savedIdentity);
                        syncCommentIdentityUi(form, savedIdentity, false);
                        refreshCommentComposerStatus(form);
                    }
                });
            });
        }

        // 验证码:点击图片刷新;表单首次聚焦时刷新本表单验证码,
        // 避免同页多个评论表单共享 session 验证码时被互相覆盖。
        var captchaImg = form.querySelector('.captcha-img');
        if (captchaImg) {
            var refreshCaptcha = function() {
                captchaImg.src = '/captcha?t=' + Date.now();
            };
            captchaImg.addEventListener('click', refreshCaptcha);
            var captchaRefreshed = false;
            form.addEventListener('focusin', function() {
                if (captchaRefreshed) return;
                captchaRefreshed = true;
                refreshCaptcha();
            });
        }

        // 访客未保存身份时,聚焦评论输入框自动(再次)弹出身份表单
        var contentField = form.querySelector('[name=content]');
        if (contentField && form.dataset.commentAdmin !== '1') {
            contentField.addEventListener('focus', function() {
                if (hasUsableCommentIdentity()) return;
                openNavIdentityDialog({
                    onSave: function() { try { contentField.focus(); } catch (e) {} }
                });
            });
        }

        form.addEventListener('submit', function(e) {
            e.preventDefault();
            if (form.dataset.commentSubmitting === '1') return;
            var nick = form.querySelector('[name=nickname]');
            var email = form.querySelector('[name=email]');
            var content = form.querySelector('[name=content]');
            var identity = loadCommentIdentity();
            if (form.dataset.commentAdmin !== '1') {
                // 无身份,或邮箱还没进白名单(没验证过验证码)→ 弹身份表单,在那里填/验证码,通过后再提交
                if (!hasUsableCommentIdentity() || !isEmailTrusted(formEmailValue(form))) {
                    openNavIdentityDialog({
                        onSave: function(savedIdentity) {
                            applyCommentIdentityToForm(form, savedIdentity);
                            window.setTimeout(function() {
                                if (form.requestSubmit) form.requestSubmit();
                                else form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
                            }, 80);
                        }
                    });
                    return;
                }
                applyCommentIdentityToForm(form, identity);
            }
            var normalizedContent = content ? stripReplyMentionPrefix(content.value, form.dataset.replyPrefix).trim() : '';
            if (nick && !nick.value.trim()) { openNavIdentityDialog(); return; }
            if (email && !email.value.trim()) { openNavIdentityDialog(); return; }
            if (content && normalizedContent.length < 2) { frontToast('评论内容太短了', 'error'); content.focus(); return; }

            setCommentSubmitLoading(form, true);
            saveCommentIdentity(form);
            var formData = new FormData(form);
            if (content) {
                formData.set('content', normalizedContent);
            }

            fetch('/comment/submit', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            }).then(function(r) { return r.json(); }).then(function(d) {
                setCommentSubmitLoading(form, false);
                if (captchaImg) captchaImg.src = '/captcha?t=' + Date.now();
                var captchaInput = form.querySelector('[name=captcha]');
                if (captchaInput) captchaInput.value = '';
                if (!d || d.code !== 0) { frontToast((d && d.msg) || '提交失败', 'error'); return; }

                if (d.trusted) {
                    addTrustedEmail(formEmailValue(form));
                    applyCaptchaTrustToForms();
                }
                if (content) content.value = '';
                var pid = form.querySelector('[name=parent_id]');
                if (pid) pid.value = '0';
                form.classList.remove('is-replying');
                form.dataset.replyPrefix = '';

                if (d.comment) {
                    d.comment.pending = !!d.pending || !!d.comment.pending;
                    var submittedComment = appendComment(form, d.comment);
                    scrollToSubmittedComment(submittedComment);
                    commentFlash(form, d.pending ? '评论已提交，等待审核' : '评论发布成功', 'success');
                } else {
                    commentFlash(form, d.msg || '评论已提交，等待审核后显示', 'success');
                }
                if (form.dataset.commentAdmin !== '1') {
                    var avatarUrl = (d.comment && d.comment.avatar_url) || d.avatar_url || '';
                    syncCommentIdentityUi(form, saveCommentIdentity(form, avatarUrl), false);
                }
                form.dataset.replyTo = '';
            }).catch(function() {
                setCommentSubmitLoading(form, false);
                frontToast('网络错误，提交失败', 'error');
            });
        });
    }

    function bindReplyBtn(btn) {
        if (btn.dataset.lnBound) return; btn.dataset.lnBound = '1';
        btn.addEventListener('click', function() {
            var scope = btn.closest('.comments') || btn.closest('.talk-comments') || document;
            var form = scope.querySelector('.comment-form');
            if (!form) {
                return;
            }

            var parentInput = form.querySelector('[name=parent_id]');
            var textarea = form.querySelector('[name=content]');

            if (parentInput) {
                parentInput.value = btn.dataset.parentId || '0';
            }
            // 记录被回复者昵称,并在输入框显示 @昵称 作为当前回复状态。
            form.dataset.replyTo = (btn.dataset.nickname || '').trim();
            setReplyMention(form, textarea, form.dataset.replyTo);

            form.classList.add('is-replying');
            form.scrollIntoView({ behavior: 'smooth', block: 'center' });
            if (textarea) {
                var caret = (form.dataset.replyPrefix || '').length;
                textarea.focus();
                textarea.setSelectionRange(caret, caret);
            }
        });
    }

    function bindMusicCommentCard(item) {
        if (item.dataset.lnBoundExpand) return; item.dataset.lnBoundExpand = '1';
        var content = item.querySelector('.comment-content');
        if (content && !item.dataset.fullContent) {
            item.dataset.fullContent = content.textContent || '';
        }
        function openDialog() {
            var metaName = item.querySelector('.comment-meta strong');
            var metaTime = item.querySelector('.comment-meta .ct');
            openMusicCommentDialog({
                nickname: metaName ? metaName.textContent.trim() : '读者',
                time: metaTime ? metaTime.textContent.trim() : '',
                content: item.dataset.fullContent || (content ? content.textContent : '')
            });
        }
        item.addEventListener('click', function(e) {
            if (e.target.closest('a, button, input, textarea, select, label')) return;
            openDialog();
        });
        item.addEventListener('keydown', function(e) {
            if (e.key !== 'Enter' && e.key !== ' ') return;
            e.preventDefault();
            openDialog();
        });
    }

    function openMusicCommentDialog(data) {
        var dialog = document.querySelector('.music-comment-dialog');
        if (!dialog) {
            dialog = document.createElement('div');
            dialog.className = 'music-comment-dialog';
            dialog.innerHTML = '<div class="music-comment-dialog-panel" role="dialog" aria-modal="true" tabindex="-1"><div class="music-comment-dialog-meta"><strong></strong><span></span></div><div class="music-comment-dialog-content"></div></div>';
            document.body.appendChild(dialog);
            dialog.addEventListener('click', function(e) {
                if (e.target === dialog) closeMusicCommentDialog();
            });
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') closeMusicCommentDialog();
            });
        }
        dialog.querySelector('.music-comment-dialog-meta strong').textContent = data.nickname || '读者';
        dialog.querySelector('.music-comment-dialog-meta span').textContent = data.time || '';
        dialog.querySelector('.music-comment-dialog-content').textContent = data.content || '';
        dialog.classList.add('is-open');
        var panel = dialog.querySelector('.music-comment-dialog-panel');
        if (panel) panel.focus();
    }

    function closeMusicCommentDialog() {
        var dialog = document.querySelector('.music-comment-dialog');
        if (dialog) dialog.classList.remove('is-open');
    }

    function bindToggleBtn(btn) {
        if (btn.dataset.lnBound) return; btn.dataset.lnBound = '1';
        btn.addEventListener('click', function() {
            var target = document.getElementById(btn.dataset.target || '');
            if (commentScopeNeedsIdentity(target) && !hasUsableCommentIdentity()) {
                openNavIdentityDialog({
                    onSave: function() {
                        if (target) {
                            target.classList.add('is-open');
                            var musicCard = target.closest('.home-music-card');
                            if (musicCard && target.classList.contains('music-share-comments')) {
                                musicCard.classList.add('is-comments-open');
                            }
                        }
                    }
                });
                return;
            }
            if (target) {
                target.classList.toggle('is-open');
                var homeMusicCard = target.closest('.home-music-card');
                if (homeMusicCard && target.classList.contains('music-share-comments')) {
                    homeMusicCard.classList.toggle('is-comments-open', target.classList.contains('is-open'));
                }
            }
        });
    }

    // 点赞撒花:在按钮周围迸发小彩花
    function likeConfetti(btn) {
        var rect = btn.getBoundingClientRect();
        var cx = rect.left + rect.width / 2;
        var cy = rect.top + rect.height / 2;
        var colors = ['#ff6b6b', '#feca57', '#48dbfb', '#1dd1a1', '#ff9ff3', '#e65a4c', '#a55eea'];
        var n = 14;
        for (var i = 0; i < n; i++) {
            var p = document.createElement('span');
            p.className = 'like-confetti';
            var angle = (Math.PI * 2 * i) / n + (Math.random() - 0.5) * 0.5;
            var dist = 16 + Math.random() * 18;
            p.style.left = cx + 'px';
            p.style.top = cy + 'px';
            p.style.background = colors[i % colors.length];
            if (i % 2 === 0) { p.style.borderRadius = '2px'; }
            p.style.setProperty('--dx', (Math.cos(angle) * dist).toFixed(1) + 'px');
            p.style.setProperty('--dy', (Math.sin(angle) * dist - 6).toFixed(1) + 'px');
            document.body.appendChild(p);
            (function(el) {
                requestAnimationFrame(function() {
                    requestAnimationFrame(function() { el.classList.add('burst'); });
                });
                setTimeout(function() { if (el.parentNode) el.remove(); }, 750);
            })(p);
        }
    }

    function likedStorageKey(type) {
        return 'litenote-liked-' + type;
    }

    function readLikedMap(type) {
        try {
            var raw = localStorage.getItem(likedStorageKey(type));
            var parsed = raw ? JSON.parse(raw) : {};
            return parsed && typeof parsed === 'object' ? parsed : {};
        } catch (e) {
            return {};
        }
    }

    function hasLiked(type, id) {
        if (!id) return false;
        var map = readLikedMap(type);
        return !!map[String(id)];
    }

    function rememberLiked(type, id) {
        if (!id) return;
        try {
            var map = readLikedMap(type);
            map[String(id)] = 1;
            localStorage.setItem(likedStorageKey(type), JSON.stringify(map));
        } catch (e) {}
    }

    function likeSvg(name) {
        if (name === 'heart-filled') {
            return '<span class="ln-icon" data-ln-icon="heart" data-ln-filled="1" data-ln-icon-trigger="both" aria-hidden="true"></span>';
        }
        return '<span class="ln-icon" data-ln-icon="heart" data-ln-icon-trigger="both" aria-hidden="true"></span>';
    }

    function musicPlaySvg() {
        return '<span class="ln-icon" data-ln-icon="play" data-ln-icon-trigger="both" aria-hidden="true"></span>';
    }

    function musicPauseSvg() {
        return '<span class="ln-icon" data-ln-icon="pause" data-ln-icon-trigger="both" aria-hidden="true"></span>';
    }

    function musicErrorSvg() {
        return '<i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>';
    }

    function mountLnIconNode(node) {
        if (!node || !window.LnIcons) return node;
        try {
            return window.LnIcons.mount(node) || node;
        } catch (e) {
            return node;
        }
    }

    function replaceButtonIcon(btn, html) {
        if (!btn) return;
        var current = btn.querySelector('.ln-icon, i');
        if (!current) {
            btn.insertAdjacentHTML('afterbegin', html);
            mountLnIconNode(btn.querySelector('.ln-icon'));
            return;
        }
        current.outerHTML = html;
        mountLnIconNode(btn.querySelector('.ln-icon'));
    }

    function setMusicToggleIcon(btn, iconName) {
        if (!btn) return;
        if (iconName === 'pause') {
            replaceButtonIcon(btn, musicPauseSvg());
        } else if (iconName === 'error') {
            replaceButtonIcon(btn, musicErrorSvg());
        } else {
            replaceButtonIcon(btn, musicPlaySvg());
        }
    }

    function updateLikeButton(btn, liked) {
        if (!btn) return;
        btn.classList.toggle('is-liked', !!liked);
        btn.setAttribute('aria-pressed', liked ? 'true' : 'false');
        var lnIcon = btn.querySelector('.ln-icon[data-ln-icon="heart"]');
        if (lnIcon && window.LnIcons) {
            window.LnIcons.set(lnIcon, 'heart', { filled: !!liked });
            if (liked) {
                try { window.LnIcons.play(lnIcon); } catch (e) {}
            }
            return;
        }
        var icon = btn.querySelector('i');
        if (!icon) {
            replaceButtonIcon(btn, likeSvg(liked ? 'heart-filled' : 'heart'));
            return;
        }
        if (icon.classList.contains('fa-heart') || icon.classList.contains('fa-thumbs-up')) {
            replaceButtonIcon(btn, likeSvg(liked ? 'heart-filled' : 'heart'));
        }
    }

    function markMusicLiked(id, count) {
        if (!id) return;
        rememberLiked('music', id);
        syncMusicLikeCount(id, count);
    }

    function hydrateStoredLikeStates(root) {
        root = root || document;
        root.querySelectorAll('.talk-like-btn[data-id]').forEach(function(btn) {
            updateLikeButton(btn, hasLiked('talk', btn.dataset.id || ''));
        });
        root.querySelectorAll('.post-like-btn[data-id]').forEach(function(btn) {
            updateLikeButton(btn, hasLiked('post', btn.dataset.id || ''));
        });
        root.querySelectorAll('.music-share-like-btn[data-music-id]').forEach(function(btn) {
            updateLikeButton(btn, hasLiked('music', btn.dataset.musicId || ''));
        });
        root.querySelectorAll('[data-music-disc-player]').forEach(function(player) {
            player.querySelectorAll('[data-music-like]').forEach(function(btn) {
                updateLikeButton(btn, hasLiked('music', player.dataset.currentId || ''));
            });
        });
    }

    function bindLikeBtn(btn) {
        if (btn.dataset.lnBound) return; btn.dataset.lnBound = '1';
        updateLikeButton(btn, hasLiked('talk', btn.dataset.id || ''));
        btn.addEventListener('click', function() {
            if (btn.disabled) {
                return;
            }
            var id = btn.dataset.id;
            var count = btn.querySelector('.like-count');
            if (hasLiked('talk', id)) {
                updateLikeButton(btn, true);
                frontToast('已经点赞过了', 'success');
                return;
            }
            btn.disabled = true;
            fetch('/talk/' + encodeURIComponent(id) + '/like', {
                method: 'POST',
                headers: frontAjaxHeaders()
            })
                .then(function(res) { return res.json(); })
                .then(function(data) {
                    if (data && (data.code === 0 || data.code === 2)) {
                        if (count) count.textContent = data.likes;
                        rememberLiked('talk', id);
                        updateLikeButton(btn, true);
                        if (data.code === 2) {
                            frontToast(data.msg || '已经点赞过了', 'success');
                            return;
                        }
                        likeConfetti(btn);
                        frontToast('已点赞', 'success');
                    } else {
                        frontToast((data && data.msg) || '点赞失败，请稍后再试', 'error');
                    }
                })
                .catch(function() {
                    frontToast('点赞失败，请稍后再试', 'error');
                })
                .finally(function() {
                    btn.disabled = false;
                });
        });
    }

    function bindPostLikeBtn(btn) {
        if (btn.dataset.lnBound) return; btn.dataset.lnBound = '1';
        updateLikeButton(btn, hasLiked('post', btn.dataset.id || ''));
        btn.addEventListener('click', function() {
            if (btn.disabled) {
                return;
            }
            var id = btn.dataset.id;
            var count = btn.querySelector('.like-count');
            if (hasLiked('post', id)) {
                updateLikeButton(btn, true);
                frontToast('已经点赞过了', 'success');
                return;
            }
            btn.disabled = true;
            fetch('/post/' + encodeURIComponent(id) + '/like', {
                method: 'POST',
                headers: frontAjaxHeaders()
            })
                .then(function(res) { return res.json(); })
                .then(function(data) {
                    if (data && (data.code === 0 || data.code === 2)) {
                        if (count) count.textContent = data.likes;
                        rememberLiked('post', id);
                        document.querySelectorAll('.post-like-btn[data-id="' + String(id).replace(/\\/g, '\\\\').replace(/"/g, '\\"') + '"]').forEach(function(item) {
                            var itemCount = item.querySelector('.like-count');
                            if (itemCount) itemCount.textContent = data.likes;
                            updateLikeButton(item, true);
                        });
                        if (data.code === 2) {
                            frontToast(data.msg || '已经点赞过了', 'success');
                            return;
                        }
                        likeConfetti(btn);
                        frontToast('已点赞', 'success');
                    } else {
                        frontToast((data && data.msg) || '点赞失败，请稍后再试', 'error');
                    }
                })
                .catch(function() {
                    frontToast('点赞失败，请稍后再试', 'error');
                })
                .finally(function() {
                    btn.disabled = false;
                });
        });
    }

    function musicSelectorValue(id) {
        return String(id || '').replace(/\\/g, '\\\\').replace(/"/g, '\\"');
    }

    function syncMusicLikeCount(id, count) {
        if (!id) return;
        var value = String(count || 0);
        var selector = musicSelectorValue(id);

        document.querySelectorAll('[data-music-id="' + selector + '"] [data-music-like-count]').forEach(function(el) {
            el.textContent = value;
        });
        document.querySelectorAll('.music-share-like-btn[data-music-id="' + selector + '"]').forEach(function(el) {
            updateLikeButton(el, true);
        });

        document.querySelectorAll('[data-music-track][data-id="' + selector + '"]').forEach(function(track) {
            track.dataset.likes = value;
        });

        var player = document.querySelector('[data-music-disc-player]');
        if (player && String(player.dataset.currentId || '') === String(id)) {
            player.querySelectorAll('[data-music-likes]').forEach(function(playerLikes) {
                playerLikes.textContent = value;
            });
            player.querySelectorAll('[data-music-like]').forEach(function(playerLikeBtn) {
                updateLikeButton(playerLikeBtn, true);
            });
        }
    }

    function syncMusicCommentCount(id, count) {
        if (!id) return;
        var value = String(count || 0);
        var selector = musicSelectorValue(id);

        document.querySelectorAll('[data-music-id="' + selector + '"] [data-music-comment-count]').forEach(function(el) {
            el.textContent = value;
        });

        document.querySelectorAll('[data-music-comment-thread="' + selector + '"]').forEach(function(thread) {
            thread.dataset.commentCount = value;
            thread.querySelectorAll('[data-music-comment-count]').forEach(function(el) {
                el.textContent = value;
            });
        });

        document.querySelectorAll('[data-music-track][data-id="' + selector + '"]').forEach(function(track) {
            track.dataset.comments = value;
            var trackCount = track.querySelector('[data-music-track-comments]');
            if (trackCount) trackCount.textContent = value;
        });

        var player = document.querySelector('[data-music-disc-player]');
        var commentsRoot = document.querySelector('[data-music-comments]');
        if (player && commentsRoot && String(player.dataset.currentId || '') === String(id)) {
            var headerCount = commentsRoot.querySelector('[data-music-comment-count]');
            if (headerCount) headerCount.textContent = value;
        }
    }

    function bindMusicShareLike(btn) {
        if (btn.dataset.lnBound) return; btn.dataset.lnBound = '1';
        updateLikeButton(btn, hasLiked('music', btn.dataset.musicId || ''));
        btn.addEventListener('click', function() {
            if (btn.disabled) return;
            var id = btn.dataset.musicId || '';
            if (!id) return;
            if (hasLiked('music', id)) {
                updateLikeButton(btn, true);
                frontToast('已经喜欢过这首音乐了', 'success');
                return;
            }

            btn.disabled = true;
            fetch('/music/' + encodeURIComponent(id) + '/like', {
                method: 'POST',
                headers: frontAjaxHeaders()
            }).then(function(res) {
                return res.json();
            }).then(function(data) {
                if (data && (data.code === 0 || data.code === 2)) {
                    markMusicLiked(id, data.likes);
                    if (data.code === 2) {
                        frontToast(data.msg || '已经喜欢过这首音乐了', 'success');
                        return;
                    }
                    likeConfetti(btn);
                    frontToast('已喜欢这首音乐', 'success');
                } else {
                    frontToast((data && data.msg) || '点赞失败，请稍后再试', 'error');
                }
            }).catch(function() {
                frontToast('点赞失败，请稍后再试', 'error');
            }).finally(function() {
                btn.disabled = false;
            });
        });
    }

    var homeMusicAudioState = window.__liteNoteHomeMusicAudio || (window.__liteNoteHomeMusicAudio = {
        audio: null,
        card: null,
        source: '',
        started: Object.create(null)
    });

    function getHomeMusicAudio() {
        if (homeMusicAudioState.audio) {
            return homeMusicAudioState.audio;
        }
        var audio = document.querySelector('audio[data-home-music-global-audio]');
        if (!audio) {
            audio = document.createElement('audio');
            audio.preload = 'none';
            audio.setAttribute('data-home-music-global-audio', '1');
            audio.style.display = 'none';
            document.body.appendChild(audio);
        }
        homeMusicAudioState.audio = audio;
        return audio;
    }

    function pauseHomeMusicAudioExcept(audio) {
        var shared = homeMusicAudioState.audio;
        if (shared && shared !== audio && !shared.paused) {
            shared.pause();
        }
    }

    // 音乐卡片播放器(自定义 audio 控件)
    function bindMusicCard(card) {
        if (card.dataset.lnBound) return; card.dataset.lnBound = '1';
        var inlineAudio = card.querySelector('audio');
        var btn = card.querySelector('.music-card-btn');
        if (!inlineAudio || !btn) return;
        var isHomeMusicCard = card.classList.contains('home-music-player') || !!card.closest('.home-music-card');
        var audio = isHomeMusicCard ? getHomeMusicAudio() : inlineAudio;
        var audioSource = isHomeMusicCard
            ? String(card.dataset.audio || inlineAudio.getAttribute('src') || inlineAudio.currentSrc || inlineAudio.src || '').trim()
            : '';
        var track = card.querySelector('.music-card-track');
        var played = card.querySelector('.music-card-played');
        var curEl = card.querySelector('.music-card-cur');
        var durEl = card.querySelector('.music-card-dur');
        var skipBtns = card.querySelectorAll('[data-music-skip]');
        var lyricsEl = card.querySelector('[data-music-card-lyrics]');
        var recordEl = card.querySelector('.home-music-record');
        var timedLyrics = [];
        var lyricIndex = -1;
        var isSeeking = false;
        var recordSpinDuration = 16;
        var recordAngle = 0;
        var recordRaf = 0;
        var recordLastFrame = 0;

        function ownsHomeMusicAudio() {
            return !isHomeMusicCard || homeMusicAudioState.card === card;
        }

        function syncHomeMusicUiFromSharedAudio(force) {
            if (!isHomeMusicCard || !audioSource || homeMusicAudioState.source !== audioSource) return;
            homeMusicAudioState.card = card;
            if (audio.duration && Number.isFinite(audio.duration) && durEl) {
                durEl.textContent = formatCardDuration(audio.duration);
            }
            if (audio.duration) {
                setCardProgress(audio.currentTime || 0);
            }
            if (!audio.paused && !audio.ended) {
                card.classList.add('playing');
                setMusicToggleIcon(btn, 'pause');
                startRecordSpin();
            } else {
                stopRecordSpin();
                card.classList.remove('playing');
                setMusicToggleIcon(btn, 'play');
                if ((audio.currentTime || 0) > 0.05) {
                    setRecordPose(audio.currentTime);
                } else if (force) {
                    resetRecordPose();
                }
            }
        }

        function formatCardDuration(seconds) {
            seconds = Math.floor(seconds || 0);
            var minutes = Math.floor(seconds / 60);
            var remaining = seconds % 60;
            return minutes + ':' + (remaining < 10 ? '0' : '') + remaining;
        }

        function stripCardLrc(text) {
            return plainLrcText(text);
        }

        function parseCardLrc(text) {
            return parseLrcText(text);
        }

        function isCardLyricMetaLine(line, titleText, artistText) {
            line = String(line || '').trim();
            if (!line) return true;
            if (/^(作词|作曲|编曲|词|曲|制作人|合声|合音|吉他|贝斯|鼓|录音|混音|母带|OP|SP|ISRC|Composer|Lyricist|Artist|Album)[^：:]*[：:]/i.test(line)) {
                return true;
            }
            return !!(titleText && artistText && line.indexOf(titleText) !== -1 && line.indexOf(artistText) !== -1);
        }

        function renderCardLyrics(text) {
            if (!lyricsEl) return;
            var titleText = ((card.querySelector('.music-card-title') || {}).textContent || '').trim();
            var artistText = ((card.querySelector('.music-card-artist') || {}).textContent || '').trim();
            timedLyrics = parseCardLrc(text).filter(function(line) {
                return !isCardLyricMetaLine(line.text, titleText, artistText);
            });
            lyricIndex = -1;
            var lines = timedLyrics.length
                ? timedLyrics.map(function(line) { return line.text; })
                : stripCardLrc(text).split(/\r?\n/).map(function(line) { return line.trim(); }).filter(Boolean);
            lines = lines.filter(function(line) {
                return !isCardLyricMetaLine(line, titleText, artistText);
            });
            if (!lines.length) return;
            lyricsEl.innerHTML = '';
            lines.forEach(function(line, index) {
                var span = document.createElement('span');
                span.textContent = line;
                span.dataset.lyricIndex = String(index);
                lyricsEl.appendChild(span);
            });
            lyricsEl.classList.toggle('has-timed-lyrics', timedLyrics.length > 0);
            updateCardLyric(0, true);
        }

        function updateCardLyric(time, force) {
            if (!lyricsEl) return;
            var spans = Array.prototype.slice.call(lyricsEl.querySelectorAll('span'));
            lyricsEl.style.setProperty('--home-music-lyric-pad-top', '0px');
            if (!timedLyrics.length) {
                if (force) {
                    var fallbackWindowEnd = Math.min(spans.length - 1, 4);
                    spans.forEach(function(span, index) {
                        var distance = Math.abs(index);
                        span.classList.toggle('is-active', index === 0);
                        span.classList.toggle('lyric-distance-1', distance === 1);
                        span.classList.toggle('lyric-distance-2', distance >= 2);
                        span.hidden = index > fallbackWindowEnd;
                    });
                }
                return;
            }
            var next = 0;
            for (var i = 0; i < timedLyrics.length; i += 1) {
                if (timedLyrics[i].time <= time + 0.15) next = i;
                else break;
            }
            if (!force && next === lyricIndex) return;
            lyricIndex = next;
            var windowStart = Math.max(0, next - 2);
            var windowEnd = Math.min(spans.length - 1, next + 2);
            spans.forEach(function(span, index) {
                var distance = Math.abs(index - next);
                span.classList.toggle('is-active', index === next);
                span.classList.toggle('lyric-distance-1', distance === 1);
                span.classList.toggle('lyric-distance-2', distance >= 2);
                span.hidden = false;
                span.classList.toggle('is-outside-window', timedLyrics.length > 5 && (index < windowStart || index > windowEnd));
            });
            scrollLyricContainerToActive(lyricsEl, spans[next]);
        }

        function loadCardLyrics() {
            var localLyrics = decodeBase64(card.dataset.lyrics || '');
            var url = String(card.dataset.lyricsUrl || '').trim();
            if (!url) {
                renderCardLyrics(localLyrics);
                return;
            }
            fetch(lyricFetchUrl(url), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function(res) {
                    if (!res.ok) throw new Error('lyrics failed');
                    return res.text();
                })
                .then(function(text) {
                    renderCardLyrics(text || localLyrics);
                })
                .catch(function() {
                    renderCardLyrics(localLyrics);
                });
        }

        function setCardProgress(time) {
            if (!audio.duration) return;
            if (played) played.style.width = (time / audio.duration * 100) + '%';
            if (curEl) curEl.textContent = formatCardDuration(time);
            updateCardLyric(time, true);
        }

        function recordAngleForTime(time) {
            return ((Math.max(0, time || 0) % recordSpinDuration) / recordSpinDuration) * 360;
        }

        function setRecordTransform(angle) {
            if (!recordEl) return;
            recordAngle = ((angle % 360) + 360) % 360;
            recordEl.style.transform = 'rotate(' + recordAngle.toFixed(3) + 'deg)';
        }

        function setRecordPose(time) {
            setRecordTransform(recordAngleForTime(time));
        }

        function resetRecordPose() {
            if (!recordEl) return;
            recordAngle = 0;
            recordEl.style.transform = '';
        }

        function stopRecordSpin() {
            if (recordRaf) {
                cancelAnimationFrame(recordRaf);
                recordRaf = 0;
            }
            recordLastFrame = 0;
        }

        function spinRecordFrame(ts) {
            if (!recordEl || audio.paused || audio.ended || !ownsHomeMusicAudio()) {
                stopRecordSpin();
                return;
            }
            if (!recordLastFrame) {
                recordLastFrame = ts;
            }
            var elapsed = Math.max(0, ts - recordLastFrame) / 1000;
            recordLastFrame = ts;
            setRecordTransform(recordAngle + (elapsed / recordSpinDuration * 360));
            recordRaf = requestAnimationFrame(spinRecordFrame);
        }

        function startRecordSpin() {
            if (!recordEl || recordRaf) return;
            recordLastFrame = 0;
            recordRaf = requestAnimationFrame(spinRecordFrame);
        }

        function seekFromPointer(e) {
            if (!track || !audio.duration) return;
            var point = e.touches && e.touches[0] ? e.touches[0] : e;
            var rect = track.getBoundingClientRect();
            var ratio = Math.max(0, Math.min(1, (point.clientX - rect.left) / rect.width));
            audio.currentTime = ratio * audio.duration;
            setCardProgress(audio.currentTime);
            if (audio.currentTime <= 0.05) {
                resetRecordPose();
            } else if (audio.paused && !isSeeking) {
                setRecordPose(audio.currentTime);
            }
        }

        function playToggle() {
            card.classList.remove('is-error');
            // 同一时间只播放一个
            document.querySelectorAll('.music-card audio, .music-disc-player audio').forEach(function(a) {
                if (a !== inlineAudio) { a.pause(); }
            });
            pauseHomeMusicAudioExcept(audio);
            if (isHomeMusicCard) {
                if (audioSource && homeMusicAudioState.source !== audioSource) {
                    audio.pause();
                    audio.src = audioSource;
                    audio.load();
                    homeMusicAudioState.source = audioSource;
                }
                homeMusicAudioState.card = card;
            }
            if (audio.paused) {
                openHomeMusicComments();
                var playing = audio.play();
                if (playing && typeof playing.catch === 'function') {
                    playing.catch(function() {
                        card.classList.add('is-error');
                        setMusicToggleIcon(btn, 'error');
                    });
                }
            } else {
                audio.pause();
            }
        }

        function openHomeMusicComments() {
            var homeMusicCard = card.closest('.home-music-card');
            var commentPanel = homeMusicCard ? homeMusicCard.querySelector('.music-share-comments') : null;
            if (!commentPanel) return;
            commentPanel.classList.add('is-open');
            homeMusicCard.classList.add('is-comments-open');
        }

        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            playToggle();
        });
        audio.addEventListener('play', function() {
            if (!ownsHomeMusicAudio()) return;
            card.classList.remove('is-error');
            if (audio.currentTime <= 0.05) {
                resetRecordPose();
            }
            card.classList.add('playing');
            startRecordSpin();
            setMusicToggleIcon(btn, 'pause');
            openHomeMusicComments();
        });
        audio.addEventListener('pause', function() {
            if (!ownsHomeMusicAudio()) return;
            stopRecordSpin();
            card.classList.remove('playing');
            if (!audio.ended) {
                if (audio.currentTime <= 0.05) {
                    resetRecordPose();
                }
            }
            setMusicToggleIcon(btn, 'play');
        });
        audio.addEventListener('ended', function() {
            if (!ownsHomeMusicAudio()) return;
            stopRecordSpin();
            card.classList.remove('playing');
            resetRecordPose();
            setMusicToggleIcon(btn, 'play');
            if (played) played.style.width = '0%';
            if (curEl) curEl.textContent = '0:00';
            updateCardLyric(0, true);
        });
        audio.addEventListener('loadedmetadata', function() {
            if (!ownsHomeMusicAudio()) return;
            if (durEl) durEl.textContent = formatCardDuration(audio.duration);
        });
        audio.addEventListener('error', function() {
            if (!ownsHomeMusicAudio()) return;
            stopRecordSpin();
            card.classList.remove('playing');
            card.classList.add('is-error');
            setMusicToggleIcon(btn, 'error');
            if (durEl) durEl.textContent = '错误';
        });
        audio.addEventListener('timeupdate', function() {
            if (!ownsHomeMusicAudio()) return;
            if (audio.duration && !isSeeking) {
                if (played) played.style.width = (audio.currentTime / audio.duration * 100) + '%';
                if (curEl) curEl.textContent = formatCardDuration(audio.currentTime);
                updateCardLyric(audio.currentTime, false);
            }
        });
        if (track) {
            track.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                seekFromPointer(e);
            });
            track.addEventListener('pointerdown', function(e) {
                e.preventDefault();
                e.stopPropagation();
                if (!audio.duration) return;
                isSeeking = true;
                if (track.setPointerCapture) track.setPointerCapture(e.pointerId);
                seekFromPointer(e);
            });
            track.addEventListener('pointermove', function(e) {
                if (!isSeeking) return;
                e.preventDefault();
                seekFromPointer(e);
            });
            track.addEventListener('pointerup', function(e) {
                if (!isSeeking) return;
                e.preventDefault();
                seekFromPointer(e);
                isSeeking = false;
                if (audio.currentTime <= 0.05) {
                    resetRecordPose();
                } else if (audio.paused) {
                    setRecordPose(audio.currentTime);
                }
                if (track.hasPointerCapture && track.hasPointerCapture(e.pointerId)) {
                    track.releasePointerCapture(e.pointerId);
                }
            });
            track.addEventListener('pointercancel', function(e) {
                isSeeking = false;
                if (track.hasPointerCapture && track.hasPointerCapture(e.pointerId)) {
                    track.releasePointerCapture(e.pointerId);
                }
            });
        }
        skipBtns.forEach(function(skipBtn) {
            skipBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                if (!audio.duration) return;
                var step = parseInt(skipBtn.dataset.musicSkip || '0', 10) || 0;
                audio.currentTime = Math.max(0, Math.min(audio.duration, audio.currentTime + step));
                updateCardLyric(audio.currentTime, true);
                if (audio.currentTime <= 0.05) {
                    resetRecordPose();
                } else if (audio.paused) {
                    setRecordPose(audio.currentTime);
                }
            });
        });
        loadCardLyrics();
        syncHomeMusicUiFromSharedAudio(true);
    }

    function decodeBase64(value) {
        if (!value) return '';
        try {
            var binary = atob(value);
            var bytes = new Uint8Array(binary.length);
            for (var i = 0; i < binary.length; i++) {
                bytes[i] = binary.charCodeAt(i);
            }
            if (window.TextDecoder) {
                return new TextDecoder('utf-8').decode(bytes);
            }
            return decodeURIComponent(escape(binary));
        } catch (e) {
            return '';
        }
    }

    function lrcTimestampPattern() {
        return /\[(\d{1,3}):([0-5]?\d)(?:[.:](\d{1,3}))?\]/g;
    }

    function lrcWordTimestampPattern() {
        return /<\d{1,3}:[0-5]?\d(?:[.:]\d{1,3})?>/g;
    }

    function normalizeLrcSource(text) {
        return String(text || '')
            .replace(/^\uFEFF/, '')
            .replace(/\r\n?/g, '\n');
    }

    function isLrcMetaLine(line) {
        return /^\s*\[(?:ti|ar|al|au|by|offset|length|re|ve|tool|encoding|kana|language):[^\]]*\]\s*$/i.test(line);
    }

    function lrcOffsetSeconds(text) {
        var match = normalizeLrcSource(text).match(/^\s*\[offset:([+-]?\d+)\]\s*$/im);
        return match ? ((parseInt(match[1], 10) || 0) / 1000) : 0;
    }

    function lrcMatchTime(match, offset) {
        var fraction = match[3] || '0';
        var ms = parseInt(fraction.padEnd(3, '0').slice(0, 3), 10) || 0;
        var seconds = (parseInt(match[1], 10) || 0) * 60
            + (parseInt(match[2], 10) || 0)
            + ms / 1000
            + (offset || 0);
        return Math.max(0, seconds);
    }

    function hasTimedLrc(text) {
        return lrcTimestampPattern().test(normalizeLrcSource(text));
    }

    function plainLrcText(text) {
        text = normalizeLrcSource(text);
        if (!hasTimedLrc(text)) {
            return text;
        }
        return text.split('\n').map(function(line) {
            line = line.trim();
            if (!line || isLrcMetaLine(line)) {
                return '';
            }
            return line
                .replace(lrcTimestampPattern(), '')
                .replace(lrcWordTimestampPattern(), '')
                .trim();
        }).filter(Boolean).join('\n');
    }

    function parseLrcText(text) {
        text = normalizeLrcSource(text);
        var offset = lrcOffsetSeconds(text);
        var rows = [];
        var seen = {};
        text.split('\n').forEach(function(line) {
            line = line.trim();
            if (!line || isLrcMetaLine(line)) {
                return;
            }
            var timeRe = lrcTimestampPattern();
            var matches = [];
            var match;
            while ((match = timeRe.exec(line)) !== null) {
                matches.push(match);
            }
            if (!matches.length) {
                return;
            }
            var lyric = line
                .replace(lrcTimestampPattern(), '')
                .replace(lrcWordTimestampPattern(), '')
                .trim();
            if (!lyric) {
                return;
            }
            matches.forEach(function(timeMatch) {
                var time = lrcMatchTime(timeMatch, offset);
                var key = time.toFixed(3) + '\n' + lyric;
                if (seen[key]) {
                    return;
                }
                seen[key] = true;
                rows.push({ time: time, text: lyric });
            });
        });
        rows.sort(function(a, b) {
            if (a.time === b.time) {
                return a.text.localeCompare(b.text);
            }
            return a.time - b.time;
        });
        return rows;
    }

    function scrollLyricContainerToActive(container, active) {
        if (!container || !active) {
            return;
        }
        var scrollToActive = function() {
            var top = active.offsetTop - (container.clientHeight / 2) + (active.offsetHeight / 2);
            container.scrollTop = Math.max(0, top);
        };
        if (window.requestAnimationFrame) {
            window.requestAnimationFrame(scrollToActive);
        } else {
            scrollToActive();
        }
    }

    function lyricFetchUrl(url) {
        url = String(url || '').trim();
        if (!url) {
            return '';
        }
        try {
            var parsed = new URL(url, window.location.href);
            if ((parsed.protocol === 'http:' || parsed.protocol === 'https:') && parsed.origin !== window.location.origin) {
                return '/music/lyrics/fetch?url=' + encodeURIComponent(parsed.href);
            }
        } catch (e) {
            return url;
        }
        return url;
    }

    function bindMusicDiscPlayer(player) {
        if (player.dataset.lnBound) return; player.dataset.lnBound = '1';
        var audio = player.querySelector('audio');
        var playBtns = Array.prototype.slice.call(player.querySelectorAll('[data-music-play]'));
        if (!audio || !playBtns.length) return;

        // 本地时间格式化:外层 formatDuration 在另一个 IIFE 闭包中,此处取不到
        function formatDuration(seconds) {
            seconds = Math.floor(seconds || 0);
            if (!isFinite(seconds) || seconds < 0) seconds = 0;
            var minutes = Math.floor(seconds / 60);
            var remaining = seconds % 60;
            return minutes + ':' + (remaining < 10 ? '0' : '') + remaining;
        }

        var tracks = Array.prototype.slice.call(document.querySelectorAll('[data-music-track]'));
        var prevBtns = Array.prototype.slice.call(player.querySelectorAll('[data-music-prev]'));
        var nextBtns = Array.prototype.slice.call(player.querySelectorAll('[data-music-next]'));
        var likeBtns = Array.prototype.slice.call(player.querySelectorAll('[data-music-like]'));
        var titleEls = Array.prototype.slice.call(player.querySelectorAll('[data-music-title]'));
        var artistEls = Array.prototype.slice.call(player.querySelectorAll('[data-music-artist]'));
        var albumEls = Array.prototype.slice.call(player.querySelectorAll('[data-music-album]'));
        var likesEls = Array.prototype.slice.call(player.querySelectorAll('[data-music-likes]'));
        var lyricsEls = Array.prototype.slice.call(player.querySelectorAll('[data-music-lyrics]'));
        var commentsRoot = document.querySelector('[data-music-comments]');
        var commentTitleEl = commentsRoot ? commentsRoot.querySelector('[data-music-comment-title]') : null;
        var commentCountEl = commentsRoot ? commentsRoot.querySelector('[data-music-comment-count]') : null;
        var commentForm = commentsRoot ? commentsRoot.querySelector('.music-comment-form') : null;
        var coverImgs = Array.prototype.slice.call(player.querySelectorAll('[data-music-cover]'));
        var coverFallbacks = Array.prototype.slice.call(player.querySelectorAll('[data-music-cover-fallback]'));
        var progressTracks = Array.prototype.slice.call(player.querySelectorAll('[data-music-progress]'));
        var progressPlayeds = Array.prototype.slice.call(player.querySelectorAll('[data-music-progress-played]'));
        var currentEls = Array.prototype.slice.call(player.querySelectorAll('[data-music-current]'));
        var durationEls = Array.prototype.slice.call(player.querySelectorAll('[data-music-duration]'));
        var playedIds = {};
        var currentIndex = Math.max(0, parseInt(player.dataset.currentIndex || '0', 10) || 0);
        var currentLyrics = [];
        var currentLyricIndex = -1;
        var lyricsCache = {};
        var lyricsRequestId = 0;

        function setTextAll(nodes, text) {
            nodes.forEach(function(node) {
                node.textContent = text;
            });
        }

        function setProgressWidth(width) {
            progressPlayeds.forEach(function(node) {
                node.style.width = width;
            });
        }

        function setPlayIcon(iconClass) {
            playBtns.forEach(function(btn) {
                setMusicToggleIcon(btn, iconClass);
            });
        }

        function stripLrc(text) {
            return plainLrcText(text);
        }

        function parseLrc(text) {
            return parseLrcText(text);
        }

        function renderLyrics(text, fallback) {
            if (!lyricsEls.length) return;
            lyricsEls.forEach(function(lyricsEl) {
                lyricsEl.innerHTML = '';
                lyricsEl.scrollTop = 0;
            });
            currentLyrics = parseLrc(text);
            currentLyricIndex = -1;
            var lines;
            if (currentLyrics.length) {
                lines = currentLyrics.map(function(line) { return line.text; });
                lyricsEls.forEach(function(lyricsEl) {
                    lyricsEl.classList.add('has-timed-lyrics');
                });
            } else {
                lines = stripLrc(text).split(/\r?\n/)
                    .map(function(line) { return line.trim(); })
                    .filter(Boolean);
                if (!lines.length) {
                    lines = [fallback || '暂无歌词，按下播放让这首歌先响起来。'];
                }
                lyricsEls.forEach(function(lyricsEl) {
                    lyricsEl.classList.remove('has-timed-lyrics');
                });
            }
            lyricsEls.forEach(function(lyricsEl) {
                lines.forEach(function(line, index) {
                    var span = document.createElement('span');
                    span.textContent = line;
                    span.dataset.lyricIndex = String(index);
                    lyricsEl.appendChild(span);
                });
            });
            updateLyric(0, true);
        }

        function loadLyrics(url, fallbackText, fallbackLabel) {
            url = String(url || '').trim();
            fallbackText = String(fallbackText || '');
            var requestId = ++lyricsRequestId;
            if (!url) {
                renderLyrics(fallbackText, fallbackLabel || '');
                return;
            }
            renderLyrics('', '歌词加载中...');
            if (Object.prototype.hasOwnProperty.call(lyricsCache, url)) {
                renderLyrics(lyricsCache[url], fallbackLabel || '');
                return;
            }
            fetch(lyricFetchUrl(url), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function(res) {
                    if (!res.ok) throw new Error('lyrics failed');
                    return res.text();
                })
                .then(function(text) {
                    if (requestId !== lyricsRequestId) return;
                    lyricsCache[url] = text || '';
                    renderLyrics(text || fallbackText, fallbackLabel || '');
                })
                .catch(function() {
                    if (requestId !== lyricsRequestId) return;
                    renderLyrics(fallbackText, fallbackLabel || '');
                });
        }

        function updateLyric(time, force) {
            if (!lyricsEls.length) return;
            if (!currentLyrics.length) {
                if (force) {
                    lyricsEls.forEach(function(lyricsEl) {
                        Array.prototype.slice.call(lyricsEl.querySelectorAll('span')).forEach(function(span, index) {
                            span.classList.toggle('is-active', index === 0);
                        });
                    });
                }
                return;
            }
            var next = 0;
            for (var i = 0; i < currentLyrics.length; i += 1) {
                if (currentLyrics[i].time <= time + 0.15) {
                    next = i;
                } else {
                    break;
                }
            }
            if (!force && next === currentLyricIndex) {
                return;
            }
            currentLyricIndex = next;
            lyricsEls.forEach(function(lyricsEl) {
                var spans = Array.prototype.slice.call(lyricsEl.querySelectorAll('span'));
                spans.forEach(function(span, index) {
                    span.classList.toggle('is-active', index === next);
                });
                var active = spans[next];
                if (active) {
                    scrollLyricContainerToActive(lyricsEl, active);
                }
            });
        }

        function setActiveRow(track) {
            tracks.forEach(function(row) {
                row.classList.toggle('is-active', row === track);
            });
        }

        function applyTrack(index, autoplay) {
            if (!tracks.length) return;
            if (index < 0) index = tracks.length - 1;
            if (index >= tracks.length) index = 0;
            var track = tracks[index];
            var d = track.dataset;
            currentIndex = index;
            player.dataset.currentIndex = String(index);
            player.dataset.currentId = d.id || '';
            player.classList.remove('is-error');
            setActiveRow(track);

            setTextAll(titleEls, d.title || '未命名音乐');
            setTextAll(artistEls, d.artist || '未知歌手');
            setTextAll(albumEls, d.album ? (' · ' + d.album) : '');
            setTextAll(likesEls, d.likes || '0');
            likeBtns.forEach(function(btn) {
                updateLikeButton(btn, hasLiked('music', d.id || ''));
            });
            syncMusicComments(d.id || '', d.title || '未命名音乐', d.comments || '0');
            setTextAll(durationEls, d.duration || '0:00');
            setTextAll(currentEls, '0:00');
            setProgressWidth('0%');
            loadLyrics(d.lyricsUrl || '', decodeBase64(d.lyrics || ''), d.description || '');

            if (coverImgs.length || coverFallbacks.length) {
                if (d.cover) {
                    coverImgs.forEach(function(coverImg) {
                        coverImg.src = d.cover;
                        coverImg.alt = d.title || '';
                        coverImg.classList.remove('is-hidden');
                    });
                    coverFallbacks.forEach(function(coverFallback) {
                        coverFallback.classList.add('is-hidden');
                    });
                } else {
                    coverImgs.forEach(function(coverImg) {
                        coverImg.removeAttribute('src');
                        coverImg.alt = '';
                        coverImg.classList.add('is-hidden');
                    });
                    coverFallbacks.forEach(function(coverFallback) {
                        coverFallback.textContent = (d.title || '♪').trim().slice(0, 1) || '♪';
                        coverFallback.classList.remove('is-hidden');
                    });
                }
            }

            if (audio.src !== d.audio) {
                audio.pause();
                audio.src = d.audio || '';
                audio.load();
            }
            if (autoplay) {
                playAudio();
            }
        }

        function syncMusicComments(id, title, count) {
            if (!commentsRoot) return;
            commentsRoot.querySelectorAll('[data-music-comment-thread]').forEach(function(thread) {
                var active = String(thread.dataset.musicCommentThread || '') === String(id || '');
                thread.classList.toggle('is-active', active);
                thread.hidden = !active;
                if (active) {
                    count = thread.dataset.commentCount || count || '0';
                }
            });
            if (commentTitleEl) commentTitleEl.textContent = title || '音乐';
            if (commentCountEl) commentCountEl.textContent = String(count || '0');
            if (commentForm) {
                var musicInput = commentForm.querySelector('[name=music_id]');
                var parentInput = commentForm.querySelector('[name=parent_id]');
                if (musicInput) musicInput.value = id || '';
                if (parentInput) parentInput.value = '0';
                commentForm.classList.remove('is-replying');
                commentForm.dataset.replyTo = '';
                commentForm.dataset.replyPrefix = '';
                var contentInput = commentForm.querySelector('[name=content]');
                if (contentInput) {
                    contentInput.value = stripReplyMentionPrefix(contentInput.value, '');
                }
            }
        }

        function playAudio() {
            player.classList.remove('is-error');
            document.querySelectorAll('.music-card audio, .music-disc-player audio').forEach(function(a) {
                if (a !== audio) { a.pause(); }
            });
            pauseHomeMusicAudioExcept(audio);
            var playing = audio.play();
            if (playing && typeof playing.catch === 'function') {
                playing.catch(function() {
                    player.classList.add('is-error');
                    setPlayIcon('error');
                });
            }
        }

        function togglePlay() {
            if (audio.paused) {
                playAudio();
            } else {
                audio.pause();
            }
        }

        playBtns.forEach(function(playBtn) {
            playBtn.addEventListener('click', function(e) {
                e.preventDefault();
                togglePlay();
            });
        });

        prevBtns.forEach(function(prevBtn) {
            prevBtn.addEventListener('click', function(e) {
                e.preventDefault();
                applyTrack(currentIndex - 1, true);
            });
        });
        nextBtns.forEach(function(nextBtn) {
            nextBtn.addEventListener('click', function(e) {
                e.preventDefault();
                applyTrack(currentIndex + 1, true);
            });
        });

        tracks.forEach(function(track, index) {
            track.addEventListener('click', function() {
                applyTrack(index, true);
            });
        });

        progressTracks.forEach(function(progressTrack) {
            progressTrack.addEventListener('click', function(e) {
                if (!audio.duration) return;
                var rect = progressTrack.getBoundingClientRect();
                audio.currentTime = Math.max(0, Math.min(1, (e.clientX - rect.left) / rect.width)) * audio.duration;
                updateLyric(audio.currentTime, true);
            });
        });

        if (likeBtns.length) {
            likeBtns.forEach(function(likeBtn) {
                updateLikeButton(likeBtn, hasLiked('music', player.dataset.currentId || ''));
            });
            likeBtns.forEach(function(likeBtn) {
            likeBtn.addEventListener('click', function(e) {
                e.preventDefault();
                if (likeBtn.disabled) return;
                var id = player.dataset.currentId || '';
                if (!id) return;
                if (hasLiked('music', id)) {
                    likeBtns.forEach(function(btn) { updateLikeButton(btn, true); });
                    frontToast('已经喜欢过这首音乐了', 'success');
                    return;
                }
                likeBtns.forEach(function(btn) { btn.disabled = true; });
                fetch('/music/' + encodeURIComponent(id) + '/like', {
                    method: 'POST',
                    headers: frontAjaxHeaders()
                }).then(function(res) {
                    return res.json();
                }).then(function(data) {
                    if (data && (data.code === 0 || data.code === 2)) {
                        setTextAll(likesEls, data.likes);
                        var active = tracks[currentIndex];
                        if (active) active.dataset.likes = String(data.likes);
                        markMusicLiked(id, data.likes);
                        if (data.code === 2) {
                            frontToast(data.msg || '已经喜欢过这首音乐了', 'success');
                            return;
                        }
                        likeConfetti(likeBtn);
                        frontToast('已喜欢这首音乐', 'success');
                    } else {
                        frontToast((data && data.msg) || '点赞失败，请稍后再试', 'error');
                    }
                }).catch(function() {
                    frontToast('点赞失败，请稍后再试', 'error');
                }).finally(function() {
                    likeBtns.forEach(function(btn) { btn.disabled = false; });
                });
            });
            });
        }

        var rafId = 0;
        function renderProgress() {
            var t = formatDuration(audio.currentTime);
            var liveCur = player.querySelectorAll('[data-music-current]');
            for (var ci = 0; ci < liveCur.length; ci += 1) { liveCur[ci].textContent = t; }
            if (audio.duration && Number.isFinite(audio.duration)) {
                var w = (audio.currentTime / audio.duration * 100) + '%';
                var livePlayed = player.querySelectorAll('[data-music-progress-played]');
                for (var pi = 0; pi < livePlayed.length; pi += 1) { livePlayed[pi].style.width = w; }
            }
            updateLyric(audio.currentTime, false);
        }
        function startRaf() {
            if (rafId) return;
            (function loop() {
                renderProgress();
                rafId = (!audio.paused && !audio.ended) ? window.requestAnimationFrame(loop) : 0;
            })();
        }
        function stopRaf() {
            if (rafId) { window.cancelAnimationFrame(rafId); rafId = 0; }
        }
        player.__lnRenderProgress = renderProgress;

        audio.addEventListener('play', function() {
            player.classList.remove('is-error');
            player.classList.add('is-playing');
            setPlayIcon('pause');
            startRaf();
            var id = player.dataset.currentId || '';
            if (id && !playedIds[id]) {
                playedIds[id] = true;
                fetch('/music/' + encodeURIComponent(id) + '/play', {
                    method: 'POST',
                    headers: frontAjaxHeaders()
                }).catch(function() {});
            }
        });

        audio.addEventListener('pause', function() {
            player.classList.remove('is-playing');
            setPlayIcon('play');
            stopRaf();
            renderProgress();
        });

        audio.addEventListener('ended', function() {
            player.classList.remove('is-playing');
            setPlayIcon('play');
            stopRaf();
            if (tracks.length > 1) {
                applyTrack(currentIndex + 1, true);
            } else {
                setProgressWidth('0%');
                setTextAll(currentEls, '0:00');
            }
        });

        audio.addEventListener('loadedmetadata', function() {
            if (audio.duration && Number.isFinite(audio.duration)) {
                setTextAll(durationEls, formatDuration(audio.duration));
            }
        });

        audio.addEventListener('timeupdate', renderProgress);
        audio.addEventListener('seeked', renderProgress);
        audio.addEventListener('loadeddata', renderProgress);

        audio.addEventListener('error', function() {
            player.classList.remove('is-playing');
            player.classList.add('is-error');
            setPlayIcon('error');
        });

        if (tracks.length) {
            applyTrack(currentIndex, false);
        }
    }

    function bindMusicCommentsToggle(btn) {
        if (btn.dataset.lnBound) return; btn.dataset.lnBound = '1';
        btn.addEventListener('click', function() {
            var root = btn.closest('[data-music-comments]');
            if (!root) return;
            var collapsed = !root.classList.contains('is-collapsed');
            if (!collapsed && commentScopeNeedsIdentity(root) && !hasUsableCommentIdentity()) {
                openNavIdentityDialog({
                    onSave: function() {
                        root.classList.remove('is-collapsed');
                        btn.setAttribute('aria-label', '收起音乐评论');
                    }
                });
                return;
            }
            root.classList.toggle('is-collapsed', collapsed);
            btn.setAttribute('aria-label', collapsed ? '展开音乐评论' : '收起音乐评论');
        });
    }

    function bindMusicCommentsClose(btn) {
        if (btn.dataset.lnBound) return; btn.dataset.lnBound = '1';
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var panel = btn.closest('.music-share-comments');
            if (panel) {
                panel.classList.remove('is-open');
                var card = panel.closest('.home-music-card');
                if (card) card.classList.remove('is-comments-open');
            }
        });
    }

    function bindArticleCommentIdentityPrompt(root) {
        root = root || document;
        var section = root.querySelector('.post-detail .comments');
        if (!section || section.dataset.lnIdentityPromptBound) return;
        section.dataset.lnIdentityPromptBound = '1';
        var form = section.querySelector('.comment-form');
        if (form && form.dataset.commentAdmin === '1') return;
        if (hasUsableCommentIdentity()) return;

        function promptOnce() {
            if (section.dataset.lnIdentityPrompted === '1' || hasUsableCommentIdentity()) return;
            section.dataset.lnIdentityPrompted = '1';
            openNavIdentityDialog();
        }

        if ('IntersectionObserver' in window) {
            var observer = new IntersectionObserver(function(entries) {
                entries.forEach(function(entry) {
                    if (!entry.isIntersecting) return;
                    observer.disconnect();
                    promptOnce();
                });
            }, { rootMargin: '0px 0px -25% 0px', threshold: 0.16 });
            observer.observe(section);
        } else {
            var onScroll = function() {
                var rect = section.getBoundingClientRect();
                if (rect.top < window.innerHeight * 0.78 && rect.bottom > 0) {
                    window.removeEventListener('scroll', onScroll);
                    promptOnce();
                }
            };
            window.addEventListener('scroll', onScroll, { passive: true });
            onScroll();
        }
    }

    // 发布表单:图片上传按钮
    function bindPublishForm(form) {
        if (form.dataset.lnBound) return; form.dataset.lnBound = '1';
        var btn = form.querySelector('.fp-upload-btn');
        var file = form.querySelector('.fp-upload-file');
        var imagesInput = form.querySelector('input[name="images"]');
        var musicBtn = form.querySelector('.fp-music-btn');
        var musicPanel = form.querySelector('.front-publish-music');
        var musicSelect = form.querySelector('select[name="music_id"]');
        var xBtn = form.querySelector('.fp-x-btn');
        var xPanel = form.querySelector('.front-publish-x');
        var postTypeInput = form.querySelector('[data-post-type-input]');
        var locationBtn = form.querySelector('.fp-location-btn');
        var locationPanel = form.querySelector('.front-publish-location');
        var locationInput = form.querySelector('.fp-location-input');
        var locationResults = form.querySelector('.fp-location-results');
        var locationCurrent = form.querySelector('.fp-location-current');
        var locationClear = form.querySelector('.fp-location-clear');
        var locationLabel = form.querySelector('[data-location-label]');
        var weatherBtn = form.querySelector('.fp-weather-btn');
        var weatherLabel = form.querySelector('[data-weather-label]');
        var mapboxToken = String(form.dataset.mapboxToken || '').trim();
        var locationFields = {
            name: form.querySelector('input[name="location_name"]'),
            city: form.querySelector('input[name="location_city"]'),
            lat: form.querySelector('input[name="location_lat"]'),
            lng: form.querySelector('input[name="location_lng"]'),
            provider: form.querySelector('input[name="location_provider"]'),
            data: form.querySelector('input[name="location_data"]')
        };
        var weatherFields = {
            label: form.querySelector('input[name="weather_label"]'),
            icon: form.querySelector('input[name="weather_icon"]'),
            temp: form.querySelector('input[name="weather_temp"]'),
            code: form.querySelector('input[name="weather_code"]'),
            data: form.querySelector('input[name="weather_data"]')
        };
        function setWeather(weather) {
            var label = String((weather && weather.label) || '').trim();
            var icon = String((weather && weather.icon) || '').trim();
            var temp = weather && weather.temperature !== undefined ? String(weather.temperature).trim() : '';
            var text = label ? (label + (temp !== '' ? ' ' + temp + '°C' : '')) : '天气';
            if (weatherFields.label) weatherFields.label.value = label;
            if (weatherFields.icon) weatherFields.icon.value = icon;
            if (weatherFields.temp) weatherFields.temp.value = temp;
            if (weatherFields.code) weatherFields.code.value = weather && weather.code !== undefined ? String(weather.code) : '';
            if (weatherFields.data) weatherFields.data.value = label ? JSON.stringify(weather || {}) : '';
            if (weatherLabel) weatherLabel.textContent = text;
            if (weatherBtn) {
                weatherBtn.classList.toggle('is-active', !!label);
                var i = weatherBtn.querySelector('i');
                if (i && icon) i.className = icon;
            }
        }
        function requestWeather(lat, lng, place) {
            var params = new URLSearchParams();
            if (lat && lng) {
                params.set('lat', lat);
                params.set('lng', lng);
            }
            if (place) params.set('place', place);
            return fetch('/talk/weather?' + params.toString(), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            }).then(function(resp) {
                return resp.json().then(function(data) {
                    if (!resp.ok || !data || data.code !== 0) {
                        throw new Error((data && data.msg) || '天气获取失败');
                    }
                    return data.data;
                });
            });
        }
        function fetchWeatherForPublish() {
            var lat = locationFields.lat && locationFields.lat.value;
            var lng = locationFields.lng && locationFields.lng.value;
            var place = (locationFields.name && locationFields.name.value) || (locationFields.city && locationFields.city.value) || '';
            if (lat && lng) {
                return requestWeather(lat, lng, place);
            }
            if (!navigator.geolocation) {
                return requestWeather('', '', '');
            }
            return new Promise(function(resolve, reject) {
                navigator.geolocation.getCurrentPosition(function(pos) {
                    requestWeather(pos.coords.latitude, pos.coords.longitude, place).then(resolve, reject);
                }, function() {
                    requestWeather('', '', '').then(resolve, reject);
                }, { enableHighAccuracy: false, timeout: 9000, maximumAge: 600000 });
            });
        }
        function hasSelectedLocation() {
            return !!(locationFields.lat && locationFields.lng && locationFields.lat.value && locationFields.lng.value);
        }
        function promptLocationForWeather(message) {
            form.dataset.weatherPending = '1';
            if (locationPanel && locationBtn) {
                locationPanel.hidden = false;
                locationBtn.setAttribute('aria-expanded', 'true');
            }
            if (locationInput) {
                setTimeout(function() { locationInput.focus(); }, 0);
            }
            if (locationResults) {
                locationResults.innerHTML = '';
                var empty = document.createElement('div');
                empty.className = 'fp-location-empty';
                empty.textContent = message || '先搜索并选择位置，或点“当前”定位，再添加天气';
                locationResults.appendChild(empty);
                locationResults.hidden = false;
            }
        }
        if (weatherBtn) {
            weatherBtn.addEventListener('click', function() {
                var original = weatherBtn.innerHTML;
                weatherBtn.disabled = true;
                weatherBtn.innerHTML = window.siteLoadingSpinnerSvg ? window.siteLoadingSpinnerSvg('fp-weather-spinner') + '<span>天气中</span>' : '<span>天气中</span>';
                fetchWeatherForPublish().then(function(weather) {
                    setWeather(weather);
                    frontToast('天气已添加', 'success');
                }).catch(function(err) {
                    var message = (err && err.message) || '天气获取失败';
                    frontToast(message, 'error');
                    if (!hasSelectedLocation()) {
                        promptLocationForWeather('本地 IP 暂时无法获取天气，请先搜索位置或点“当前”定位');
                        weatherBtn.innerHTML = '<i class="fa-solid fa-location-dot" aria-hidden="true"></i><span data-weather-label>选位置</span>';
                        weatherLabel = weatherBtn.querySelector('[data-weather-label]');
                    }
                }).finally(function() {
                    weatherBtn.disabled = false;
                    if (!weatherFields.label || !weatherFields.label.value) {
                        if (!form.dataset.weatherPending) {
                            weatherBtn.innerHTML = original;
                        }
                    } else {
                        var text = weatherLabel ? weatherLabel.textContent : '天气';
                        weatherBtn.innerHTML = '<i class="fa-solid fa-cloud-sun" aria-hidden="true"></i><span data-weather-label>' + text + '</span>';
                        weatherLabel = weatherBtn.querySelector('[data-weather-label]');
                    }
                });
            });
        }
        if (locationBtn && locationPanel && locationInput) {
            var searchTimer = null;
            var searchAbort = null;

            function setLocation(place) {
                var name = String((place && place.name) || '').trim();
                var city = String((place && place.city) || name).trim();
                var display = name || city;
                var fullName = String((place && (place.fullName || place.full_name || place.place_name)) || display).trim();
                var lat = String((place && place.lat) || '').trim();
                var lng = String((place && place.lng) || '').trim();
                var provider = String((place && place.provider) || (lat && lng ? 'mapbox' : 'manual')).trim();
                if (locationFields.name) locationFields.name.value = name;
                if (locationFields.city) locationFields.city.value = city;
                if (locationFields.lat) locationFields.lat.value = lat;
                if (locationFields.lng) locationFields.lng.value = lng;
                if (locationFields.provider) locationFields.provider.value = name ? provider : '';
                if (locationFields.data) {
                    locationFields.data.value = name ? JSON.stringify({
                        id: place && place.id || '',
                        name: name,
                        city: city,
                        full_name: fullName,
                        lat: lat,
                        lng: lng,
                        provider: provider
                    }) : '';
                }
                if (locationLabel) locationLabel.textContent = display || '位置';
                locationBtn.classList.toggle('is-active', !!name);
                if (name) {
                    locationInput.value = display;
                    locationPanel.hidden = true;
                    locationBtn.setAttribute('aria-expanded', 'false');
                    if (weatherBtn && form.dataset.weatherPending === '1') {
                        form.dataset.weatherPending = '';
                        setTimeout(function() { weatherBtn.click(); }, 80);
                    }
                }
            }

            function cityFromFeature(feature) {
                var context = Array.isArray(feature && feature.context) ? feature.context : [];
                var city = '';
                context.some(function(item) {
                    var id = String(item.id || '');
                    if (id.indexOf('place.') === 0 || id.indexOf('locality.') === 0 || id.indexOf('region.') === 0) {
                        city = String(item.text || item.text_zh || item.short_code || '').trim();
                        return city !== '';
                    }
                    return false;
                });
                return city || String((feature && feature.text) || '').trim();
            }

            function featureToPlace(feature) {
                var center = Array.isArray(feature && feature.center) ? feature.center : [];
                var city = cityFromFeature(feature);
                var shortName = String((feature && (feature.text_zh || feature.text)) || city || '').trim();
                var fullName = String((feature && (feature.place_name_zh || feature.place_name)) || shortName || city || '').trim();
                return {
                    id: feature && feature.id || '',
                    name: shortName || fullName,
                    city: city,
                    fullName: fullName,
                    lng: center[0] !== undefined ? String(center[0]) : '',
                    lat: center[1] !== undefined ? String(center[1]) : '',
                    provider: 'mapbox'
                };
            }

            function renderLocationResults(features, emptyText) {
                if (!locationResults) return;
                locationResults.innerHTML = '';
                var list = Array.isArray(features) ? features : [];
                if (!list.length) {
                    if (emptyText) {
                        var empty = document.createElement('div');
                        empty.className = 'fp-location-empty';
                        empty.textContent = emptyText;
                        locationResults.appendChild(empty);
                        locationResults.hidden = false;
                    } else {
                        locationResults.hidden = true;
                    }
                    return;
                }
                list.slice(0, 6).forEach(function(feature) {
                    var place = featureToPlace(feature);
                    var item = document.createElement('button');
                    item.type = 'button';
                    item.className = 'fp-location-result';
                    item.innerHTML = '<i class="fa-solid fa-location-dot" aria-hidden="true"></i><span></span>';
                    item.querySelector('span').textContent = place.name;
                    item.addEventListener('click', function() { setLocation(place); });
                    locationResults.appendChild(item);
                });
                locationResults.hidden = false;
            }

            function mapboxFetch(url) {
                if (searchAbort) searchAbort.abort();
                searchAbort = new AbortController();
                return fetch(url, { signal: searchAbort.signal }).then(function(resp) {
                    if (!resp.ok) throw new Error('mapbox request failed');
                    return resp.json();
                });
            }

            function searchLocation(query) {
                query = String(query || '').trim();
                if (!query) {
                    renderLocationResults([]);
                    return;
                }
                if (!mapboxToken) {
                    renderLocationResults([], '需要先配置 Mapbox Token');
                    return;
                }
                var url = 'https://api.mapbox.com/geocoding/v5/mapbox.places/'
                    + encodeURIComponent(query)
                    + '.json?access_token=' + encodeURIComponent(mapboxToken)
                    + '&language=zh-Hans&limit=6&types=place,locality,region,country';
                mapboxFetch(url).then(function(data) {
                    renderLocationResults(data && data.features, '没有找到这个位置');
                }).catch(function(err) {
                    if (err && err.name === 'AbortError') return;
                    renderLocationResults([], '位置搜索失败，请稍后重试');
                });
            }

            function reverseLocation(lat, lng) {
                if (!mapboxToken) {
                    frontToast('需要先在后台基础设置填入 Mapbox 公开 Token', 'error');
                    return;
                }
                var url = 'https://api.mapbox.com/geocoding/v5/mapbox.places/'
                    + encodeURIComponent(lng + ',' + lat)
                    + '.json?access_token=' + encodeURIComponent(mapboxToken)
                    + '&language=zh-Hans&limit=1&types=place,locality,region,country';
                renderLocationResults([], '定位中...');
                mapboxFetch(url).then(function(data) {
                    var feature = data && data.features && data.features[0];
                    if (!feature) {
                        renderLocationResults([], '没有识别到城市，请搜索后选择候选位置');
                        return;
                    }
                    setLocation(featureToPlace(feature));
                }).catch(function(err) {
                    if (err && err.name === 'AbortError') return;
                    renderLocationResults([], '定位失败，请搜索后选择候选位置');
                });
            }

            locationBtn.addEventListener('click', function() {
                var open = locationPanel.hidden;
                locationPanel.hidden = !open;
                locationBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
                if (open) setTimeout(function() { locationInput.focus(); }, 0);
            });
            locationInput.addEventListener('input', function() {
                clearTimeout(searchTimer);
                searchTimer = setTimeout(function() { searchLocation(locationInput.value); }, mapboxToken ? 260 : 0);
            });
            locationInput.addEventListener('keydown', function(e) {
                if (e.key !== 'Enter') return;
                e.preventDefault();
                searchLocation(locationInput.value);
            });
            if (locationCurrent) {
                locationCurrent.addEventListener('click', function() {
                    if (!navigator.geolocation) {
                        frontToast('当前浏览器不支持定位', 'error');
                        return;
                    }
                    navigator.geolocation.getCurrentPosition(function(pos) {
                        reverseLocation(pos.coords.latitude, pos.coords.longitude);
                    }, function() {
                        frontToast('没有获得定位权限', 'error');
                    }, { enableHighAccuracy: false, timeout: 9000, maximumAge: 600000 });
                });
            }
            if (locationClear) {
                locationClear.addEventListener('click', function() {
                    locationInput.value = '';
                    renderLocationResults([]);
                    setLocation(null);
                    setWeather(null);
                });
            }
        }
        if (musicBtn && musicPanel) {
            function syncMusicButton(open) {
                var hasMusic = musicSelect && String(musicSelect.value || '0') !== '0';
                musicBtn.classList.toggle('is-open', !!open);
                musicBtn.classList.toggle('is-active', hasMusic);
                musicBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
                musicBtn.title = hasMusic ? '已关联音乐' : '选择关联音乐';
            }
            musicBtn.addEventListener('click', function() {
                var nextOpen = musicPanel.hidden;
                musicPanel.hidden = !nextOpen;
                if (nextOpen && musicSelect) {
                    setTimeout(function() { musicSelect.focus(); }, 0);
                }
                syncMusicButton(nextOpen);
            });
            if (musicSelect) {
                musicSelect.addEventListener('change', function() {
                    syncMusicButton(!musicPanel.hidden);
                });
            }
            syncMusicButton(!musicPanel.hidden);
        }
        if (xBtn && xPanel) {
            function hasXValue() {
                return Array.prototype.some.call(xPanel.querySelectorAll('input[type="text"]'), function(input) {
                    return String(input.value || '').trim() !== '';
                });
            }
            function syncXButton(open) {
                var active = !!open || hasXValue();
                xBtn.classList.toggle('is-open', !!open);
                xBtn.classList.toggle('is-active', active);
                xBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
                xBtn.title = active ? '正在发布 X 卡片' : '添加 X 卡片';
                if (postTypeInput) {
                    postTypeInput.value = active ? 'tweet' : 'talk';
                }
            }
            xBtn.addEventListener('click', function() {
                var nextOpen = xPanel.hidden;
                xPanel.hidden = !nextOpen;
                if (nextOpen) {
                    var first = xPanel.querySelector('input[type="text"]');
                    if (first) setTimeout(function() { first.focus(); }, 0);
                }
                syncXButton(nextOpen);
            });
            xPanel.querySelectorAll('input').forEach(function(input) {
                input.addEventListener('input', function() {
                    syncXButton(!xPanel.hidden);
                });
                input.addEventListener('change', function() {
                    syncXButton(!xPanel.hidden);
                });
            });
            syncXButton(!xPanel.hidden);
        }

        form.addEventListener('submit', function(e) {
            e.preventDefault();
            if (form.dataset.submitting === '1') return;
            var submitBtn = form.querySelector('.publish-btn');
            var originalText = submitBtn ? submitBtn.textContent : '';
            var formData = new FormData(form);
            form.dataset.submitting = '1';
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = '发布中...';
            }

            fetch(form.action || '/talk/publish', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            }).then(function(resp) {
                return resp.json().then(function(data) {
                    if (!resp.ok) {
                        data = data || {};
                        data.code = data.code || resp.status;
                    }
                    return data;
                });
            }).then(function(data) {
                if (!data || data.code !== 0) {
                    frontToast((data && data.msg) || '发布失败，请稍后重试', 'error');
                    return;
                }
                var list = document.querySelector('.talk-list .js-list-items') || document.querySelector('.js-list-items');
                if (list && data.html) {
                    var tpl = document.createElement('template');
                    tpl.innerHTML = data.html;
                    var card = tpl.content.firstElementChild;
                    if (card) {
                        var empty = list.querySelector('.empty');
                        if (empty) empty.remove();
                        card.classList.add('is-newly-published');
                        card.style.overflow = 'hidden';
                        card.style.maxHeight = '0px';
                        card.style.opacity = '0';
                        list.insertBefore(card, list.firstChild);
                        bindDynamic(card);
                        var targetHeight = card.scrollHeight;
                        requestAnimationFrame(function() {
                            card.style.transition = 'max-height .32s ease, opacity .24s ease, transform .32s ease';
                            card.style.maxHeight = targetHeight + 'px';
                            card.style.opacity = '1';
                            card.style.transform = 'translateY(0)';
                        });
                        setTimeout(function() {
                            card.style.maxHeight = '';
                            card.style.overflow = '';
                            card.style.transition = '';
                            card.classList.remove('is-newly-published');
                            card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                        }, 380);
                    }
                }
                form.reset();
                if (imagesInput) imagesInput.value = '';
                if (locationInput) locationInput.value = '';
                if (locationLabel) locationLabel.textContent = '位置';
                if (locationBtn) {
                    locationBtn.classList.remove('is-active');
                    locationBtn.setAttribute('aria-expanded', 'false');
                }
                if (locationPanel) locationPanel.hidden = true;
                Object.keys(locationFields).forEach(function(key) {
                    if (locationFields[key]) locationFields[key].value = '';
                });
                setWeather(null);
                frontToast(data.msg || '滔客已发布', 'success');
            }).catch(function() {
                frontToast('网络错误，发布失败', 'error');
            }).finally(function() {
                form.dataset.submitting = '';
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText || '发布';
                }
            });
        });

        if (!btn || !file || !imagesInput) return;
        var row = form.querySelector('.front-publish-images');
        var status = form.querySelector('.fp-upload-status');
        var progress = status ? status.querySelector('.fp-upload-progress span') : null;
        var percent = status ? status.querySelector('.fp-upload-percent') : null;
        var csrfEl = form.querySelector('input[name="_csrf"]');
        var csrf = csrfEl ? csrfEl.value : '';
        var hideStatusTimer = null;

        function setUploadProgress(value, text) {
            value = Math.max(0, Math.min(100, value || 0));
            if (status) status.hidden = false;
            if (progress) progress.style.width = value + '%';
            if (percent) percent.textContent = text || (value + '%');
        }

        function setUploading(active) {
            if (active && hideStatusTimer) {
                clearTimeout(hideStatusTimer);
                hideStatusTimer = null;
            }
            if (status && active) {
                status.classList.remove('is-done', 'is-error');
            }
            btn.disabled = active;
            if (row) row.classList.toggle('is-uploading', active);
        }

        function finishUploadStatus(message, delay, type) {
            if (status) {
                status.classList.toggle('is-done', type === 'done');
                status.classList.toggle('is-error', type === 'error');
            }
            setUploadProgress(100, message || '100%');
            hideStatusTimer = setTimeout(function() {
                if (status) status.hidden = true;
                if (status) status.classList.remove('is-done', 'is-error');
                if (progress) progress.style.width = '0%';
                if (percent) percent.textContent = '0%';
            }, delay || 1200);
        }

        btn.addEventListener('click', function() { file.click(); });
        file.addEventListener('change', function() {
            var f = file.files && file.files[0];
            if (!f) return;
            var orig = btn.innerHTML;
            setUploading(true);
            setUploadProgress(0, '0%');
            var uploadToast = frontUploadToast(f.name || '');
            uploadToast.progress(3);
            btn.innerHTML = window.siteLoadingSpinnerSvg('fp-upload-spinner') + '<span>上传中</span>';
            var data = new FormData();
            data.append('_csrf', csrf);
            data.append('purpose', 'talk');
            data.append('image', f);

            var xhr = new XMLHttpRequest();
            xhr.open('POST', '/talk/upload-image');
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.upload.addEventListener('progress', function(e) {
                if (!e.lengthComputable) {
                    setUploadProgress(8, '上传中');
                    return;
                }
                var value = Math.max(1, Math.min(99, Math.round(e.loaded / e.total * 100)));
                setUploadProgress(value, value + '%');
                uploadToast.progress(value);
            });
            xhr.addEventListener('load', function() {
                var d = null;
                try {
                    d = JSON.parse(xhr.responseText || '{}');
                } catch (e) {}
                if (xhr.status >= 200 && xhr.status < 300) {
                    if (d && d.code === 0 && d.data && d.data.url) {
                        var cur = imagesInput.value.trim().replace(/,\s*$/, '');
                        imagesInput.value = cur ? (cur + ',' + d.data.url) : d.data.url;
                        finishUploadStatus('完成', 1200, 'done');
                        uploadToast.success('图片上传成功');
                    } else {
                        finishUploadStatus('失败', 1600, 'error');
                        uploadToast.error((d && d.msg) || '上传失败');
                    }
                    return;
                }
                finishUploadStatus('失败', 1600, 'error');
                uploadToast.error('上传失败');
            });
            xhr.addEventListener('error', function() {
                finishUploadStatus('失败', 1600, 'error');
                uploadToast.error('上传失败');
            });
            xhr.addEventListener('abort', function() {
                finishUploadStatus('已取消', 1200, 'error');
                uploadToast.error('已取消');
            });
            xhr.addEventListener('loadend', function() {
                setUploading(false);
                btn.innerHTML = orig;
                file.value = '';
            });
            xhr.send(data);
        });
    }

    function absoluteCopyUrl(value) {
        try {
            return new URL(value || '/rss.xml', window.location.origin).href;
        } catch (e) {
            return window.location.origin + '/rss.xml';
        }
    }

    function fallbackCopy(text) {
        var input = document.createElement('textarea');
        input.value = text;
        input.setAttribute('readonly', '');
        input.style.position = 'fixed';
        input.style.left = '-9999px';
        input.style.top = '0';
        document.body.appendChild(input);
        input.select();

        var ok = false;
        try {
            ok = document.execCommand('copy');
        } catch (e) {
            ok = false;
        }

        input.remove();
        return ok ? Promise.resolve() : Promise.reject(new Error('copy failed'));
    }

    function copyText(text) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            return navigator.clipboard.writeText(text).catch(function() {
                return fallbackCopy(text);
            });
        }

        return fallbackCopy(text);
    }

    function frontToast(message, type) {
        var toastType = type === 'error' ? 'error' : 'success';
        var old = document.querySelector('.front-copy-toast');
        if (old) {
            old.remove();
        }

        var toast = document.createElement('div');
        toast.className = 'front-copy-toast site-toast site-toast-' + toastType + ' front-copy-toast-' + toastType;
        toast.setAttribute('role', toastType === 'error' ? 'alert' : 'status');
        toast.setAttribute('aria-live', toastType === 'error' ? 'assertive' : 'polite');
        toast.innerHTML = '<i class="fa-solid ' + (toastType === 'error' ? 'fa-triangle-exclamation' : 'fa-circle-check') + '" aria-hidden="true"></i><span></span>';
        toast.querySelector('span').textContent = message;
        document.body.appendChild(toast);

        requestAnimationFrame(function() {
            toast.classList.add('is-visible');
        });

        setTimeout(function() {
            toast.classList.remove('is-visible');
            setTimeout(function() {
                if (toast.parentNode) {
                    toast.remove();
                }
            }, 180);
        }, 2200);
    }

    function compactUploadFilename(filename) {
        var value = String(filename || '').trim();
        var chars = Array.from(value);
        if (chars.length <= 24) {
            return value;
        }
        var dot = value.lastIndexOf('.');
        var ext = dot > 0 && value.length - dot <= 10 ? value.slice(dot) : '';
        var stem = ext ? value.slice(0, -ext.length) : value;
        var stemChars = Array.from(stem);
        var head = stemChars.slice(0, 12).join('');
        var tail = stemChars.slice(Math.max(12, stemChars.length - 6)).join('');
        return head + '...' + tail + ext;
    }

    function frontUploadToast(filename) {
        var toast = document.createElement('div');
        toast.className = 'front-copy-toast front-upload-toast site-toast';
        toast.setAttribute('role', 'status');
        toast.setAttribute('aria-live', 'polite');
        toast.innerHTML = ''
            + '<span class="front-upload-toast-icon">' + window.siteLoadingSpinnerSvg('front-upload-toast-spinner') + '</span>'
            + '<span class="front-upload-toast-body">'
            + '  <span class="front-upload-toast-head"><span class="front-upload-toast-title"></span><strong class="front-upload-toast-percent">0%</strong></span>'
            + '  <span class="front-upload-toast-bar"><span></span></span>'
            + '</span>';
        var title = toast.querySelector('.front-upload-toast-title');
        title.textContent = filename ? ('上传 ' + compactUploadFilename(filename)) : '正在上传';
        if (filename) {
            title.title = filename;
        }
        document.body.appendChild(toast);

        var bar = toast.querySelector('.front-upload-toast-bar span');
        var percent = toast.querySelector('.front-upload-toast-percent');
        var icon = toast.querySelector('.front-upload-toast-icon');

        requestAnimationFrame(function() {
            toast.classList.add('is-visible');
        });

        function close(delay) {
            setTimeout(function() {
                toast.classList.remove('is-visible');
                setTimeout(function() {
                    if (toast.parentNode) toast.remove();
                }, 180);
            }, delay || 0);
        }

        return {
            progress: function(value) {
                value = Math.max(0, Math.min(100, Math.round(value || 0)));
                if (bar) bar.style.width = value + '%';
                if (percent) percent.textContent = value + '%';
            },
            success: function(message) {
                toast.classList.add('front-upload-toast-success');
                if (icon) icon.innerHTML = '<i class="fa-regular fa-circle-check" aria-hidden="true"></i>';
                if (bar) bar.style.width = '100%';
                if (percent) percent.textContent = '100%';
                toast.querySelector('.front-upload-toast-title').textContent = message || '上传完成';
                close(1400);
            },
            error: function(message) {
                toast.classList.add('front-upload-toast-error');
                if (icon) icon.innerHTML = '<i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>';
                toast.querySelector('.front-upload-toast-title').textContent = message || '上传失败';
                close(3000);
            }
        };
    }

    function bindCopyButton(btn) {
        if (btn.dataset.lnBound) return; btn.dataset.lnBound = '1';
        var label = btn.dataset.copyLabel || '内容';
        var originalTitle = btn.getAttribute('title') || ('复制' + label);
        var originalAria = btn.getAttribute('aria-label') || originalTitle;

        btn.addEventListener('click', function(event) {
            event.preventDefault();
            var text = btn.dataset.copyText || absoluteCopyUrl(btn.dataset.copyUrl || '/rss.xml');
            var message = btn.dataset.copyMessage || (btn.classList.contains('footer-rss-copy') ? 'RSS 地址已复制' : (label + '已复制'));
            var isSpellCopy = btn.classList.contains('spell-copy-btn');
            if (!text) {
                frontToast('没有可复制的内容', 'error');
                return;
            }

            if (isSpellCopy) {
                btn.classList.add('is-copied');
                btn.disabled = true;
                btn.setAttribute('title', message);
                btn.setAttribute('aria-label', message);
                setTimeout(function() {
                    btn.classList.remove('is-copied');
                    btn.disabled = false;
                    btn.setAttribute('title', originalTitle);
                    btn.setAttribute('aria-label', originalAria);
                }, 1800);
            }

            copyText(text).then(function() {
                if (!isSpellCopy) {
                    btn.classList.add('is-copied');
                    btn.setAttribute('title', message);
                    btn.setAttribute('aria-label', message);
                }
                frontToast(message, 'success');
                if (!isSpellCopy) {
                    setTimeout(function() {
                        btn.classList.remove('is-copied');
                        btn.setAttribute('title', originalTitle);
                        btn.setAttribute('aria-label', originalAria);
                    }, 1800);
                }
            }).catch(function() {
                frontToast('复制失败，请稍后重试', 'error');
            });
        });
    }

    window.siteToast = frontToast;

    function bindToastSeeds(root) {
        root = root || document;
        root.querySelectorAll('[data-toast-message]').forEach(function(seed) {
            if (seed.dataset.lnBound) return;
            seed.dataset.lnBound = '1';
            frontToast(seed.dataset.toastMessage || seed.textContent || '', seed.dataset.toastType || 'success');
            seed.remove();
        });
    }

    function bindTimeTags(root) {
        root = root || document;
        root.querySelectorAll('.time-tag[data-time-absolute][data-time-relative]').forEach(function(timeEl) {
            if (timeEl.dataset.lnTimeBound === '1') return;
            timeEl.dataset.lnTimeBound = '1';
            var relativeText = timeEl.dataset.timeRelative || timeEl.textContent || '';
            var absoluteText = timeEl.dataset.timeAbsolute || '';
            if (!absoluteText) return;
            timeEl.textContent = relativeText;
            timeEl.title = absoluteText;
            timeEl.setAttribute('aria-label', absoluteText);
        });
    }

    function bindPostCommentsScroll(root) {
        root = root || document;
        root.querySelectorAll('[data-post-comments-scroll]').forEach(function(btn) {
            if (btn.dataset.lnCommentsScrollBound === '1') return;
            btn.dataset.lnCommentsScrollBound = '1';
            btn.addEventListener('click', function() {
                var comments = document.querySelector('.post-detail .comments');
                if (!comments) return;
                comments.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        });
    }

    // 统一绑定动态交互(初始 + 加载更多后的新内容,带去重守卫)
    var discPlayerFallbackLoop = 0;
    function ensureDiscPlayerFallbackLoop(root) {
        root = root || document;
        if (discPlayerFallbackLoop) {
            return;
        }
        if (!root.querySelector || !root.querySelector('.music-disc-player')) {
            if (root !== document && !document.querySelector('.music-disc-player')) {
                return;
            }
            if (root === document) {
                return;
            }
        }
        discPlayerFallbackLoop = window.setInterval(function() {
            var players = document.querySelectorAll('.music-disc-player');
            for (var i = 0; i < players.length; i += 1) {
                var p = players[i];
                var a = p.querySelector('audio');
                if (a && !a.paused && !a.ended && typeof p.__lnRenderProgress === 'function') {
                    p.__lnRenderProgress();
                }
            }
        }, 250);
    }

    function bindCommentLoadMore(root) {
        (root || document).querySelectorAll('.comment-load-more').forEach(function(btn) {
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
                                bindDynamic(node);
                            });
                            if (data.hasMore) {
                                btn.dataset.offset = String(data.nextOffset || (offset + (data.count || 0)));
                                btn.disabled = false;
                                btn.textContent = originalText;
                            } else if (btn.parentNode) {
                                btn.parentNode.removeChild(btn);
                            }
                        } else {
                            frontToast((data && data.msg) || '加载失败', 'error');
                            btn.disabled = false;
                            btn.textContent = originalText;
                        }
                    })
                    .catch(function() {
                        frontToast('网络错误，请重试', 'error');
                        btn.disabled = false;
                        btn.textContent = originalText;
                    })
                    .finally(function() { busy = false; });
            });
        });
    }

    function bindDynamic(root) {
        root = root || document;
        bindToastSeeds(root);
        bindTimeTags(root);
        bindPostCommentsScroll(root);
        bindNavIdentityOrb(root);
        bindNavShell(root);
        root.querySelectorAll('.comment-form').forEach(bindCommentForm);
        root.querySelectorAll('.comment-reply-btn').forEach(bindReplyBtn);
        root.querySelectorAll('.talk-comment-toggle').forEach(bindToggleBtn);
        root.querySelectorAll('.talk-like-btn').forEach(bindLikeBtn);
        root.querySelectorAll('.post-like-btn').forEach(bindPostLikeBtn);
        root.querySelectorAll('.music-share-like-btn').forEach(bindMusicShareLike);
        root.querySelectorAll('.home-music-card .music-share-comments .comment-item').forEach(bindMusicCommentCard);
        root.querySelectorAll('.music-card').forEach(bindMusicCard);
        root.querySelectorAll('.music-disc-player').forEach(bindMusicDiscPlayer);
        ensureDiscPlayerFallbackLoop(root);
        root.querySelectorAll('[data-music-comments-toggle]').forEach(bindMusicCommentsToggle);
        root.querySelectorAll('[data-music-comments-close]').forEach(bindMusicCommentsClose);
        root.querySelectorAll('.front-publish-form').forEach(bindPublishForm);
        root.querySelectorAll('.footer-rss-copy, [data-copy-text]').forEach(bindCopyButton);
        bindArticleCommentIdentityPrompt(root);
        hydrateStoredLikeStates(root);
        bindImages(root);
        bindLoadMore(root);
        bindHomeFeedMore(root);
        bindCommentLoadMore(root);
        bindTalkKeywordFilter(root);
    }
    bindDynamic(document);

    // 图片懒加载 + LiteZoom 分组灯箱
    function finishImageLoad(img, wrapper) {
        img.classList.remove('is-image-loading');
        wrapper.classList.remove('is-loading');
        wrapper.classList.add('is-loaded');
    }

    function finishAvatarLoad(img) {
        img.classList.remove('is-avatar-loading');
        img.classList.add('is-avatar-loaded');
    }

    function prepareAvatarLoading(img) {
        if (!img || img.dataset.avatarLoadingReady === '1') {
            return;
        }

        img.dataset.avatarLoadingReady = '1';
        if (!img.hasAttribute('loading')) {
            img.setAttribute('loading', 'lazy');
        }
        img.setAttribute('decoding', 'async');
        img.classList.add('is-avatar-loading');

        if (img.complete) {
            finishAvatarLoad(img);
            return;
        }

        img.addEventListener('load', function() {
            finishAvatarLoad(img);
        }, { once: true });

        img.addEventListener('error', function() {
            finishAvatarLoad(img);
        }, { once: true });
    }

    function prepareImageLoading(img) {
        if (img.dataset.imageLoadingReady === '1') {
            return;
        }

        img.dataset.imageLoadingReady = '1';

        var wrapper = img.parentElement && img.parentElement.classList.contains('image-loading-wrap')
            ? img.parentElement
            : document.createElement('span');

        if (!wrapper.parentElement) {
            wrapper.className = 'image-loading-wrap is-loading';
            img.parentNode.insertBefore(wrapper, img);
            wrapper.appendChild(img);
        } else {
            wrapper.classList.add('image-loading-wrap');
        }

        img.classList.add('is-image-loading');

        if (img.complete) {
            finishImageLoad(img, wrapper);
            return;
        }

        img.addEventListener('load', function() {
            finishImageLoad(img, wrapper);
        }, { once: true });

        img.addEventListener('error', function() {
            finishImageLoad(img, wrapper);
            wrapper.classList.add('is-error');
        }, { once: true });
    }

    // 说说图片分组:每条说说一个画廊
    function liteZoomTalkGroup(img) {
        var card = img.closest('.home-talk-card, .talk-card, article[id]');
        var id = card && card.id ? card.id : '';
        return id ? ('talk-' + id) : 'talk';
    }

    // 文章/页面正文图片分组
    function liteZoomPostGroup(img) {
        if (img.closest('.page-content')) {
            return 'page';
        }
        var card = img.closest('.post-detail, article[id]');
        var id = card && card.id ? card.id : '';
        return id ? ('post-' + id) : 'post';
    }

    // 向 LiteZoom 注册两套绑定(委托式,只需注册一次)
    var liteZoomBound = false;
    function bindLiteZoom() {
        if (liteZoomBound || !window.LiteZoom) {
            return;
        }
        liteZoomBound = true;
        // 首页/说说:简单模式 —— 点开放大 + 左右切换 + 关闭
        window.LiteZoom.bind('.talk-images img', {
            mode: 'simple',
            group: liteZoomTalkGroup
        });
        // 文章/页面正文:完整模式 —— 缩放/平移 + 缩略图 + caption
        window.LiteZoom.bind('.post-content img, .page-content img', {
            mode: 'full',
            group: liteZoomPostGroup,
            caption: function(img) { return (img.getAttribute('alt') || '').trim(); }
        });
    }

    function bindImages(root) {
        root = root || document;
        var images = Array.prototype.slice.call(root.querySelectorAll('.post-cover img, .home-post-cover img, .post-content img, .page-content img, .talk-images img'));
        var postCoverImages = Array.prototype.slice.call(root.querySelectorAll('.post-hero-card .post-cover img'));
        var avatarImages = Array.prototype.slice.call(root.querySelectorAll('.comment-avatar, .music-share-comment-avatar, .music-song-comment-avatar img'));

        if (window.LiteZoom && typeof window.LiteZoom.enhance === 'function') {
            window.LiteZoom.enhance('.post-cover img, .home-post-cover img, .post-content img, .page-content img, .talk-images img', {
                root: root,
                wrap: true,
                rootMargin: '360px 0px'
            });
            window.LiteZoom.enhance('.comment-avatar, .music-share-comment-avatar, .music-song-comment-avatar img', {
                root: root,
                avatar: true,
                wrap: false,
                rootMargin: '360px 0px'
            });
            window.LiteZoom.refresh(root);
        } else {
            images.forEach(function(img) {
                if (!img.hasAttribute('loading')) {
                    img.setAttribute('loading', 'lazy');
                }
                img.setAttribute('decoding', 'async');
                prepareImageLoading(img);
            });
            avatarImages.forEach(prepareAvatarLoading);
        }

        postCoverImages.forEach(function(img) {
            if (img.dataset.noViewReady === '1') {
                return;
            }
            img.dataset.noViewReady = '1';
            img.style.cursor = 'default';
            img.setAttribute('draggable', 'false');
            img.addEventListener('contextmenu', function(event) {
                event.preventDefault();
            });
        });

        bindLiteZoom();
    }

    // 导航:悬停「文章」时整体盒子向下展开分类
    function bindNavShell(root) {
        root = root || document;
        var shell = root.querySelector ? root.querySelector('#nav-shell') : document.getElementById('nav-shell');
        if (!shell && root !== document) shell = document.getElementById('nav-shell');
        if (!shell) return;
        if (shell.dataset.lnNavBound === '1') return;
        shell.dataset.lnNavBound = '1';
        var row = shell.querySelector('.nav-row');
        var indicator = row ? row.querySelector('.nav-indicator') : null;
        var items = row ? Array.prototype.slice.call(row.querySelectorAll('a')) : [];
        var indicatorItems = items.filter(function(item) {
            return !item.classList.contains('nav-avatar');
        });
        var scrollTicking = false;
        var lastScrolledState = null;
        var scrollChangeTimer = 0;

        function isDesktopNav() {
            return !window.matchMedia || window.matchMedia('(min-width: 769px)').matches;
        }

        function updateScrolledNav() {
            if (!isDesktopNav()) {
                document.body.classList.remove('nav-scrolled');
                document.body.classList.remove('nav-scroll-changing');
                lastScrolledState = null;
                return;
            }
            var nextState = window.scrollY > 72;
            if (lastScrolledState !== null && lastScrolledState !== nextState) {
                document.body.classList.add('nav-scroll-changing');
                window.clearTimeout(scrollChangeTimer);
                scrollChangeTimer = window.setTimeout(function() {
                    document.body.classList.remove('nav-scroll-changing');
                    hideIndicator();
                }, 220);
            }
            lastScrolledState = nextState;
            document.body.classList.toggle('nav-scrolled', nextState);
        }

        function syncShellWidth() {
            if (!row) return;
            if (!isDesktopNav() || document.body.classList.contains('nav-scrolled')) {
                shell.style.removeProperty('width');
                return;
            }

            shell.style.removeProperty('width');
            var styles = window.getComputedStyle(row);
            var gap = parseFloat(styles.columnGap || styles.gap) || 0;
            var width = (parseFloat(styles.paddingLeft) || 0) + (parseFloat(styles.paddingRight) || 0);
            var parts = Array.prototype.filter.call(row.children, function(el) {
                return !el.classList.contains('nav-indicator') && window.getComputedStyle(el).display !== 'none';
            });

            parts.forEach(function(el, index) {
                width += el.getBoundingClientRect().width;
                if (index > 0) width += gap;
            });

            shell.style.width = Math.ceil(width) + 'px';
        }

        function activeItem() {
            if (!row) return null;
            var active = row.querySelector('a.active:not(.nav-avatar)');
            if (active) return active;
            return row.querySelector('.nav-avatar.active') ? null : (indicatorItems[0] || null);
        }

        function hideIndicator() {
            if (row) {
                row.style.setProperty('--nav-indicator-opacity', '0');
            }
        }

        function closeIdentityMenus() {
            document.querySelectorAll('[data-nav-identity].is-menu-open').forEach(function(orb) {
                if (typeof orb.__lnCloseIdentityMenu === 'function') {
                    orb.__lnCloseIdentityMenu();
                } else {
                    orb.classList.remove('is-menu-open');
                }
            });
        }

        function moveIndicator(target) {
            if (!row || !indicator || !target || target.classList.contains('nav-avatar')) {
                hideIndicator();
                return;
            }
            var rowRect = row.getBoundingClientRect();
            var rect = target.getBoundingClientRect();
            row.style.setProperty('--nav-indicator-x', (rect.left - rowRect.left) + 'px');
            row.style.setProperty('--nav-indicator-width', rect.width + 'px');
            row.style.setProperty('--nav-indicator-opacity', '1');
        }

        if (indicator && items.length) {
            requestAnimationFrame(function() {
                updateScrolledNav();
                syncShellWidth();
                hideIndicator();
            });

            items.forEach(function(item) {
                item.addEventListener('mouseenter', function() {
                    if (!item.classList.contains('nav-avatar')) closeIdentityMenus();
                    moveIndicator(item);
                });
                item.addEventListener('focus', function() {
                    if (!item.classList.contains('nav-avatar')) closeIdentityMenus();
                    moveIndicator(item);
                });
            });

            row.addEventListener('focusout', function(e) {
                if (!row.contains(e.relatedTarget)) {
                    hideIndicator();
                }
            });

            window.addEventListener('resize', function() {
                updateScrolledNav();
                syncShellWidth();
                hideIndicator();
            });

            window.addEventListener('load', function() {
                updateScrolledNav();
                syncShellWidth();
                hideIndicator();
            });

            window.addEventListener('scroll', function() {
                if (scrollTicking) return;
                scrollTicking = true;
                requestAnimationFrame(function() {
                    scrollTicking = false;
                    updateScrolledNav();
                    hideIndicator();
                });
            }, { passive: true });

            if (document.fonts && document.fonts.ready) {
                document.fonts.ready.then(function() {
                    updateScrolledNav();
                    syncShellWidth();
                    hideIndicator();
                });
            }
        }

        var trigger = shell.querySelector('.nav-dd-trigger');
        var drawer = shell.querySelector('.nav-drawer');
        var navCloseTimer = 0;
        function closeNavDrawer() {
            window.clearTimeout(navCloseTimer);
            shell.classList.remove('nav-open');
        }
        function scheduleCloseNavDrawer() {
            window.clearTimeout(navCloseTimer);
            navCloseTimer = window.setTimeout(function() {
                shell.classList.remove('nav-open');
            }, 120);
        }
        if (!trigger) return;
        trigger.addEventListener('mouseenter', function() {
            window.clearTimeout(navCloseTimer);
            closeIdentityMenus();
            shell.classList.add('nav-open');
        });
        trigger.addEventListener('mouseleave', scheduleCloseNavDrawer);
        if (drawer) {
            drawer.addEventListener('mouseenter', function() {
                window.clearTimeout(navCloseTimer);
            });
            drawer.addEventListener('mouseleave', scheduleCloseNavDrawer);
        }
        items.forEach(function(item) {
            if (item === trigger || item.classList.contains('nav-avatar')) return;
            item.addEventListener('mouseenter', closeNavDrawer);
            item.addEventListener('focus', closeNavDrawer);
        });
        shell.addEventListener('mouseleave', function() {
            closeNavDrawer();
            hideIndicator();
        });
    }

    // 加载更多:首次自动加载,之后手动;到底显示"没有更多内容"
    // 包成可重入函数并由 bindDynamic 调用,确保新插入列表的按钮也能绑定
    function bindLoadMore(root) {
        (root || document).querySelectorAll('.load-more').forEach(function(lm) {
        if (lm.dataset.lnLoadmore) return;
        lm.dataset.lnLoadmore = '1';
        var pages = parseInt(lm.dataset.pages, 10) || 1;
        var page = parseInt(lm.dataset.page, 10) || 1;
        var base = lm.dataset.base || '';
        var btn = lm.querySelector('.load-more-btn');
        var loading = lm.querySelector('.load-more-loading');
        var endEl = lm.querySelector('.load-more-end');
        var container = (lm.parentNode && lm.parentNode.querySelector('.js-list-items')) || document.querySelector('.js-list-items');
        var busy = false;

        function showEnd() {
            if (btn) btn.hidden = true;
            if (loading) loading.hidden = true;
            if (endEl) endEl.hidden = false;
        }
        if (page >= pages || !container) { showEnd(); return; }
        if (btn) btn.hidden = false; // 手动点击加载,与首页加载更多一致

        function load() {
            if (busy || page >= pages) return;
            busy = true;
            if (btn) btn.hidden = true;
            if (loading) loading.hidden = false;
            var next = page + 1;
            var url = base + (base.indexOf('?') > -1 ? '&' : '?') + 'page=' + next;
            fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function(r) { return r.text(); })
                .then(function(html) {
                    var doc = new DOMParser().parseFromString(html, 'text/html');
                    var src = doc.querySelector('.js-list-items');
                    if (src) {
                        Array.prototype.slice.call(src.children).forEach(function(node) {
                            var n = document.importNode(node, true);
                            container.appendChild(n);
                            if (n.querySelectorAll) bindDynamic(n);
                        });
                    }
                    page = next; lm.dataset.page = page;
                    if (loading) loading.hidden = true;
                    busy = false;
                    if (page >= pages) showEnd();
                    else if (btn) btn.hidden = false;
                })
                .catch(function() {
                    busy = false;
                    if (loading) loading.hidden = true;
                    if (btn) { btn.hidden = false; btn.textContent = '加载失败，点击重试'; }
                });
        }

        if (btn) btn.addEventListener('click', function() { load(); });
        });
    }
    bindLoadMore(document);

    // 滔客关键词筛选:点击侧栏 #关键词 → AJAX 拉取完整筛选结果换列表,地址栏保持 /talk 不变
    function bindTalkKeywordFilter(root) {
        var rail = (root || document).querySelector('[data-talk-keyword-rail]');
        if (!rail || rail.dataset.lnFilter === '1') return;
        rail.dataset.lnFilter = '1';
        var section = rail.closest('.talk-list');
        if (!section) return;
        var chips = Array.prototype.slice.call(rail.querySelectorAll('.talk-keyword-chip'));
        var busy = false;
        var resetLoading = function() {
            var frame = section.querySelector('[data-talk-filter-frame]');
            var loading = frame ? frame.querySelector('[data-talk-filter-loading]') : null;
            busy = false;
            section.classList.remove('is-filtering');
            section.removeAttribute('aria-busy');
            if (frame) frame.classList.remove('is-loading');
            if (loading) loading.hidden = true;
        };
        resetLoading();

        chips.forEach(function(chip) {
            chip.addEventListener('click', function(e) {
                e.preventDefault();
                if (busy || chip.classList.contains('is-active')) return;
                var href = chip.getAttribute('href');
                var frame = section.querySelector('[data-talk-filter-frame]');
                var list = frame ? frame.querySelector('.js-list-items') : section.querySelector('.js-list-items');
                var loading = frame ? frame.querySelector('[data-talk-filter-loading]') : null;
                if (!href || !list) return;

                var previous = rail.querySelector('.talk-keyword-chip.is-active');
                busy = true;
                chips.forEach(function(c) { c.classList.remove('is-active'); });
                chip.classList.add('is-active');
                section.classList.add('is-filtering');
                section.setAttribute('aria-busy', 'true');
                if (frame) frame.classList.add('is-loading');
                if (loading) loading.hidden = false;

                fetch(href, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(function(r) { return r.text(); })
                    .then(function(html) {
                        var doc = new DOMParser().parseFromString(html, 'text/html');
                        var newList = doc.querySelector('.js-list-items');
                        var newMore = doc.querySelector('.load-more');
                        if (newList) {
                            list.innerHTML = newList.innerHTML;
                            bindDynamic(list);
                        }
                        var oldMore = section.querySelector('.load-more');
                        if (oldMore) oldMore.remove();
                        if (newMore && list.parentNode) {
                            list.parentNode.insertBefore(document.importNode(newMore, true), list.nextSibling);
                            bindLoadMore(section);
                        }
                        resetLoading();
                    })
                    .catch(function() {
                        chip.classList.remove('is-active');
                        if (previous) previous.classList.add('is-active');
                        resetLoading();
                        if (window.frontToast) window.frontToast('关键词加载失败，请稍后重试', 'error');
                    });
            });
        });
    }
    bindTalkKeywordFilter(document);

    function bindHomeFeedMore(root) {
        root = root || document;
        root.querySelectorAll('[data-home-feed-more]').forEach(function(box) {
        if (box.dataset.lnHomeFeedMoreBound === '1') return;
        box.dataset.lnHomeFeedMoreBound = '1';
        var listSelector = box.dataset.listSelector || '[data-home-feed-list]';
        var list = document.querySelector(listSelector);
        var btn = box.querySelector('.home-feed-more-btn');
        var btnText = btn ? btn.querySelector('span') : null;
        var loading = box.querySelector('.home-feed-more-loading');
        var end = box.querySelector('.home-feed-more-end');
        var url = box.dataset.url || '/home/feed';
        var limit = parseInt(box.dataset.limit, 10) || 10;
        var busy = false;

        function setState(state) {
            if (btn) btn.hidden = state !== 'idle';
            if (loading) loading.hidden = state !== 'loading';
            if (end) end.hidden = state !== 'end';
        }

        function appendHtml(html) {
            var tpl = document.createElement('template');
            tpl.innerHTML = html || '';
            Array.prototype.slice.call(tpl.content.children).forEach(function(node) {
                list.appendChild(node);
                if (node.querySelectorAll) bindDynamic(node);
            });
        }

        function loadMore() {
            if (busy || !list) return;
            busy = true;
            if (btnText) btnText.textContent = '加载更多';
            setState('loading');
            var offset = parseInt(box.dataset.offset, 10) || 0;
            var startedAt = Date.now();
            var requestUrl = url + (url.indexOf('?') > -1 ? '&' : '?') + 'offset=' + encodeURIComponent(offset) + '&limit=' + encodeURIComponent(limit);
            fetch(requestUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    var wait = Math.max(0, 500 - (Date.now() - startedAt));
                    window.setTimeout(function() {
                        if (!data || data.code !== 0) {
                            busy = false;
                            if (btnText) btnText.textContent = '加载失败，重试';
                            setState('idle');
                            return;
                        }
                        appendHtml(data.html || '');
                        box.dataset.offset = String(data.nextOffset || offset);
                        busy = false;
                        setState(data.hasMore ? 'idle' : 'end');
                    }, wait);
                })
                .catch(function() {
                    busy = false;
                    if (btnText) btnText.textContent = '加载失败，重试';
                    setState('idle');
                });
        }

        if (!list) {
            setState('end');
            return;
        }
        setState('idle');
        if (btn) btn.addEventListener('click', loadMore);
        });
    }
    bindHomeFeedMore(document);

})();

/* 登录 dialog + Passkey(WebAuthn 逻辑移植自后台 admin.js) —— 头像菜单"登录"触发,不依赖独立登录页 */
(function () {
    function lnLoginCsrf() {
        var f = document.querySelector('[data-login-form] input[name="_csrf"]') || document.querySelector('input[name="_csrf"]');
        return f ? f.value : '';
    }
    function lnB64UrlToBytes(value) {
        value = String(value || '').replace(/-/g, '+').replace(/_/g, '/');
        while (value.length % 4) value += '=';
        return Uint8Array.from(atob(value), function (c) { return c.charCodeAt(0); });
    }
    function lnBytesToB64Url(buffer) {
        var bytes = new Uint8Array(buffer), s = '';
        for (var i = 0; i < bytes.length; i += 1) s += String.fromCharCode(bytes[i]);
        return btoa(s).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/g, '');
    }
    async function lnPkJson(res) {
        var type = res.headers.get('content-type') || '';
        if (type.indexOf('application/json') === -1) { await res.text(); throw new Error('Passkey 接口返回非 JSON 响应'); }
        var data = await res.json();
        if (!res.ok || data.success === false) throw new Error(data.message || data.error || 'Passkey 请求失败');
        return data;
    }
    async function lnLoginWithPasskey() {
        if (!window.PublicKeyCredential || !navigator.credentials) throw new Error('当前浏览器不支持 Passkey');
        var res = await fetch('/admin/passkey/login-options', { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
        var options = await lnPkJson(res);
        var allow = (options.allowCredentials || []).map(function (it) { return { type: it.type || 'public-key', id: lnB64UrlToBytes(it.id) }; });
        var assertion = await navigator.credentials.get({
            publicKey: {
                challenge: lnB64UrlToBytes(options.challenge),
                timeout: options.timeout,
                rpId: options.rpId,
                allowCredentials: allow,
                userVerification: options.userVerification || 'preferred'
            }
        });
        var data = {
            id: assertion.id,
            rawId: lnBytesToB64Url(assertion.rawId),
            response: {
                clientDataJSON: lnBytesToB64Url(assertion.response.clientDataJSON),
                authenticatorData: lnBytesToB64Url(assertion.response.authenticatorData),
                signature: lnBytesToB64Url(assertion.response.signature),
                userHandle: assertion.response.userHandle ? lnBytesToB64Url(assertion.response.userHandle) : ''
            }
        };
        var loginRes = await fetch('/admin/passkey/login', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': lnLoginCsrf(), 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            body: JSON.stringify({ credential: JSON.stringify(data) })
        });
        return await lnPkJson(loginRes);
    }

    function lnOverlay() { return document.querySelector('[data-login-overlay]'); }
    function lnErr(msg) { var e = document.querySelector('[data-login-error]'); if (e) { e.textContent = msg || ''; e.hidden = !msg; } }
    function lnOpen(trigger) {
        var o = lnOverlay();
        if (!o) {
            window.location.href = '/?login=1';
            return;
        }
        var orb = trigger && trigger.closest ? trigger.closest('[data-nav-identity]') : null;
        if (orb) orb.classList.remove('is-menu-open');
        o.hidden = false; document.body.classList.add('login-modal-open');
        var u = o.querySelector('[name=username]'); if (u) setTimeout(function () { try { u.focus(); } catch (e) {} }, 60);
    }
    function lnClose() { var o = lnOverlay(); if (o) { o.hidden = true; document.body.classList.remove('login-modal-open'); lnErr(''); } }

    document.addEventListener('click', function (e) {
        var openTrigger = e.target.closest('[data-login-open]');
        if (openTrigger) { e.preventDefault(); lnOpen(openTrigger); return; }
        if (e.target.closest('[data-login-close]')) { e.preventDefault(); lnClose(); return; }
        var o = lnOverlay();
        if (o && !o.hidden && e.target === o) lnClose();
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') lnClose(); });

    document.addEventListener('submit', function(e) {
        var form = e.target && e.target.closest ? e.target.closest('[data-login-form]') : null;
        if (!form) return;
        e.preventDefault(); lnErr('');
        var btn = form.querySelector('.login-modal-submit');
        if (btn) btn.disabled = true;
        var body = new URLSearchParams();
        body.set('_csrf', lnLoginCsrf());
        body.set('username', (form.username.value || '').trim());
        body.set('password', form.password.value || '');
        fetch('/admin/login', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
            credentials: 'same-origin',
            body: body.toString()
        }).then(function (res) {
            return res.json().catch(function () { return {}; }).then(function (data) { return { ok: res.ok, data: data }; });
        }).then(function (r) {
            if (r.ok && r.data && r.data.ok) { window.location.href = r.data.redirect || '/admin'; }
            else { lnErr((r.data && r.data.message) || '用户名或密码错误'); if (btn) btn.disabled = false; }
        }).catch(function (err) { lnErr('登录失败：' + err.message); if (btn) btn.disabled = false; });
    });

    document.addEventListener('click', function(e) {
        var pk = e.target.closest('[data-login-passkey]');
        if (!pk) return;
        e.preventDefault();
        lnErr('');
        lnLoginWithPasskey().then(function (r) {
            if (r && r.success !== false) window.location.href = '/admin';
            else lnErr((r && r.message) || 'Passkey 登录失败');
        }).catch(function (err) { lnErr('Passkey 登录失败：' + err.message); });
    });

    try {
        var params = new URLSearchParams(window.location.search || '');
        if (params.get('login') === '1') {
            lnOpen();
            if (params.get('password_changed') === '1') {
                lnErr('密码已修改，请重新登录');
            }
        }
    } catch (e) {}
})();
