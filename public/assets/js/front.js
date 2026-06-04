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

    // 评论表单提交时的简单校验
    document.querySelectorAll('.comment-form').forEach(function(form) {
        fillCommentIdentity(form);

        form.addEventListener('submit', function(e) {
            var nick = form.querySelector('[name=nickname]');
            var email = form.querySelector('[name=email]');
            var content = form.querySelector('[name=content]');
            if (nick && !nick.value.trim()) {
                e.preventDefault();
                alert('请输入昵称');
                nick.focus();
                return;
            }
            if (email && !email.value.trim()) {
                e.preventDefault();
                alert('请输入邮箱');
                email.focus();
                return;
            }
            if (content && content.value.trim().length < 2) {
                e.preventDefault();
                alert('评论内容太短了');
                content.focus();
                return;
            }

            saveCommentIdentity(form);
        });
    });

    document.querySelectorAll('.comment-reply-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var scope = btn.closest('.comments') || btn.closest('.shuoshuo-comments') || document;
            var form = scope.querySelector('.comment-form');
            if (!form) {
                return;
            }

            var parentInput = form.querySelector('[name=parent_id]');
            var textarea = form.querySelector('[name=content]');
            var nickname = (btn.dataset.nickname || '').trim();
            var prefix = nickname ? '@' + nickname + ' ' : '';

            if (parentInput) {
                parentInput.value = btn.dataset.parentId || '0';
            }

            if (textarea && prefix && textarea.value.indexOf(prefix) !== 0) {
                textarea.value = prefix + textarea.value.replace(/^@\S+\s*/, '');
            }

            form.classList.add('is-replying');
            form.scrollIntoView({ behavior: 'smooth', block: 'center' });
            if (textarea) {
                textarea.focus();
                textarea.setSelectionRange(textarea.value.length, textarea.value.length);
            }
        });
    });

    document.querySelectorAll('.shuoshuo-comment-toggle').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var target = document.getElementById(btn.dataset.target || '');
            if (target) {
                target.classList.toggle('is-open');
            }
        });
    });

    document.querySelectorAll('.shuoshuo-like-btn').forEach(function(btn) {
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
                    }
                })
                .catch(function() {
                    btn.disabled = false;
                });
        });
    });

    // 图片懒加载 + Tokinx ViewImage 灯箱
    (function() {
        var images = Array.prototype.slice.call(document.querySelectorAll('.post-cover img, .post-content img, .page-content img, .shuoshuo-images img'));
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

        if (window.ViewImage) {
            ViewImage.init('.post-content img, .page-content img, .shuoshuo-images img');
        }
    })();

    console.log('%c LiteNote %c PHP 8.5 ', 'background:#2c7be5;color:#fff;padding:2px 6px;border-radius:3px', 'color:#888');
})();
