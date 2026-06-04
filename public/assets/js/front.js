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

    // AJAX 提交后把新评论插入列表(说说 / 文章两种结构)
    function appendComment(form, c) {
        var talkInput = form.querySelector('[name=talk_id]');
        var isTalk = talkInput && parseInt(talkInput.value, 10) > 0;
        var parentId = parseInt(c.parent_id, 10) || 0;

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
                var ar = document.createElement('span'); ar.className = 'reply-arrow'; ar.textContent = ' › ';
                var tg = document.createElement('span'); tg.className = 'reply-target'; tg.textContent = rtn;
                li.appendChild(ar); li.appendChild(tg);
            }
            var time = document.createElement('span'); time.className = 'comment-time'; time.innerHTML = ' · ' + c.time;
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
            var item = document.createElement('li');
            item.className = 'comment-item' + (parentId > 0 ? ' comment-reply' : '');
            item.setAttribute('data-id', c.id);
            var rtn2 = (parentId > 0) ? (form.dataset.replyTo || '') : '';
            var metaExtra = rtn2 ? '<span class="reply-arrow">›</span><span class="reply-target"></span>' : '';
            item.innerHTML = '<div class="comment-body"><div class="comment-meta"><strong></strong>' + metaExtra + ' <span class="ct"></span></div><div class="comment-content"></div></div>';
            item.querySelector('strong').textContent = c.nickname;
            if (rtn2) item.querySelector('.reply-target').textContent = rtn2;
            item.querySelector('.ct').innerHTML = '· ' + c.time;
            item.querySelector('.comment-content').textContent = c.content;

            if (parentId > 0) {
                var root2 = rootLiOf(section, parentId);
                if (root2) { replyListIn(root2, 'comment-reply-list').appendChild(item); }
                else { var u0 = section.querySelector('.comment-list'); if (u0) u0.appendChild(item); }
            } else {
                var ul = section.querySelector('.comment-list');
                if (!ul) { ul = document.createElement('ul'); ul.className = 'comment-list'; section.insertBefore(ul, form); }
                ul.appendChild(item);
            }
        }
    }

    // 评论提交后的内联提示
    function commentFlash(form, msg, type) {
        var old = form.parentNode.querySelector('.comment-ajax-flash');
        if (old) old.remove();
        var div = document.createElement('div');
        div.className = 'comment-ajax-flash alert alert-' + (type === 'success' ? 'success' : 'error');
        div.textContent = msg;
        form.parentNode.insertBefore(div, form);
        setTimeout(function() { if (div.parentNode) div.remove(); }, 4000);
    }

    // 评论表单(可对动态加载的内容重复调用)
    function bindCommentForm(form) {
        if (form.dataset.lnBound) return; form.dataset.lnBound = '1';
        fillCommentIdentity(form);

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

        form.addEventListener('submit', function(e) {
            e.preventDefault();
            var nick = form.querySelector('[name=nickname]');
            var email = form.querySelector('[name=email]');
            var content = form.querySelector('[name=content]');
            if (nick && !nick.value.trim()) { alert('请输入昵称'); nick.focus(); return; }
            if (email && !email.value.trim()) { alert('请输入邮箱'); email.focus(); return; }
            if (content && content.value.trim().length < 2) { alert('评论内容太短了'); content.focus(); return; }

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
                if (!d || d.code !== 0) { alert((d && d.msg) || '提交失败'); return; }

                if (content) content.value = '';
                var pid = form.querySelector('[name=parent_id]');
                if (pid) pid.value = '0';
                form.classList.remove('is-replying');

                if (d.comment && !d.pending) {
                    appendComment(form, d.comment);
                    commentFlash(form, '评论发布成功', 'success');
                } else {
                    commentFlash(form, d.msg || '评论已提交，等待审核后显示', 'success');
                }
                form.dataset.replyTo = '';
            }).catch(function() {
                if (btn) btn.disabled = false;
                alert('网络错误，提交失败');
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
            // 记录被回复者昵称(用于提交后展示"回复者 › 被回复者"),不再往输入框塞 @昵称
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

    function bindLikeBtn(btn) {
        if (btn.dataset.lnBound) return; btn.dataset.lnBound = '1';
        btn.addEventListener('click', function() {
            if (btn.disabled) {
                return;
            }
            var id = btn.dataset.id;
            var count = btn.querySelector('.like-count');
            btn.disabled = true;
            fetch('/talk/' + encodeURIComponent(id) + '/like', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(function(res) { return res.json(); })
                .then(function(data) {
                    if (data && data.code === 0 && count) {
                        count.textContent = data.likes;
                        btn.classList.add('is-liked');
                        likeConfetti(btn);
                    }
                })
                .catch(function() {
                    btn.disabled = false;
                });
        });
    }

    // 音乐卡片播放器(自定义 audio 控件)
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

        function fmt(s) {
            s = Math.floor(s || 0);
            var m = Math.floor(s / 60), ss = s % 60;
            return m + ':' + (ss < 10 ? '0' : '') + ss;
        }

        btn.addEventListener('click', function() {
            // 同一时间只播放一个
            document.querySelectorAll('.music-card audio').forEach(function(a) {
                if (a !== audio) { a.pause(); }
            });
            if (audio.paused) { audio.play(); } else { audio.pause(); }
        });
        audio.addEventListener('play', function() {
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
        audio.addEventListener('timeupdate', function() {
            if (audio.duration) {
                if (played) played.style.width = (audio.currentTime / audio.duration * 100) + '%';
                if (curEl) curEl.textContent = fmt(audio.currentTime);
            }
        });
        if (track) {
            track.addEventListener('click', function(e) {
                if (!audio.duration) return;
                var rect = track.getBoundingClientRect();
                audio.currentTime = Math.max(0, Math.min(1, (e.clientX - rect.left) / rect.width)) * audio.duration;
            });
        }
    }

    // 发布表单:图片上传按钮
    function bindPublishForm(form) {
        if (form.dataset.lnBound) return; form.dataset.lnBound = '1';
        var btn = form.querySelector('.fp-upload-btn');
        var file = form.querySelector('.fp-upload-file');
        var imagesInput = form.querySelector('input[name="images"]');
        if (!btn || !file || !imagesInput) return;
        var csrfEl = form.querySelector('input[name="_csrf"]');
        var csrf = csrfEl ? csrfEl.value : '';

        btn.addEventListener('click', function() { file.click(); });
        file.addEventListener('change', function() {
            var f = file.files && file.files[0];
            if (!f) return;
            var orig = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> 上传中';
            var data = new FormData();
            data.append('_csrf', csrf);
            data.append('purpose', 'talk');
            data.append('image', f);
            fetch('/admin/posts/upload-image', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: data
            })
                .then(function(r) { return r.json(); })
                .then(function(d) {
                    if (d && d.code === 0 && d.data && d.data.url) {
                        var cur = imagesInput.value.trim().replace(/,\s*$/, '');
                        imagesInput.value = cur ? (cur + ',' + d.data.url) : d.data.url;
                    } else {
                        alert((d && d.msg) || '上传失败');
                    }
                })
                .catch(function() { alert('上传失败'); })
                .then(function() {
                    btn.disabled = false;
                    btn.innerHTML = orig;
                    file.value = '';
                });
        });
    }

    // 统一绑定动态交互(初始 + 加载更多后的新内容,带去重守卫)
    function bindDynamic(root) {
        root = root || document;
        root.querySelectorAll('.comment-form').forEach(bindCommentForm);
        root.querySelectorAll('.comment-reply-btn').forEach(bindReplyBtn);
        root.querySelectorAll('.talk-comment-toggle').forEach(bindToggleBtn);
        root.querySelectorAll('.talk-like-btn').forEach(bindLikeBtn);
        root.querySelectorAll('.music-card').forEach(bindMusicCard);
        root.querySelectorAll('.front-publish-form').forEach(bindPublishForm);
    }
    bindDynamic(document);

    // 图片懒加载 + Tokinx ViewImage 灯箱
    (function() {
        var images = Array.prototype.slice.call(document.querySelectorAll('.post-cover img, .post-content img, .page-content img, .talk-images img'));
        var postCoverImages = Array.prototype.slice.call(document.querySelectorAll('.post-hero-card .post-cover img'));

        if (!images.length) {
            return;
        }

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
            img.style.cursor = 'default';
            img.setAttribute('draggable', 'false');
            img.addEventListener('contextmenu', function(event) {
                event.preventDefault();
            });
        });

    })();

    // 自定义图片灯箱:单图查看(不串联其它说说的图),打开时隐藏导航,尺寸由 CSS 控制
    (function() {
        var overlay = null, imgEl = null;
        function close() {
            if (!overlay) return;
            overlay.classList.remove('is-open');
            document.body.classList.remove('lightbox-open');
        }
        function open(src, alt) {
            if (!overlay) {
                overlay = document.createElement('div');
                overlay.className = 'img-lightbox';
                imgEl = document.createElement('img');
                overlay.appendChild(imgEl);
                overlay.addEventListener('click', close);
                document.body.appendChild(overlay);
            }
            imgEl.src = src;
            imgEl.alt = alt || '';
            document.body.classList.add('lightbox-open');
            requestAnimationFrame(function() { overlay.classList.add('is-open'); });
        }
        document.addEventListener('click', function(e) {
            var t = e.target;
            if (t && t.tagName === 'IMG' && t.closest && t.closest('.talk-images, .post-content, .page-content')) {
                e.preventDefault();
                open(t.currentSrc || t.src, t.alt);
            }
        });
        document.addEventListener('keydown', function(e) { if (e.key === 'Escape') close(); });
    })();

    // 导航:悬停「文章」时整体盒子向下展开分类,移出盒子收起
    (function() {
        var shell = document.getElementById('nav-shell');
        if (!shell) return;
        var trigger = shell.querySelector('.nav-dd-trigger');
        if (!trigger) return;
        trigger.addEventListener('mouseenter', function() { shell.classList.add('nav-open'); });
        shell.addEventListener('mouseleave', function() { shell.classList.remove('nav-open'); });
    })();

    // 加载更多:首次自动加载,之后手动;到底显示"没有更多内容"
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
                    if (btn) { btn.hidden = false; btn.textContent = '加载失败，点击重试'; }
                });
        }

        // 首次:滚动到接近时自动加载(初始也检测一次,短页面直接自动)
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
