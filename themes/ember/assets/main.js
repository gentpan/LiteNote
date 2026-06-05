// LiteNote ViewImage: grouped image viewer with a bottom toolbar.
(function() {
    'use strict';

    function siteLoadingSpinnerSvg(extraClass) {
        var cls = 'site-loading-spinner' + (extraClass ? ' ' + extraClass : '');
        return '<span class="' + cls + '" aria-hidden="true">'
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
                button.setAttribute('aria-label', isDark ? '\u{5207}\u{6362}\u{6D45}\u{8272}\u{6A21}\u{5F0F}' : '\u{5207}\u{6362}\u{6DF1}\u{8272}\u{6A21}\u{5F0F}');
                button.setAttribute('title', isDark ? '\u{5207}\u{6362}\u{6D45}\u{8272}\u{6A21}\u{5F0F}' : '\u{5207}\u{6362}\u{6DF1}\u{8272}\u{6A21}\u{5F0F}');
                var label = button.querySelector('[data-theme-label]');
                if (label) {
                    label.textContent = isDark ? '\u{6D45}\u{8272}\u{6A21}\u{5F0F}' : '\u{6DF1}\u{8272}\u{6A21}\u{5F0F}';
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

    if (window.ViewImage && window.ViewImage.__liteNote) {
        return;
    }

    var target = '[view-image] img';
    var bound = false;

    function imageSrc(img) {
        return img.currentSrc || img.getAttribute('src') || img.src || '';
    }

    function collect(group) {
        return Array.prototype.slice.call(group.querySelectorAll('img:not([no-view])'))
            .map(imageSrc)
            .filter(Boolean);
    }

    function display(images, current) {
        if (!images.length) {
            return;
        }

        var index = Math.max(0, images.indexOf(current));
        var overlay = document.createElement('div');
        overlay.className = 'view-image';
        overlay.innerHTML = ''
            + '<div class="view-image-close-full" data-view-close></div>'
            + '<div class="view-image-stage">'
            + '  <img class="view-image-lead" alt="ViewImage" no-view>'
            + '</div>'
            + '<div class="view-image-tools">'
            + '  <div class="view-image-count"><strong data-view-index></strong><span data-view-total></span></div>'
            + '  <div class="view-image-flip">'
            + '    <button type="button" class="view-image-btn" data-view-prev aria-label="\u{4E0A}\u{4E00}\u{5F20}"><i class="fa-solid fa-chevron-left"></i></button>'
            + '    <button type="button" class="view-image-btn" data-view-next aria-label="\u{4E0B}\u{4E00}\u{5F20}"><i class="fa-solid fa-chevron-right"></i></button>'
            + '  </div>'
            + '  <button type="button" class="view-image-btn" data-view-close aria-label="\u{5173}\u{95ED}"><i class="fa-solid fa-xmark"></i></button>'
            + '</div>';

        var lead = overlay.querySelector('.view-image-lead');
        var indexEl = overlay.querySelector('[data-view-index]');
        var totalEl = overlay.querySelector('[data-view-total]');
        var prevBtn = overlay.querySelector('[data-view-prev]');
        var nextBtn = overlay.querySelector('[data-view-next]');

        function render() {
            lead.classList.remove('is-in');
            lead.classList.add('is-out');
            setTimeout(function() {
                indexEl.textContent = String(index + 1);
                totalEl.textContent = '/' + images.length;
                prevBtn.disabled = images.length < 2;
                nextBtn.disabled = images.length < 2;
                lead.onload = function() {
                    lead.classList.remove('is-out');
                    lead.classList.add('is-in');
                };
                lead.src = images[index];
                if (lead.complete) {
                    lead.onload();
                }
            }, 120);
        }

        function close() {
            overlay.classList.add('is-leaving');
            document.body.classList.remove('view-image-open');
            window.removeEventListener('keydown', onKeydown, true);
            setTimeout(function() {
                overlay.remove();
            }, 180);
        }

        function move(step) {
            if (images.length < 2) {
                return;
            }
            index = (index + step + images.length) % images.length;
            render();
        }

        function onKeydown(event) {
            if (event.key === 'Escape') close();
            if (event.key === 'ArrowLeft') move(-1);
            if (event.key === 'ArrowRight') move(1);
        }

        overlay.addEventListener('click', function(event) {
            if (event.target.closest('[data-view-close]')) close();
            if (event.target.closest('[data-view-prev]')) move(-1);
            if (event.target.closest('[data-view-next]')) move(1);
        });

        document.body.appendChild(overlay);
        document.body.classList.add('view-image-open');
        window.addEventListener('keydown', onKeydown, true);
        render();
        requestAnimationFrame(function() {
            overlay.classList.add('is-open');
        });
    }

    function listener(event) {
        if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) {
            return;
        }
        var selector = String(target.split(',').map(function(item) {
            return item.trim() + ':not([no-view])';
        }));
        var img = event.target.closest(selector);
        if (!img) {
            return;
        }
        var group = img.closest('[view-image]') || document.body;
        var images = collect(group);
        display(images, imageSrc(img));
        event.preventDefault();
        event.stopPropagation();
    }

    window.ViewImage = {
        __liteNote: true,
        init: function(nextTarget) {
            if (nextTarget) {
                target = nextTarget;
            }
            if (!bound) {
                document.addEventListener('click', listener, false);
                bound = true;
            }
        },
        display: display
    };
})();

// \u{524D}\u{53F0}\u{811A}\u{672C}
(function() {
    'use strict';

    // \u{5E73}\u{6ED1}\u{6EDA}\u{52A8}\u{5230}\u{951A}\u{70B9}
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

    function saveCommentIdentity(form) {
        if (form.dataset.commentAdmin === '1') {
            return;
        }

        var nick = form.querySelector('[name=nickname]');
        var email = form.querySelector('[name=email]');
        var website = form.querySelector('[name=website]');
        var identity = {
            nickname: nick ? nick.value.trim() : '',
            email: email ? email.value.trim() : '',
            website: website ? website.value.trim() : ''
        };

        if (!identity.nickname && !identity.email && !identity.website) {
            return;
        }

        try {
            localStorage.setItem(commentIdentityKey, JSON.stringify(identity));
        } catch (e) {}
    }

    function fillCommentIdentity(form) {
        if (form.dataset.commentAdmin === '1') {
            return;
        }

        var identity = loadCommentIdentity();
        if (!identity) {
            return;
        }

        [
            ['nickname', identity.nickname],
            ['email', identity.email],
            ['website', identity.website]
        ].forEach(function(item) {
            var input = form.querySelector('[name=' + item[0] + ']');
            if (input && !input.value && item[1]) {
                input.value = item[1];
            }
        });
    }

    // AJAX \u{63D0}\u{4EA4}\u{540E}\u{628A}\u{65B0}\u{8BC4}\u{8BBA}\u{63D2}\u{5165}\u{5217}\u{8868}(\u{8BF4}\u{8BF4} / \u{6587}\u{7AE0}\u{4E24}\u{79CD}\u{7ED3}\u{6784})
    function appendComment(form, c) {
        var talkInput = form.querySelector('[name=talk_id]');
        var isTalk = talkInput && parseInt(talkInput.value, 10) > 0;
        var musicInput = form.querySelector('[name=music_id]');
        var isMusic = musicInput && parseInt(musicInput.value, 10) > 0;
        var parentId = parseInt(c.parent_id, 10) || 0;

        // \u{627E}\u{5230}\u{56DE}\u{590D}\u{5E94}\u{5F52}\u{5C5E}\u{7684}\u{9876}\u{5C42}\u{8BC4}\u{8BBA} li(\u{7236}\u{7EA7}\u{672C}\u{8EAB}\u{662F}\u{56DE}\u{590D}\u{65F6},\u{53D6}\u{5176}\u{6240}\u{5C5E}\u{9876}\u{5C42})
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
            if (!rl) { rl = document.createElement('ul'); rl.className = cls; rootLi.appendChild(rl); }
            return rl;
        }

        if (isTalk) {
            var container = form.closest('.talk-comments') || form.parentNode;
            var li = document.createElement('li');
            li.setAttribute('data-id', c.id);
            var strong = document.createElement('strong'); strong.textContent = c.nickname;
            li.appendChild(strong);
            var rtn = (parentId > 0) ? (form.dataset.replyTo || '') : '';
            if (rtn) {
                var ar = document.createElement('span'); ar.className = 'reply-arrow'; ar.textContent = ' \u{203A} ';
                var tg = document.createElement('span'); tg.className = 'reply-target'; tg.textContent = rtn;
                li.appendChild(ar); li.appendChild(tg);
            }
            var time = document.createElement('span'); time.className = 'comment-time'; time.innerHTML = ' \u{B7} ' + c.time;
            var bodyS = document.createElement('span'); bodyS.className = 'talk-comment-content'; bodyS.textContent = c.content;
            li.appendChild(time); li.appendChild(document.createTextNode(' ')); li.appendChild(bodyS);

            if (parentId > 0) {
                var root = rootLiOf(container, parentId);
                if (root) { replyListIn(root, 'talk-reply-list').appendChild(li); }
                else { var l0 = container.querySelector('.talk-comment-list'); if (l0) l0.appendChild(li); }
            } else {
                var list = container.querySelector('.talk-comment-list');
                if (!list) { list = document.createElement('ul'); list.className = 'talk-comment-list'; container.insertBefore(list, form); }
                list.appendChild(li);
            }
            if (container.id) {
                var toggle = document.querySelector('.talk-comment-toggle[data-target="' + container.id + '"] span');
                if (toggle) toggle.textContent = (parseInt(toggle.textContent, 10) || 0) + 1;
            }
        } else {
            var section = form.closest('.comments') || form.parentNode;
            var listScope = section;
            if (isMusic) {
                listScope = section.querySelector('.music-comment-thread.is-active') || section;
                var empty = listScope.querySelector('.music-comment-empty');
                if (empty) empty.remove();
            }
            var item = document.createElement('li');
            item.className = 'comment-item' + (parentId > 0 ? ' comment-reply' : '');
            item.setAttribute('data-id', c.id);
            var rtn2 = (parentId > 0) ? (form.dataset.replyTo || '') : '';
            var metaExtra = rtn2 ? '<span class="reply-arrow">\u{203A}</span><span class="reply-target"></span>' : '';
            item.innerHTML = '<div class="comment-body"><div class="comment-meta"><strong></strong>' + metaExtra + ' <span class="ct"></span></div><div class="comment-content"></div></div>';
            item.querySelector('strong').textContent = c.nickname;
            if (rtn2) item.querySelector('.reply-target').textContent = rtn2;
            item.querySelector('.ct').innerHTML = '\u{B7} ' + c.time;
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
            if (isMusic && listScope.dataset) {
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
        }
    }

    // \u{8BC4}\u{8BBA}\u{63D0}\u{4EA4}\u{540E}\u{7684}\u{7EDF}\u{4E00} toast \u{63D0}\u{793A}
    function commentFlash(form, msg, type) {
        frontToast(msg, type === 'success' ? 'success' : 'error');
    }

    // \u{8BC4}\u{8BBA}\u{8868}\u{5355}(\u{53EF}\u{5BF9}\u{52A8}\u{6001}\u{52A0}\u{8F7D}\u{7684}\u{5185}\u{5BB9}\u{91CD}\u{590D}\u{8C03}\u{7528})
    function bindCommentForm(form) {
        if (form.dataset.lnBound) return; form.dataset.lnBound = '1';
        fillCommentIdentity(form);

        // \u{9A8C}\u{8BC1}\u{7801}:\u{70B9}\u{51FB}\u{56FE}\u{7247}\u{5237}\u{65B0};\u{8868}\u{5355}\u{9996}\u{6B21}\u{805A}\u{7126}\u{65F6}\u{5237}\u{65B0}\u{672C}\u{8868}\u{5355}\u{9A8C}\u{8BC1}\u{7801},
        // \u{907F}\u{514D}\u{540C}\u{9875}\u{591A}\u{4E2A}\u{8BC4}\u{8BBA}\u{8868}\u{5355}\u{5171}\u{4EAB} session \u{9A8C}\u{8BC1}\u{7801}\u{65F6}\u{88AB}\u{4E92}\u{76F8}\u{8986}\u{76D6}\u{3002}
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

        form.addEventListener('submit', function(e) {
            e.preventDefault();
            var nick = form.querySelector('[name=nickname]');
            var email = form.querySelector('[name=email]');
            var content = form.querySelector('[name=content]');
            if (nick && !nick.value.trim()) { frontToast('\u{8BF7}\u{8F93}\u{5165}\u{6635}\u{79F0}', 'error'); nick.focus(); return; }
            if (email && !email.value.trim()) { frontToast('\u{8BF7}\u{8F93}\u{5165}\u{90AE}\u{7BB1}', 'error'); email.focus(); return; }
            if (content && content.value.trim().length < 2) { frontToast('\u{8BC4}\u{8BBA}\u{5185}\u{5BB9}\u{592A}\u{77ED}\u{4E86}', 'error'); content.focus(); return; }

            var btn = form.querySelector('button[type=submit]') || form.querySelector('button');
            if (btn) btn.disabled = true;
            saveCommentIdentity(form);

            fetch('/comment/submit', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: new FormData(form)
            }).then(function(r) { return r.json(); }).then(function(d) {
                if (btn) btn.disabled = false;
                if (captchaImg) captchaImg.src = '/captcha?t=' + Date.now();
                var captchaInput = form.querySelector('[name=captcha]');
                if (captchaInput) captchaInput.value = '';
                if (!d || d.code !== 0) { frontToast((d && d.msg) || '\u{63D0}\u{4EA4}\u{5931}\u{8D25}', 'error'); return; }

                if (content) content.value = '';
                var pid = form.querySelector('[name=parent_id]');
                if (pid) pid.value = '0';
                form.classList.remove('is-replying');

                if (d.comment && !d.pending) {
                    appendComment(form, d.comment);
                    commentFlash(form, '\u{8BC4}\u{8BBA}\u{53D1}\u{5E03}\u{6210}\u{529F}', 'success');
                } else {
                    commentFlash(form, d.msg || '\u{8BC4}\u{8BBA}\u{5DF2}\u{63D0}\u{4EA4}\u{FF0C}\u{7B49}\u{5F85}\u{5BA1}\u{6838}\u{540E}\u{663E}\u{793A}', 'success');
                }
                form.dataset.replyTo = '';
            }).catch(function() {
                if (btn) btn.disabled = false;
                frontToast('\u{7F51}\u{7EDC}\u{9519}\u{8BEF}\u{FF0C}\u{63D0}\u{4EA4}\u{5931}\u{8D25}', 'error');
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
            // \u{8BB0}\u{5F55}\u{88AB}\u{56DE}\u{590D}\u{8005}\u{6635}\u{79F0}(\u{7528}\u{4E8E}\u{63D0}\u{4EA4}\u{540E}\u{5C55}\u{793A}"\u{56DE}\u{590D}\u{8005} \u{203A} \u{88AB}\u{56DE}\u{590D}\u{8005}"),\u{4E0D}\u{518D}\u{5F80}\u{8F93}\u{5165}\u{6846}\u{585E} @\u{6635}\u{79F0}
            form.dataset.replyTo = (btn.dataset.nickname || '').trim();

            form.classList.add('is-replying');
            form.scrollIntoView({ behavior: 'smooth', block: 'center' });
            if (textarea) {
                textarea.focus();
                textarea.setSelectionRange(textarea.value.length, textarea.value.length);
            }
        });
    }

    function bindToggleBtn(btn) {
        if (btn.dataset.lnBound) return; btn.dataset.lnBound = '1';
        btn.addEventListener('click', function() {
            var target = document.getElementById(btn.dataset.target || '');
            if (target) {
                target.classList.toggle('is-open');
            }
        });
    }

    // \u{70B9}\u{8D5E}\u{6492}\u{82B1}:\u{5728}\u{6309}\u{94AE}\u{5468}\u{56F4}\u{8FF8}\u{53D1}\u{5C0F}\u{5F69}\u{82B1}
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

    function updateLikeButton(btn, liked) {
        if (!btn) return;
        btn.classList.toggle('is-liked', !!liked);
        btn.setAttribute('aria-pressed', liked ? 'true' : 'false');
        var icon = btn.querySelector('i');
        if (!icon) return;
        if (icon.classList.contains('fa-heart')) {
            icon.className = (liked ? 'fa-solid' : 'fa-regular') + ' fa-heart';
        } else if (icon.classList.contains('fa-thumbs-up')) {
            icon.className = (liked ? 'fa-solid' : 'fa-regular') + ' fa-thumbs-up';
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
        root.querySelectorAll('.music-share-like-btn[data-music-id]').forEach(function(btn) {
            updateLikeButton(btn, hasLiked('music', btn.dataset.musicId || ''));
        });
        root.querySelectorAll('[data-music-disc-player]').forEach(function(player) {
            var btn = player.querySelector('[data-music-like]');
            updateLikeButton(btn, hasLiked('music', player.dataset.currentId || ''));
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
                frontToast('\u{5DF2}\u{7ECF}\u{70B9}\u{8D5E}\u{8FC7}\u{4E86}', 'success');
                return;
            }
            btn.disabled = true;
            fetch('/talk/' + encodeURIComponent(id) + '/like', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(function(res) { return res.json(); })
                .then(function(data) {
                    if (data && (data.code === 0 || data.code === 2)) {
                        if (count) count.textContent = data.likes;
                        rememberLiked('talk', id);
                        updateLikeButton(btn, true);
                        if (data.code === 2) {
                            frontToast(data.msg || '\u{5DF2}\u{7ECF}\u{70B9}\u{8D5E}\u{8FC7}\u{4E86}', 'success');
                            return;
                        }
                        likeConfetti(btn);
                        frontToast('\u{5DF2}\u{70B9}\u{8D5E}', 'success');
                    } else {
                        frontToast((data && data.msg) || '\u{70B9}\u{8D5E}\u{5931}\u{8D25}\u{FF0C}\u{8BF7}\u{7A0D}\u{540E}\u{518D}\u{8BD5}', 'error');
                    }
                })
                .catch(function() {
                    frontToast('\u{70B9}\u{8D5E}\u{5931}\u{8D25}\u{FF0C}\u{8BF7}\u{7A0D}\u{540E}\u{518D}\u{8BD5}', 'error');
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
            var playerLikes = player.querySelector('[data-music-likes]');
            if (playerLikes) playerLikes.textContent = value;
            var playerLikeBtn = player.querySelector('[data-music-like]');
            updateLikeButton(playerLikeBtn, true);
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
                frontToast('\u{5DF2}\u{7ECF}\u{559C}\u{6B22}\u{8FC7}\u{8FD9}\u{9996}\u{97F3}\u{4E50}\u{4E86}', 'success');
                return;
            }

            btn.disabled = true;
            fetch('/music/' + encodeURIComponent(id) + '/like', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            }).then(function(res) {
                return res.json();
            }).then(function(data) {
                if (data && (data.code === 0 || data.code === 2)) {
                    markMusicLiked(id, data.likes);
                    if (data.code === 2) {
                        frontToast(data.msg || '\u{5DF2}\u{7ECF}\u{559C}\u{6B22}\u{8FC7}\u{8FD9}\u{9996}\u{97F3}\u{4E50}\u{4E86}', 'success');
                        return;
                    }
                    likeConfetti(btn);
                    frontToast('\u{5DF2}\u{559C}\u{6B22}\u{8FD9}\u{9996}\u{97F3}\u{4E50}', 'success');
                } else {
                    frontToast((data && data.msg) || '\u{70B9}\u{8D5E}\u{5931}\u{8D25}\u{FF0C}\u{8BF7}\u{7A0D}\u{540E}\u{518D}\u{8BD5}', 'error');
                }
            }).catch(function() {
                frontToast('\u{70B9}\u{8D5E}\u{5931}\u{8D25}\u{FF0C}\u{8BF7}\u{7A0D}\u{540E}\u{518D}\u{8BD5}', 'error');
            }).finally(function() {
                btn.disabled = false;
            });
        });
    }

    // \u{97F3}\u{4E50}\u{5361}\u{7247}\u{64AD}\u{653E}\u{5668}(\u{81EA}\u{5B9A}\u{4E49} audio \u{63A7}\u{4EF6})
    function bindMusicCard(card) {
        if (card.dataset.lnBound) return; card.dataset.lnBound = '1';
        var audio = card.querySelector('audio');
        var btn = card.querySelector('.music-card-btn');
        if (!audio || !btn) return;
        var icon = btn.querySelector('i');
        var track = card.querySelector('.music-card-track');
        var played = card.querySelector('.music-card-played');
        var curEl = card.querySelector('.music-card-cur');
        var durEl = card.querySelector('.music-card-dur');
        var skipBtns = card.querySelectorAll('[data-music-skip]');

        function fmt(s) {
            s = Math.floor(s || 0);
            var m = Math.floor(s / 60), ss = s % 60;
            return m + ':' + (ss < 10 ? '0' : '') + ss;
        }

        function playToggle() {
            card.classList.remove('is-error');
            // \u{540C}\u{4E00}\u{65F6}\u{95F4}\u{53EA}\u{64AD}\u{653E}\u{4E00}\u{4E2A}
            document.querySelectorAll('.music-card audio, .music-disc-player audio').forEach(function(a) {
                if (a !== audio) { a.pause(); }
            });
            if (audio.paused) {
                var playing = audio.play();
                if (playing && typeof playing.catch === 'function') {
                    playing.catch(function() {
                        card.classList.add('is-error');
                        if (icon) icon.className = 'fa-solid fa-triangle-exclamation';
                    });
                }
            } else {
                audio.pause();
            }
        }

        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            playToggle();
        });
        card.addEventListener('click', function(e) {
            if (e.target.closest('button') || e.target.closest('.music-card-track')) return;
            playToggle();
        });
        card.addEventListener('keydown', function(e) {
            if (e.key !== 'Enter' && e.key !== ' ') return;
            e.preventDefault();
            playToggle();
        });
        audio.addEventListener('play', function() {
            card.classList.remove('is-error');
            card.classList.add('playing');
            if (icon) icon.className = 'fa-solid fa-pause';
        });
        audio.addEventListener('pause', function() {
            card.classList.remove('playing');
            if (icon) icon.className = 'fa-solid fa-play';
        });
        audio.addEventListener('ended', function() {
            card.classList.remove('playing');
            if (icon) icon.className = 'fa-solid fa-play';
            if (played) played.style.width = '0%';
            if (curEl) curEl.textContent = '0:00';
        });
        audio.addEventListener('loadedmetadata', function() {
            if (durEl) durEl.textContent = fmt(audio.duration);
        });
        audio.addEventListener('error', function() {
            card.classList.remove('playing');
            card.classList.add('is-error');
            if (icon) icon.className = 'fa-solid fa-triangle-exclamation';
            if (durEl) durEl.textContent = '\u{9519}\u{8BEF}';
        });
        audio.addEventListener('timeupdate', function() {
            if (audio.duration) {
                if (played) played.style.width = (audio.currentTime / audio.duration * 100) + '%';
                if (curEl) curEl.textContent = fmt(audio.currentTime);
            }
        });
        if (track) {
            track.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                if (!audio.duration) return;
                var rect = track.getBoundingClientRect();
                audio.currentTime = Math.max(0, Math.min(1, (e.clientX - rect.left) / rect.width)) * audio.duration;
            });
        }
        skipBtns.forEach(function(skipBtn) {
            skipBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                if (!audio.duration) return;
                var step = parseInt(skipBtn.dataset.musicSkip || '0', 10) || 0;
                audio.currentTime = Math.max(0, Math.min(audio.duration, audio.currentTime + step));
            });
        });
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

    function bindMusicDiscPlayer(player) {
        if (player.dataset.lnBound) return; player.dataset.lnBound = '1';
        var audio = player.querySelector('audio');
        var playBtn = player.querySelector('[data-music-play]');
        if (!audio || !playBtn) return;

        var tracks = Array.prototype.slice.call(document.querySelectorAll('[data-music-track]'));
        var prevBtn = player.querySelector('[data-music-prev]');
        var nextBtn = player.querySelector('[data-music-next]');
        var likeBtn = player.querySelector('[data-music-like]');
        var titleEl = player.querySelector('[data-music-title]');
        var artistEl = player.querySelector('[data-music-artist]');
        var albumEl = player.querySelector('[data-music-album]');
        var likesEl = player.querySelector('[data-music-likes]');
        var lyricsEl = player.querySelector('[data-music-lyrics]');
        var commentsRoot = document.querySelector('[data-music-comments]');
        var commentTitleEl = commentsRoot ? commentsRoot.querySelector('[data-music-comment-title]') : null;
        var commentCountEl = commentsRoot ? commentsRoot.querySelector('[data-music-comment-count]') : null;
        var commentForm = commentsRoot ? commentsRoot.querySelector('.music-comment-form') : null;
        var coverImg = player.querySelector('[data-music-cover]');
        var coverFallback = player.querySelector('[data-music-cover-fallback]');
        var progressTrack = player.querySelector('[data-music-progress]');
        var progressPlayed = player.querySelector('[data-music-progress-played]');
        var currentEl = player.querySelector('[data-music-current]');
        var durationEl = player.querySelector('[data-music-duration]');
        var playIcon = playBtn.querySelector('i');
        var playedIds = {};
        var currentIndex = Math.max(0, parseInt(player.dataset.currentIndex || '0', 10) || 0);

        function fmt(s) {
            s = Math.floor(s || 0);
            var m = Math.floor(s / 60), ss = s % 60;
            return m + ':' + (ss < 10 ? '0' : '') + ss;
        }

        function stripLrc(text) {
            text = String(text || '').replace(/\r\n?/g, '\n');
            if (!/\[\d{1,2}:\d{2}(?:[.:]\d{1,3})?\]/.test(text)) {
                return text;
            }
            return text.split('\n').map(function(line) {
                line = line.trim();
                if (!line || /^\[(ti|ar|al|by|offset|length|re):[^\]]*\]$/i.test(line)) {
                    return '';
                }
                return line.replace(/(?:\[\d{1,2}:\d{2}(?:[.:]\d{1,3})?\])+/g, '').trim();
            }).filter(Boolean).join('\n');
        }

        function renderLyrics(text, fallback) {
            if (!lyricsEl) return;
            lyricsEl.innerHTML = '';
            var lines = stripLrc(text).split(/\r?\n/)
                .map(function(line) { return line.trim(); })
                .filter(Boolean)
                .slice(0, 4);
            if (!lines.length) {
                lines = [fallback || '\u{6682}\u{65E0}\u{6B4C}\u{8BCD}\u{FF0C}\u{6309}\u{4E0B}\u{64AD}\u{653E}\u{8BA9}\u{8FD9}\u{9996}\u{6B4C}\u{5148}\u{54CD}\u{8D77}\u{6765}\u{3002}'];
            }
            lines.forEach(function(line) {
                var span = document.createElement('span');
                span.textContent = line;
                lyricsEl.appendChild(span);
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

            if (titleEl) titleEl.textContent = d.title || '\u{672A}\u{547D}\u{540D}\u{97F3}\u{4E50}';
            if (artistEl) artistEl.textContent = d.artist || '\u{672A}\u{77E5}\u{6B4C}\u{624B}';
            if (albumEl) albumEl.textContent = d.album ? (' \u{B7} ' + d.album) : '';
            if (likesEl) likesEl.textContent = d.likes || '0';
            updateLikeButton(likeBtn, hasLiked('music', d.id || ''));
            syncMusicComments(d.id || '', d.title || '\u{672A}\u{547D}\u{540D}\u{97F3}\u{4E50}', d.comments || '0');
            if (durationEl) durationEl.textContent = d.duration || '0:00';
            if (currentEl) currentEl.textContent = '0:00';
            if (progressPlayed) progressPlayed.style.width = '0%';
            renderLyrics(decodeBase64(d.lyrics || ''), d.description || '');

            if (coverImg && coverFallback) {
                if (d.cover) {
                    coverImg.src = d.cover;
                    coverImg.alt = d.title || '';
                    coverImg.classList.remove('is-hidden');
                    coverFallback.classList.add('is-hidden');
                } else {
                    coverImg.removeAttribute('src');
                    coverImg.alt = '';
                    coverImg.classList.add('is-hidden');
                    coverFallback.textContent = (d.title || '\u{266A}').trim().slice(0, 1) || '\u{266A}';
                    coverFallback.classList.remove('is-hidden');
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
            if (commentTitleEl) commentTitleEl.textContent = title || '\u{97F3}\u{4E50}';
            if (commentCountEl) commentCountEl.textContent = String(count || '0');
            if (commentForm) {
                var musicInput = commentForm.querySelector('[name=music_id]');
                var parentInput = commentForm.querySelector('[name=parent_id]');
                if (musicInput) musicInput.value = id || '';
                if (parentInput) parentInput.value = '0';
                commentForm.classList.remove('is-replying');
                commentForm.dataset.replyTo = '';
            }
        }

        function playAudio() {
            player.classList.remove('is-error');
            document.querySelectorAll('.music-card audio, .music-disc-player audio').forEach(function(a) {
                if (a !== audio) { a.pause(); }
            });
            var playing = audio.play();
            if (playing && typeof playing.catch === 'function') {
                playing.catch(function() {
                    player.classList.add('is-error');
                    if (playIcon) playIcon.className = 'fa-solid fa-triangle-exclamation';
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

        playBtn.addEventListener('click', function(e) {
            e.preventDefault();
            togglePlay();
        });

        if (prevBtn) {
            prevBtn.addEventListener('click', function(e) {
                e.preventDefault();
                applyTrack(currentIndex - 1, true);
            });
        }
        if (nextBtn) {
            nextBtn.addEventListener('click', function(e) {
                e.preventDefault();
                applyTrack(currentIndex + 1, true);
            });
        }

        tracks.forEach(function(track, index) {
            track.addEventListener('click', function() {
                applyTrack(index, true);
            });
        });

        if (progressTrack) {
            progressTrack.addEventListener('click', function(e) {
                if (!audio.duration) return;
                var rect = progressTrack.getBoundingClientRect();
                audio.currentTime = Math.max(0, Math.min(1, (e.clientX - rect.left) / rect.width)) * audio.duration;
            });
        }

        if (likeBtn) {
            updateLikeButton(likeBtn, hasLiked('music', player.dataset.currentId || ''));
            likeBtn.addEventListener('click', function(e) {
                e.preventDefault();
                if (likeBtn.disabled) return;
                var id = player.dataset.currentId || '';
                if (!id) return;
                if (hasLiked('music', id)) {
                    updateLikeButton(likeBtn, true);
                    frontToast('\u{5DF2}\u{7ECF}\u{559C}\u{6B22}\u{8FC7}\u{8FD9}\u{9996}\u{97F3}\u{4E50}\u{4E86}', 'success');
                    return;
                }
                likeBtn.disabled = true;
                fetch('/music/' + encodeURIComponent(id) + '/like', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                }).then(function(res) {
                    return res.json();
                }).then(function(data) {
                    if (data && (data.code === 0 || data.code === 2)) {
                        if (likesEl) likesEl.textContent = data.likes;
                        var active = tracks[currentIndex];
                        if (active) active.dataset.likes = String(data.likes);
                        markMusicLiked(id, data.likes);
                        if (data.code === 2) {
                            frontToast(data.msg || '\u{5DF2}\u{7ECF}\u{559C}\u{6B22}\u{8FC7}\u{8FD9}\u{9996}\u{97F3}\u{4E50}\u{4E86}', 'success');
                            return;
                        }
                        likeConfetti(likeBtn);
                        frontToast('\u{5DF2}\u{559C}\u{6B22}\u{8FD9}\u{9996}\u{97F3}\u{4E50}', 'success');
                    } else {
                        frontToast((data && data.msg) || '\u{70B9}\u{8D5E}\u{5931}\u{8D25}\u{FF0C}\u{8BF7}\u{7A0D}\u{540E}\u{518D}\u{8BD5}', 'error');
                    }
                }).catch(function() {
                    frontToast('\u{70B9}\u{8D5E}\u{5931}\u{8D25}\u{FF0C}\u{8BF7}\u{7A0D}\u{540E}\u{518D}\u{8BD5}', 'error');
                }).finally(function() {
                    likeBtn.disabled = false;
                });
            });
        }

        audio.addEventListener('play', function() {
            player.classList.remove('is-error');
            player.classList.add('is-playing');
            if (playIcon) playIcon.className = 'fa-solid fa-pause';
            var id = player.dataset.currentId || '';
            if (id && !playedIds[id]) {
                playedIds[id] = true;
                fetch('/music/' + encodeURIComponent(id) + '/play', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                }).catch(function() {});
            }
        });

        audio.addEventListener('pause', function() {
            player.classList.remove('is-playing');
            if (playIcon) playIcon.className = 'fa-solid fa-play';
        });

        audio.addEventListener('ended', function() {
            player.classList.remove('is-playing');
            if (playIcon) playIcon.className = 'fa-solid fa-play';
            if (tracks.length > 1) {
                applyTrack(currentIndex + 1, true);
            } else {
                if (progressPlayed) progressPlayed.style.width = '0%';
                if (currentEl) currentEl.textContent = '0:00';
            }
        });

        audio.addEventListener('loadedmetadata', function() {
            if (durationEl && audio.duration && Number.isFinite(audio.duration)) {
                durationEl.textContent = fmt(audio.duration);
            }
        });

        audio.addEventListener('timeupdate', function() {
            if (!audio.duration) return;
            if (currentEl) currentEl.textContent = fmt(audio.currentTime);
            if (progressPlayed) {
                progressPlayed.style.width = (audio.currentTime / audio.duration * 100) + '%';
            }
        });

        audio.addEventListener('error', function() {
            player.classList.remove('is-playing');
            player.classList.add('is-error');
            if (playIcon) playIcon.className = 'fa-solid fa-triangle-exclamation';
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
            root.classList.toggle('is-collapsed', collapsed);
            btn.setAttribute('aria-label', collapsed ? '\u{5C55}\u{5F00}\u{97F3}\u{4E50}\u{8BC4}\u{8BBA}' : '\u{6536}\u{8D77}\u{97F3}\u{4E50}\u{8BC4}\u{8BBA}');
        });
    }

    // \u{53D1}\u{5E03}\u{8868}\u{5355}:\u{56FE}\u{7247}\u{4E0A}\u{4F20}\u{6309}\u{94AE}
    function bindPublishForm(form) {
        if (form.dataset.lnBound) return; form.dataset.lnBound = '1';
        var btn = form.querySelector('.fp-upload-btn');
        var file = form.querySelector('.fp-upload-file');
        var imagesInput = form.querySelector('input[name="images"]');
        var musicBtn = form.querySelector('.fp-music-btn');
        var musicPanel = form.querySelector('.front-publish-music');
        var musicSelect = form.querySelector('select[name="music_id"]');
        if (musicBtn && musicPanel) {
            function syncMusicButton(open) {
                var hasMusic = musicSelect && String(musicSelect.value || '0') !== '0';
                musicBtn.classList.toggle('is-open', !!open);
                musicBtn.classList.toggle('is-active', hasMusic);
                musicBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
                musicBtn.title = hasMusic ? '\u{5DF2}\u{5173}\u{8054}\u{97F3}\u{4E50}' : '\u{9009}\u{62E9}\u{5173}\u{8054}\u{97F3}\u{4E50}';
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
            btn.innerHTML = window.siteLoadingSpinnerSvg('fp-upload-spinner') + '<span>\u{4E0A}\u{4F20}\u{4E2D}</span>';
            var data = new FormData();
            data.append('_csrf', csrf);
            data.append('purpose', 'talk');
            data.append('image', f);

            var xhr = new XMLHttpRequest();
            xhr.open('POST', '/admin/posts/upload-image');
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.upload.addEventListener('progress', function(e) {
                if (!e.lengthComputable) {
                    setUploadProgress(8, '\u{4E0A}\u{4F20}\u{4E2D}');
                    return;
                }
                var value = Math.max(1, Math.min(99, Math.round(e.loaded / e.total * 100)));
                setUploadProgress(value, value + '%');
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
                        finishUploadStatus('\u{5B8C}\u{6210}', 1200, 'done');
                        frontToast('\u{56FE}\u{7247}\u{4E0A}\u{4F20}\u{6210}\u{529F}', 'success');
                    } else {
                        finishUploadStatus('\u{5931}\u{8D25}', 1600, 'error');
                        frontToast((d && d.msg) || '\u{4E0A}\u{4F20}\u{5931}\u{8D25}', 'error');
                    }
                    return;
                }
                finishUploadStatus('\u{5931}\u{8D25}', 1600, 'error');
                frontToast('\u{4E0A}\u{4F20}\u{5931}\u{8D25}', 'error');
            });
            xhr.addEventListener('error', function() {
                finishUploadStatus('\u{5931}\u{8D25}', 1600, 'error');
                frontToast('\u{4E0A}\u{4F20}\u{5931}\u{8D25}', 'error');
            });
            xhr.addEventListener('abort', function() {
                finishUploadStatus('\u{5DF2}\u{53D6}\u{6D88}', 1200, 'error');
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

    function bindCopyButton(btn) {
        if (btn.dataset.lnBound) return; btn.dataset.lnBound = '1';
        var label = btn.dataset.copyLabel || '\u{5185}\u{5BB9}';
        var originalTitle = btn.getAttribute('title') || ('\u{590D}\u{5236}' + label);
        var originalAria = btn.getAttribute('aria-label') || originalTitle;

        btn.addEventListener('click', function(event) {
            event.preventDefault();
            var text = btn.dataset.copyText || absoluteCopyUrl(btn.dataset.copyUrl || '/rss.xml');
            var message = btn.dataset.copyMessage || (btn.classList.contains('footer-rss-copy') ? 'RSS \u{5730}\u{5740}\u{5DF2}\u{590D}\u{5236}' : (label + '\u{5DF2}\u{590D}\u{5236}'));
            if (!text) {
                frontToast('\u{6CA1}\u{6709}\u{53EF}\u{590D}\u{5236}\u{7684}\u{5185}\u{5BB9}', 'error');
                return;
            }

            copyText(text).then(function() {
                btn.classList.add('is-copied');
                btn.setAttribute('title', message);
                btn.setAttribute('aria-label', message);
                frontToast(message, 'success');
                setTimeout(function() {
                    btn.classList.remove('is-copied');
                    btn.setAttribute('title', originalTitle);
                    btn.setAttribute('aria-label', originalAria);
                }, 1800);
            }).catch(function() {
                frontToast('\u{590D}\u{5236}\u{5931}\u{8D25}\u{FF0C}\u{8BF7}\u{7A0D}\u{540E}\u{91CD}\u{8BD5}', 'error');
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

    // \u{7EDF}\u{4E00}\u{7ED1}\u{5B9A}\u{52A8}\u{6001}\u{4EA4}\u{4E92}(\u{521D}\u{59CB} + \u{52A0}\u{8F7D}\u{66F4}\u{591A}\u{540E}\u{7684}\u{65B0}\u{5185}\u{5BB9},\u{5E26}\u{53BB}\u{91CD}\u{5B88}\u{536B})
    function bindDynamic(root) {
        root = root || document;
        bindToastSeeds(root);
        root.querySelectorAll('.comment-form').forEach(bindCommentForm);
        root.querySelectorAll('.comment-reply-btn').forEach(bindReplyBtn);
        root.querySelectorAll('.talk-comment-toggle').forEach(bindToggleBtn);
        root.querySelectorAll('.talk-like-btn').forEach(bindLikeBtn);
        root.querySelectorAll('.music-share-like-btn').forEach(bindMusicShareLike);
        root.querySelectorAll('.music-card').forEach(bindMusicCard);
        root.querySelectorAll('.music-disc-player').forEach(bindMusicDiscPlayer);
        root.querySelectorAll('[data-music-comments-toggle]').forEach(bindMusicCommentsToggle);
        root.querySelectorAll('.front-publish-form').forEach(bindPublishForm);
        root.querySelectorAll('.footer-rss-copy, [data-copy-text]').forEach(bindCopyButton);
        hydrateStoredLikeStates(root);
        bindImages(root);
    }
    bindDynamic(document);

    // \u{56FE}\u{7247}\u{61D2}\u{52A0}\u{8F7D} + ViewImage \u{5206}\u{7EC4}\u{706F}\u{7BB1}
    function finishImageLoad(img, wrapper) {
        img.classList.remove('is-image-loading');
        wrapper.classList.remove('is-loading');
        wrapper.classList.add('is-loaded');
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

    function bindImages(root) {
        root = root || document;
        root.querySelectorAll('.talk-images, .post-content, .page-content').forEach(function(group) {
            group.setAttribute('view-image', '');
        });

        var images = Array.prototype.slice.call(root.querySelectorAll('.post-cover img, .post-content img, .page-content img, .talk-images img'));
        var postCoverImages = Array.prototype.slice.call(root.querySelectorAll('.post-hero-card .post-cover img'));

        images.forEach(function(img) {
            if (!img.hasAttribute('loading')) {
                img.setAttribute('loading', 'lazy');
            }
            img.setAttribute('decoding', 'async');
            if (img.closest('.post-hero-card')) {
                img.setAttribute('no-view', '');
            } else {
                img.classList.add('view-image-target');
            }
            prepareImageLoading(img);
        });

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

        if (window.ViewImage) {
            window.ViewImage.init('[view-image] img');
        }
    }

    // \u{5BFC}\u{822A}:\u{60AC}\u{505C}\u{300C}\u{6587}\u{7AE0}\u{300D}\u{65F6}\u{6574}\u{4F53}\u{76D2}\u{5B50}\u{5411}\u{4E0B}\u{5C55}\u{5F00}\u{5206}\u{7C7B},\u{5E76}\u{9A71}\u{52A8}\u{80F6}\u{56CA}\u{6ED1}\u{5757}
    (function() {
        var shell = document.getElementById('nav-shell');
        if (!shell) return;
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
                    moveIndicator(activeItem());
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
                moveIndicator(activeItem());
            });

            items.forEach(function(item) {
                item.addEventListener('mouseenter', function() {
                    moveIndicator(item);
                });
                item.addEventListener('focus', function() {
                    moveIndicator(item);
                });
            });

            row.addEventListener('focusout', function(e) {
                if (!row.contains(e.relatedTarget)) {
                    moveIndicator(activeItem());
                }
            });

            window.addEventListener('resize', function() {
                updateScrolledNav();
                syncShellWidth();
                moveIndicator(activeItem());
            });

            window.addEventListener('load', function() {
                updateScrolledNav();
                syncShellWidth();
                moveIndicator(activeItem());
            });

            window.addEventListener('scroll', function() {
                if (scrollTicking) return;
                scrollTicking = true;
                requestAnimationFrame(function() {
                    scrollTicking = false;
                    updateScrolledNav();
                    syncShellWidth();
                    moveIndicator(activeItem());
                });
            }, { passive: true });

            if (document.fonts && document.fonts.ready) {
                document.fonts.ready.then(function() {
                    updateScrolledNav();
                    syncShellWidth();
                    moveIndicator(activeItem());
                });
            }
        }

        var trigger = shell.querySelector('.nav-dd-trigger');
        if (!trigger) return;
        trigger.addEventListener('mouseenter', function() { shell.classList.add('nav-open'); });
        shell.addEventListener('mouseleave', function() {
            shell.classList.remove('nav-open');
            moveIndicator(activeItem());
        });
    })();

    // \u{52A0}\u{8F7D}\u{66F4}\u{591A}:\u{9996}\u{6B21}\u{81EA}\u{52A8}\u{52A0}\u{8F7D},\u{4E4B}\u{540E}\u{624B}\u{52A8};\u{5230}\u{5E95}\u{663E}\u{793A}"\u{6CA1}\u{6709}\u{66F4}\u{591A}\u{5185}\u{5BB9}"
    document.querySelectorAll('.load-more').forEach(function(lm) {
        var pages = parseInt(lm.dataset.pages, 10) || 1;
        var page = parseInt(lm.dataset.page, 10) || 1;
        var base = lm.dataset.base || '';
        var btn = lm.querySelector('.load-more-btn');
        var loading = lm.querySelector('.load-more-loading');
        var endEl = lm.querySelector('.load-more-end');
        var container = (lm.parentNode && lm.parentNode.querySelector('.js-list-items')) || document.querySelector('.js-list-items');
        var busy = false, autoUsed = false;

        function showEnd() {
            if (btn) btn.hidden = true;
            if (loading) loading.hidden = true;
            if (endEl) endEl.hidden = false;
            window.removeEventListener('scroll', onScroll);
        }
        if (page >= pages || !container) { showEnd(); return; }

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
                    else if (autoUsed && btn) btn.hidden = false;
                })
                .catch(function() {
                    busy = false;
                    if (loading) loading.hidden = true;
                    if (btn) { btn.hidden = false; btn.textContent = '\u{52A0}\u{8F7D}\u{5931}\u{8D25}\u{FF0C}\u{70B9}\u{51FB}\u{91CD}\u{8BD5}'; }
                });
        }

        // \u{9996}\u{6B21}:\u{6EDA}\u{52A8}\u{5230}\u{63A5}\u{8FD1}\u{65F6}\u{81EA}\u{52A8}\u{52A0}\u{8F7D}(\u{521D}\u{59CB}\u{4E5F}\u{68C0}\u{6D4B}\u{4E00}\u{6B21},\u{77ED}\u{9875}\u{9762}\u{76F4}\u{63A5}\u{81EA}\u{52A8})
        function onScroll() {
            if (autoUsed || busy) return;
            var r = lm.getBoundingClientRect();
            if (r.top < window.innerHeight + 300) {
                autoUsed = true;
                window.removeEventListener('scroll', onScroll);
                load();
            }
        }
        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();
        if (btn) btn.addEventListener('click', function() { load(); });
    });

    console.log('%c LiteNote %c PHP 8.5 ', 'background:#2c7be5;color:#fff;padding:2px 6px;border-radius:3px', 'color:#888');
})();
