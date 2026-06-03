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

    // 图片点击放大（简单 lightbox）
    document.querySelectorAll('.post-content img, .page-content img').forEach(function(img) {
        img.style.cursor = 'zoom-in';
        img.addEventListener('click', function() {
            var overlay = document.createElement('div');
            overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.85);z-index:9999;display:flex;align-items:center;justify-content:center;cursor:zoom-out;';
            var big = document.createElement('img');
            big.src = img.src;
            big.style.cssText = 'max-width:90%;max-height:90%;box-shadow:0 0 20px rgba(255,255,255,0.2);';
            overlay.appendChild(big);
            overlay.addEventListener('click', function() { document.body.removeChild(overlay); });
            document.body.appendChild(overlay);
        });
    });

    console.log('%c LiteNote %c PHP 8.5 ', 'background:#2c7be5;color:#fff;padding:2px 6px;border-radius:3px', 'color:#888');
})();
