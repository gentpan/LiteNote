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

    // 正文图片懒加载 + ViewImage 灯箱
    (function() {
        var images = Array.prototype.slice.call(document.querySelectorAll('.post-cover img, .post-content img, .page-content img, .shuoshuo-images img'));
        var active = null;

        if (!images.length) {
            return;
        }

        images.forEach(function(img) {
            if (!img.hasAttribute('loading')) {
                img.setAttribute('loading', 'lazy');
            }
            img.setAttribute('decoding', 'async');
            img.classList.add('view-image-target');
            img.addEventListener('click', function(event) {
                event.preventDefault();
                openLightbox(img);
            });
        });

        function openLightbox(source) {
            closeLightbox();

            var overlay = document.createElement('div');
            overlay.className = 'view-image-overlay';
            overlay.setAttribute('role', 'dialog');
            overlay.setAttribute('aria-modal', 'true');
            overlay.setAttribute('aria-label', '图片预览');

            var stage = document.createElement('div');
            stage.className = 'view-image-stage';

            var spinner = document.createElement('div');
            spinner.className = 'view-image-spinner';

            var img = document.createElement('img');
            img.className = 'view-image-img';
            img.alt = source.alt || '图片预览';
            img.decoding = 'async';
            img.onload = function() {
                overlay.classList.add('is-loaded');
            };
            img.src = source.currentSrc || source.src;

            var close = document.createElement('button');
            close.type = 'button';
            close.className = 'view-image-close';
            close.setAttribute('aria-label', '关闭图片预览');
            close.innerHTML = '&times;';

            var captionText = source.getAttribute('alt') || source.getAttribute('title') || '';
            var caption = null;
            if (captionText) {
                caption = document.createElement('div');
                caption.className = 'view-image-caption';
                caption.textContent = captionText;
            }

            stage.appendChild(spinner);
            stage.appendChild(img);
            overlay.appendChild(stage);
            overlay.appendChild(close);
            if (caption) {
                overlay.appendChild(caption);
            }

            overlay.addEventListener('click', function(event) {
                if (event.target === overlay || event.target === close) {
                    closeLightbox();
                }
            });

            document.body.appendChild(overlay);
            document.body.classList.add('view-image-open');
            active = overlay;
            close.focus({ preventScroll: true });
        }

        function closeLightbox() {
            if (!active) {
                return;
            }
            active.remove();
            active = null;
            document.body.classList.remove('view-image-open');
        }

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeLightbox();
            }
        });
    })();

    console.log('%c LiteNote %c PHP 8.5 ', 'background:#2c7be5;color:#fff;padding:2px 6px;border-radius:3px', 'color:#888');
})();
