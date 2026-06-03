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
    var imagePicker = document.getElementById('md-image-picker');
    var titleInput = document.getElementById('post-title');
    var summaryInput = document.getElementById('post-summary');
    var coverUrl = document.getElementById('cover-url');
    var coverBtn = document.getElementById('cover-upload-btn');
    var coverFile = document.getElementById('cover-file');
    var coverPreview = document.getElementById('cover-preview');
    var previewUrl = root.getAttribute('data-preview-url') || '';
    var uploadUrl = root.getAttribute('data-upload-url') || '';
    var summaryUrl = root.getAttribute('data-summary-url') || '';
    var csrf = root.getAttribute('data-csrf') || '';
    var previewTimer = null;

    function setBusy(el, busy, text) {
        if (!el) return;
        if (busy) {
            el.dataset.originalText = el.innerHTML;
            el.disabled = true;
            if (text) el.innerHTML = text;
        } else {
            el.disabled = false;
            if (el.dataset.originalText) el.innerHTML = el.dataset.originalText;
        }
    }

    function uploadImage(file, purpose) {
        if (!uploadUrl || !file) {
            return Promise.reject(new Error('上传接口不可用'));
        }
        var data = new FormData();
        data.append('_csrf', csrf);
        data.append('purpose', purpose || 'post');
        data.append('image', file);
        return fetch(uploadUrl, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: data
        }).then(function(res) {
            return res.ok ? res.json() : Promise.reject(new Error('上传失败'));
        }).then(function(data) {
            if (data.code !== 0) {
                throw new Error(data.msg || '上传失败');
            }
            return data.data;
        });
    }

    function updateCoverPreview(url) {
        if (!coverPreview) return;
        var img = coverPreview.querySelector('img');
        if (!url) {
            coverPreview.classList.add('hidden');
            if (img) img.removeAttribute('src');
            return;
        }
        if (img) img.src = url;
        coverPreview.classList.remove('hidden');
    }

    if (coverBtn && coverFile) {
        coverBtn.addEventListener('click', function() {
            coverFile.click();
        });
        coverFile.addEventListener('change', function() {
            var file = coverFile.files && coverFile.files[0];
            if (!file) return;
            setBusy(coverBtn, true, '<i class="fa-solid fa-spinner fa-spin"></i> 上传中');
            uploadImage(file, 'cover').then(function(data) {
                if (coverUrl) coverUrl.value = data.url || '';
                updateCoverPreview(data.url || '');
            }).catch(function(err) {
                alert(err.message || '特色图上传失败');
            }).finally(function() {
                setBusy(coverBtn, false);
                coverFile.value = '';
            });
        });
        if (coverUrl) {
            coverUrl.addEventListener('input', function() {
                updateCoverPreview(coverUrl.value.trim());
            });
        }
    }

    if (!textarea) return;

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
            case 'image-upload':
                if (imagePicker) imagePicker.click();
                break;
            case 'ul': insertLine('- ', '列表项'); break;
            case 'ol': insertLine('1. ', '列表项'); break;
            case 'table': insert('\n', '\n', table); break;
            case 'summary': generateSummary(); break;
        }
    }

    function generateSummary() {
        if (!summaryUrl) return;
        var btn = root.querySelector('[data-md="summary"]');
        setBusy(btn, true, '<i class="fa-solid fa-spinner fa-spin"></i>');
        var data = new URLSearchParams();
        data.set('_csrf', csrf);
        data.set('title', titleInput ? titleInput.value : '');
        data.set('markdown', textarea.value);
        fetch(summaryUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: data.toString()
        }).then(function(res) {
            return res.ok ? res.json() : Promise.reject(new Error('摘要接口不可用'));
        }).then(function(data) {
            if (data.code !== 0) {
                throw new Error(data.msg || '生成摘要失败');
            }
            if (summaryInput) {
                summaryInput.value = data.data.summary || '';
                summaryInput.focus();
            }
        }).catch(function(err) {
            alert(err.message || '生成摘要失败');
        }).finally(function() {
            setBusy(btn, false);
        });
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

    if (imagePicker) {
        imagePicker.addEventListener('change', function() {
            var file = imagePicker.files && imagePicker.files[0];
            if (!file) return;
            var btn = root.querySelector('[data-md="image-upload"]');
            setBusy(btn, true, '<i class="fa-solid fa-spinner fa-spin"></i>');
            uploadImage(file, 'editor').then(function(data) {
                insert('![', '](' + data.url + ')', data.name || '图片描述');
            }).catch(function(err) {
                alert(err.message || '图片上传失败');
            }).finally(function() {
                setBusy(btn, false);
                imagePicker.value = '';
            });
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
