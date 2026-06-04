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

    // 评论表单提交时的简单校验
    document.querySelectorAll('.comment-form').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            var nick = form.querySelector('[name=nickname]');
            var content = form.querySelector('[name=content]');
            if (nick && !nick.value.trim()) {
                e.preventDefault();
                alert('请输入昵称');
                nick.focus();
                return;
            }
            if (content && content.value.trim().length < 2) {
                e.preventDefault();
                alert('评论内容太短了');
                content.focus();
                return;
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
            fetch('/shuoshuo/' + encodeURIComponent(id) + '/like', {
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
