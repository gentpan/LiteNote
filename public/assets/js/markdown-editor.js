// LiteNote Markdown editor
(function() {
    'use strict';

    var root = document.querySelector('.markdown-editor');
    if (!root) return;

    var textarea = document.getElementById('editor-md');
    var preview = document.getElementById('md-preview');
    var words = document.getElementById('md-words');
    var lines = document.getElementById('md-lines');
    var filePicker = document.getElementById('md-file-picker');
    var previewUrl = root.getAttribute('data-preview-url') || '';
    var csrf = root.getAttribute('data-csrf') || '';
    var previewTimer = null;

    function insert(before, after, placeholder) {
        var start = textarea.selectionStart || 0;
        var end = textarea.selectionEnd || 0;
        var selected = textarea.value.slice(start, end) || placeholder;
        var text = before + selected + after;
        textarea.setRangeText(text, start, end, 'end');
        textarea.focus();
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
    }

    function insertLine(prefix, placeholder) {
        var start = textarea.selectionStart || 0;
        var end = textarea.selectionEnd || 0;
        var selected = textarea.value.slice(start, end) || placeholder;
        var lines = selected.split('\n').map(function(line) {
            return prefix + line;
        }).join('\n');
        textarea.setRangeText(lines, start, end, 'end');
        textarea.focus();
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
    }

    function command(type) {
        var table = '| 列一 | 列二 |\n| --- | --- |\n| 内容 | 内容 |';
        switch (type) {
            case 'heading': insertLine('## ', '小标题'); break;
            case 'bold': insert('**', '**', '加粗文字'); break;
            case 'italic': insert('*', '*', '斜体文字'); break;
            case 'quote': insertLine('> ', '引用内容'); break;
            case 'code': insert('```php\n', '\n```', 'echo "Hello LiteNote";'); break;
            case 'link': insert('[', '](https://example.com)', '链接文字'); break;
            case 'image': insert('![', '](/assets/uploads/image.jpg)', '图片描述'); break;
            case 'ul': insertLine('- ', '列表项'); break;
            case 'ol': insertLine('1. ', '列表项'); break;
            case 'table': insert('\n', '\n', table); break;
        }
    }

    function updateStats() {
        var value = textarea.value;
        var plain = value.replace(/[#>*_`\-[\]()!|]/g, '').trim();
        var count = plain ? plain.length : 0;
        var lineCount = value ? value.split('\n').length : 0;
        words.textContent = count + ' 字';
        lines.textContent = lineCount + ' 行';
    }

    function updatePreview() {
        if (!previewUrl) return;
        var data = new URLSearchParams();
        data.set('_csrf', csrf);
        data.set('markdown', textarea.value);
        fetch(previewUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: data.toString()
        }).then(function(res) {
            return res.ok ? res.json() : Promise.reject();
        }).then(function(data) {
            preview.innerHTML = data.html || '<div class="empty">预览会显示在这里</div>';
        }).catch(function() {
            preview.innerHTML = '<div class="empty">预览暂时不可用</div>';
        });
    }

    function schedulePreview() {
        updateStats();
        clearTimeout(previewTimer);
        previewTimer = setTimeout(updatePreview, 260);
    }

    root.querySelectorAll('[data-md]').forEach(function(btn) {
        btn.addEventListener('click', function() {
            command(btn.getAttribute('data-md'));
        });
    });

    if (filePicker) {
        filePicker.addEventListener('change', function() {
            var file = filePicker.files && filePicker.files[0];
            if (!file) return;
            var reader = new FileReader();
            reader.onload = function() {
                textarea.value = String(reader.result || '');
                textarea.dispatchEvent(new Event('input', { bubbles: true }));
            };
            reader.readAsText(file);
        });
    }

    textarea.addEventListener('input', schedulePreview);
    textarea.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'b') {
            e.preventDefault();
            command('bold');
        }
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'i') {
            e.preventDefault();
            command('italic');
        }
    });

    updateStats();
    updatePreview();
})();
