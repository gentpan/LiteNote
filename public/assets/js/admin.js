// 后台脚本
(function() {
    'use strict';

    // 全选
    var checkAll = document.getElementById('check-all');
    if (checkAll) {
        checkAll.addEventListener('change', function() {
            document.querySelectorAll('input[name="ids[]"]').forEach(function(cb) {
                cb.checked = checkAll.checked;
            });
        });
    }

    // 表单提交禁用按钮
    document.querySelectorAll('form').forEach(function(form) {
        form.addEventListener('submit', function() {
            var btn = form.querySelector('button[type=submit]');
            if (btn) {
                btn.disabled = true;
                btn.textContent = '处理中...';
                setTimeout(function() { btn.disabled = false; }, 5000);
            }
        });
    });

    // 离开页面前提示（编辑文章/页面时）
    var isDirty = false;
    document.querySelectorAll('input, textarea, select').forEach(function(el) {
        el.addEventListener('change', function() { isDirty = true; });
    });
    window.addEventListener('beforeunload', function(e) {
        if (isDirty) {
            e.preventDefault();
            e.returnValue = '有未保存的修改，确定离开？';
        }
    });
    document.querySelectorAll('form').forEach(function(form) {
        form.addEventListener('submit', function() { isDirty = false; });
    });

    // 后台 flash toast
    document.querySelectorAll('.admin-toast').forEach(function(toast) {
        var close = function() {
            if (toast.classList.contains('is-leaving')) {
                return;
            }
            toast.classList.add('is-leaving');
            setTimeout(function() {
                toast.remove();
            }, 180);
        };

        var timer = setTimeout(close, 3600);
        var closeBtn = toast.querySelector('.admin-toast-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', function() {
                clearTimeout(timer);
                close();
            });
        }
    });
})();
