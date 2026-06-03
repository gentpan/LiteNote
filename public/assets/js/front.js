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

    // 图片懒加载 + Tokinx ViewImage 灯箱
    (function() {
        var images = Array.prototype.slice.call(document.querySelectorAll('.post-cover img, .post-content img, .page-content img, .shuoshuo-images img'));

        if (!images.length) {
            return;
        }

        images.forEach(function(img) {
            if (!img.hasAttribute('loading')) {
                img.setAttribute('loading', 'lazy');
            }
            img.setAttribute('decoding', 'async');
            img.classList.add('view-image-target');
        });

        if (window.ViewImage) {
            ViewImage.init('.post-cover img, .post-content img, .page-content img, .shuoshuo-images img');
        }
    })();

    console.log('%c LiteNote %c PHP 8.5 ', 'background:#2c7be5;color:#fff;padding:2px 6px;border-radius:3px', 'color:#888');
})();
