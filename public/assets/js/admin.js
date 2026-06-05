// 后台脚本
(function() {
    'use strict';

    function adminLoadingSpinnerSvg(extraClass) {
        var cls = 'site-loading-spinner admin-loading-spinner' + (extraClass ? ' ' + extraClass : '');
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

    function adminLoadingMarkup(text) {
        return adminLoadingSpinnerSvg() + (text ? '<span>' + text + '</span>' : '');
    }

    window.adminLoadingSpinner = adminLoadingSpinnerSvg;
    window.adminLoadingMarkup = adminLoadingMarkup;

    var nativeFormSubmit = HTMLFormElement.prototype.submit;
    var dialogId = 0;

    function bindToast(toast) {
        if (!toast || toast.dataset.bound === '1') {
            return;
        }
        toast.dataset.bound = '1';
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
    }

    function showToast(message, type) {
        var stack = document.querySelector('.admin-toast-stack');
        if (!stack) {
            stack = document.createElement('div');
            stack.className = 'admin-toast-stack';
            stack.setAttribute('aria-live', 'polite');
            stack.setAttribute('aria-atomic', 'true');
            document.body.appendChild(stack);
        }

        var toast = document.createElement('div');
        var isError = type === 'error';
        toast.className = 'admin-toast site-toast site-toast-' + (isError ? 'error' : 'success') + ' ' + (isError ? 'admin-toast-error' : 'admin-toast-success');
        toast.setAttribute('role', isError ? 'alert' : 'status');
        toast.innerHTML = ''
            + (isError
                ? '<i class="fa-solid fa-triangle-exclamation"></i>'
                : '<i class="fa-solid fa-circle-check" aria-hidden="true"></i>')
            + '<span class="admin-toast-message"></span>'
            + '<button type="button" class="admin-toast-close" aria-label="关闭提示"><i class="fa-solid fa-xmark"></i></button>';
        toast.querySelector('.admin-toast-message').textContent = message || (isError ? '操作失败' : '操作成功');
        stack.appendChild(toast);
        bindToast(toast);
    }

    function dialogTextFromAttribute(value) {
        if (!value) {
            return '';
        }
        var match = String(value).match(/confirm\((['"])(.*?)\1\)/);
        return match ? match[2] : '';
    }

    function inferConfirmTone(message, tone) {
        if (tone) {
            return tone;
        }
        return /删除|移除|清空|Deactivate|危险|不可恢复/.test(message || '') ? 'danger' : 'primary';
    }

    function getConfirmOptions(el) {
        if (!el) {
            return null;
        }
        var message = el.dataset.confirm || el.dataset.confirmClick || dialogTextFromAttribute(el.getAttribute('onsubmit') || el.getAttribute('onclick'));
        if (!message) {
            return null;
        }
        var tone = inferConfirmTone(message, el.dataset.confirmTone || '');
        return {
            title: el.dataset.confirmTitle || (tone === 'danger' ? '确认删除' : '确认操作'),
            message: message,
            tone: tone,
            confirmText: el.dataset.confirmText || (tone === 'danger' ? '确认删除' : '确认'),
            cancelText: el.dataset.cancelText || '取消'
        };
    }

    function closeDialog(dialog, result, resolve) {
        if (!dialog || dialog.dataset.closing === '1') {
            return;
        }
        dialog.dataset.closing = '1';
        dialog.classList.add('is-leaving');
        document.removeEventListener('keydown', dialog._onKeydown, true);
        setTimeout(function() {
            dialog.remove();
            if (!document.querySelector('.admin-dialog-backdrop')) {
                document.body.classList.remove('admin-dialog-open');
            }
            if (dialog._restoreFocus && document.contains(dialog._restoreFocus)) {
                dialog._restoreFocus.focus();
            }
            resolve(result);
        }, 160);
    }

    function adminConfirm(options) {
        var opts = typeof options === 'string' ? { message: options } : (options || {});
        var message = opts.message || '确认执行此操作？';
        var tone = inferConfirmTone(message, opts.tone || '');
        var id = ++dialogId;

        return new Promise(function(resolve) {
            var backdrop = document.createElement('div');
            backdrop.className = 'admin-dialog-backdrop';
            backdrop.innerHTML = ''
                + '<div class="admin-dialog-shell">'
                + '  <section class="admin-dialog" role="dialog" aria-modal="true" aria-labelledby="admin-dialog-title-' + id + '" aria-describedby="admin-dialog-desc-' + id + '">'
                + '    <div class="admin-dialog-body">'
                + '      <div class="admin-dialog-layout">'
                + '        <div class="admin-dialog-icon admin-dialog-icon-' + tone + '"><i class="fa-solid ' + (tone === 'danger' ? 'fa-triangle-exclamation' : 'fa-circle-question') + '"></i></div>'
                + '        <div class="admin-dialog-copy">'
                + '          <h3 id="admin-dialog-title-' + id + '"></h3>'
                + '          <p id="admin-dialog-desc-' + id + '"></p>'
                + '        </div>'
                + '      </div>'
                + '    </div>'
                + '    <div class="admin-dialog-actions">'
                + '      <button type="button" class="btn admin-dialog-confirm admin-dialog-confirm-' + tone + '"></button>'
                + '      <button type="button" class="btn admin-dialog-cancel"></button>'
                + '    </div>'
                + '  </section>'
                + '</div>';

            var title = backdrop.querySelector('h3');
            var desc = backdrop.querySelector('p');
            var confirmBtn = backdrop.querySelector('.admin-dialog-confirm');
            var cancelBtn = backdrop.querySelector('.admin-dialog-cancel');
            title.textContent = opts.title || (tone === 'danger' ? '确认删除' : '确认操作');
            desc.textContent = message;
            confirmBtn.textContent = opts.confirmText || (tone === 'danger' ? '确认删除' : '确认');
            cancelBtn.textContent = opts.cancelText || '取消';

            backdrop._restoreFocus = document.activeElement;
            backdrop._onKeydown = function(e) {
                if (e.key === 'Escape') {
                    e.preventDefault();
                    closeDialog(backdrop, false, resolve);
                }
                if (e.key === 'Tab') {
                    var focusables = backdrop.querySelectorAll('button');
                    var first = focusables[0];
                    var last = focusables[focusables.length - 1];
                    if (e.shiftKey && document.activeElement === first) {
                        e.preventDefault();
                        last.focus();
                    } else if (!e.shiftKey && document.activeElement === last) {
                        e.preventDefault();
                        first.focus();
                    }
                }
            };

            backdrop.addEventListener('click', function(e) {
                if (e.target === backdrop || e.target.classList.contains('admin-dialog-shell')) {
                    closeDialog(backdrop, false, resolve);
                }
            });
            cancelBtn.addEventListener('click', function() {
                closeDialog(backdrop, false, resolve);
            });
            confirmBtn.addEventListener('click', function() {
                closeDialog(backdrop, true, resolve);
            });

            document.body.appendChild(backdrop);
            document.body.classList.add('admin-dialog-open');
            document.addEventListener('keydown', backdrop._onKeydown, true);
            setTimeout(function() {
                confirmBtn.focus();
            }, 0);
        });
    }

    window.adminConfirm = adminConfirm;
    window.adminToast = showToast;
    window.siteToast = showToast;

    document.addEventListener('submit', function(e) {
        var form = e.target;
        if (!form || form.nodeName !== 'FORM') {
            return;
        }
        if (form.dataset.confirmed === '1') {
            delete form.dataset.confirmed;
            return;
        }
        var opts = getConfirmOptions(form);
        if (!opts) {
            return;
        }
        e.preventDefault();
        e.stopImmediatePropagation();
        adminConfirm(opts).then(function(confirmed) {
            if (!confirmed) {
                return;
            }
            isDirty = false;
            suppressDirtyWarning = true;
            form.dataset.confirmed = '1';
            nativeFormSubmit.call(form);
        });
    }, true);

    document.addEventListener('click', function(e) {
        var trigger = e.target.closest('[data-confirm-click], button[onclick*="confirm("], a[onclick*="confirm("]');
        if (!trigger) {
            return;
        }
        var opts = getConfirmOptions(trigger);
        if (!opts) {
            return;
        }
        e.preventDefault();
        e.stopImmediatePropagation();
        adminConfirm(opts).then(function(confirmed) {
            if (!confirmed) {
                return;
            }
            if (trigger.type === 'submit' && trigger.form) {
                isDirty = false;
                suppressDirtyWarning = true;
                trigger.form.dataset.confirmed = '1';
                nativeFormSubmit.call(trigger.form);
            } else if (trigger.href) {
                suppressDirtyWarning = true;
                window.location.href = trigger.href;
            }
        });
    }, true);

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

    // 离开页面前提示：仅监听明确开启的编辑表单。
    var isDirty = false;
    var suppressDirtyWarning = false;

    function dirtyForms() {
        return Array.prototype.slice.call(document.querySelectorAll('form.admin-form[data-dirty-watch]'));
    }

    function dirtyControls(form) {
        return Array.prototype.slice.call(form.querySelectorAll('input, textarea, select')).filter(function(el) {
            return shouldTrackDirty(el);
        });
    }

    function dirtyValue(el) {
        var type = (el.type || '').toLowerCase();
        if (type === 'checkbox' || type === 'radio') {
            return el.checked ? '1:' + (el.value || '') : '0';
        }
        if (type === 'file') {
            return Array.prototype.slice.call(el.files || []).map(function(file) {
                return [file.name, file.size, file.lastModified].join(':');
            }).join('|');
        }
        return el.value;
    }

    function formSnapshot(form) {
        return JSON.stringify(dirtyControls(form).map(function(el) {
            return [
                el.name || el.id || '',
                (el.type || el.tagName || '').toLowerCase(),
                dirtyValue(el)
            ];
        }));
    }

    function markFormClean(form) {
        if (form) {
            form.dataset.dirtySnapshot = formSnapshot(form);
        }
    }

    function hasDirtyForm() {
        return dirtyForms().some(function(form) {
            return formSnapshot(form) !== (form.dataset.dirtySnapshot || '');
        });
    }

    function shouldTrackDirty(el) {
        var form = el.form || el.closest('form');
        if (!form || !form.classList.contains('admin-form')) {
            return false;
        }
        if (!form.hasAttribute('data-dirty-watch')) {
            return false;
        }
        if (form.hasAttribute('data-no-dirty-form') || form.dataset.noDirty === '1') {
            return false;
        }
        if (el.hasAttribute('data-no-dirty') || el.closest('[data-ajax-toggle]')) {
            return false;
        }
        return true;
    }
    document.querySelectorAll('form.admin-form input, form.admin-form textarea, form.admin-form select').forEach(function(el) {
        el.addEventListener('change', function() {
            if (!shouldTrackDirty(el)) {
                return;
            }
            isDirty = hasDirtyForm();
        });
        el.addEventListener('input', function() {
            if (!shouldTrackDirty(el)) {
                return;
            }
            isDirty = hasDirtyForm();
        });
    });
    dirtyForms().forEach(markFormClean);
    window.addEventListener('beforeunload', function(e) {
        if (suppressDirtyWarning) {
            return;
        }
        isDirty = hasDirtyForm();
        if (isDirty) {
            e.preventDefault();
            e.returnValue = '有未保存的修改，确定离开？';
        }
    });
    document.querySelectorAll('form.admin-form').forEach(function(form) {
        form.addEventListener('submit', function() {
            markFormClean(form);
            isDirty = false;
            suppressDirtyWarning = true;
        });
    });

    // AJAX 开关：例如分类「菜单显示」
    document.querySelectorAll('form[data-ajax-toggle]').forEach(function(form) {
        var checkbox = form.querySelector('input[type="checkbox"]');
        if (!checkbox) return;

        var lastChecked = checkbox.checked;

        form.addEventListener('submit', function(e) {
            e.preventDefault();
        });

        checkbox.addEventListener('change', function() {
            var nextChecked = checkbox.checked;
            var data = new FormData(form);
            data.set(checkbox.name, nextChecked ? '1' : '0');

            checkbox.disabled = true;
            form.classList.add('is-saving');

            fetch(form.action, {
                method: (form.method || 'POST').toUpperCase(),
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: data
            }).then(function(res) {
                return res.json().then(function(payload) {
                    if (!res.ok || payload.code !== 0) {
                        throw new Error(payload.msg || '保存失败');
                    }
                    return payload;
                });
            }).then(function(payload) {
                var saved = payload.data && Number(payload.data.show_in_nav) === 1;
                checkbox.checked = saved;
                lastChecked = saved;
                var label = checkbox.closest('label');
                if (label) {
                    label.title = saved ? '点击从菜单栏隐藏' : '点击在菜单栏显示';
                }
                showToast(payload.msg || '保存成功', 'success');
            }).catch(function(err) {
                checkbox.checked = lastChecked;
                showToast(err.message || '保存失败，请稍后再试', 'error');
            }).finally(function() {
                checkbox.disabled = false;
                form.classList.remove('is-saving');
            });
        });
    });

    function setPostFlagButton(button, active) {
        var isActive = !!active;
        var field = button.dataset.field || '';
        button.classList.toggle('is-active', isActive);
        button.dataset.active = isActive ? '1' : '0';

        var labels = {
            is_top: isActive ? '取消置顶' : '置顶',
            is_recommend: isActive ? '取消推荐' : '推荐'
        };
        if (labels[field]) {
            button.title = labels[field];
            button.setAttribute('aria-label', labels[field]);
        }
    }

    function syncPostFlagBadge(row, field, active) {
        if (!row || !field) return;
        var badge = row.querySelector('[data-post-badge="' + field + '"]');
        if (!active) {
            if (badge) badge.remove();
            return;
        }
        if (badge) return;

        var titleCell = row.querySelector('td:nth-child(3)');
        var titleLink = titleCell ? titleCell.querySelector('a') : null;
        if (!titleCell || !titleLink) return;

        badge = document.createElement('span');
        badge.dataset.postBadge = field;
        if (field === 'is_top') {
            badge.className = 'badge badge-top';
            badge.textContent = '顶';
        } else {
            badge.className = 'badge badge-recommend';
            badge.textContent = '荐';
        }
        var beforeNode = titleLink;
        if (field === 'is_top') {
            beforeNode = titleCell.querySelector('[data-post-badge="is_recommend"]') || titleLink;
        }
        titleCell.insertBefore(badge, beforeNode);
    }

    document.querySelectorAll('[data-post-toggle]').forEach(function(button) {
        button.addEventListener('click', function() {
            if (button.disabled) return;
            var field = button.dataset.field || '';
            var action = button.dataset.action || '';
            var csrf = button.dataset.csrf || '';
            if (!field || !action || !csrf) {
                showToast('缺少操作参数', 'error');
                return;
            }

            var data = new FormData();
            data.append('_csrf', csrf);
            data.append('field', field);

            button.disabled = true;
            button.classList.add('is-saving');

            fetch(action, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: data
            }).then(function(res) {
                return res.json().then(function(payload) {
                    if (!res.ok || payload.code !== 0) {
                        throw new Error(payload.msg || payload.error || '保存失败');
                    }
                    return payload;
                });
            }).then(function(payload) {
                var active = payload.data && Number(payload.data.value) === 1;
                setPostFlagButton(button, active);
                syncPostFlagBadge(button.closest('tr'), field, active);
                showToast(payload.msg || '保存成功', 'success');
            }).catch(function(err) {
                showToast(err.message || '保存失败，请稍后再试', 'error');
            }).finally(function() {
                button.disabled = false;
                button.classList.remove('is-saving');
            });
        });
    });

    // 后台 flash toast
    document.querySelectorAll('.admin-toast').forEach(function(toast) {
        bindToast(toast);
    });
    document.querySelectorAll('[data-toast-message]').forEach(function(seed) {
        if (seed.dataset.bound === '1') {
            return;
        }
        seed.dataset.bound = '1';
        showToast(seed.dataset.toastMessage || seed.textContent || '', seed.dataset.toastType || 'success');
        seed.remove();
    });
})();

// LiteNote Markdown editor
(function() {
    'use strict';

    function setBusy(el, busy, text) {
        if (!el) return;
        if (busy) {
            if (!el.dataset.originalText) {
                el.dataset.originalText = el.innerHTML;
            }
            el.disabled = true;
            if (text) el.innerHTML = text;
        } else {
            el.disabled = false;
            if (el.dataset.originalText) {
                el.innerHTML = el.dataset.originalText;
                delete el.dataset.originalText;
            }
        }
    }

    function notify(message, type) {
        if (window.adminToast) {
            window.adminToast(message, type || 'error');
        }
    }

    function uploadImage(file, purpose, uploadUrl, csrf) {
        if (!uploadUrl || !file) {
            return Promise.reject(new Error('上传接口不可用'));
        }
        var data = new FormData();
        data.append('_csrf', csrf || '');
        data.append('purpose', purpose || 'editor');
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

    function findBySelector(selector) {
        if (!selector) return null;
        try {
            return document.querySelector(selector);
        } catch (e) {
            return document.getElementById(selector.replace(/^#/, ''));
        }
    }

    function editorConfigRoot() {
        return document.querySelector('.markdown-editor[data-upload-url]') || document.querySelector('.markdown-editor');
    }

    function normalizeLrcText(value) {
        value = String(value || '').replace(/\r\n?/g, '\n').trim();
        if (!/\[\d{1,2}:\d{2}(?:[.:]\d{1,3})?\]/.test(value)) {
            return value;
        }
        return value.split('\n').map(function(line) {
            line = line.trim();
            if (!line || /^\[(ti|ar|al|by|offset|length|re):[^\]]*\]$/i.test(line)) {
                return '';
            }
            return line.replace(/(?:\[\d{1,2}:\d{2}(?:[.:]\d{1,3})?\])+/g, '').trim();
        }).filter(Boolean).join('\n');
    }

    function initLrcInputs() {
        document.querySelectorAll('[data-lrc-input]').forEach(function(input) {
            var normalize = function() {
                var next = normalizeLrcText(input.value);
                if (next !== input.value) {
                    input.value = next;
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                }
            };
            input.addEventListener('paste', function() {
                setTimeout(normalize, 0);
            });
            input.addEventListener('blur', normalize);
            if (input.form) {
                input.form.addEventListener('submit', normalize);
            }
        });
    }

    function initCoverUpload() {
        var coverUrl = document.getElementById('cover-url');
        var coverBtn = document.getElementById('cover-upload-btn');
        var coverFile = document.getElementById('cover-file');
        var coverPreview = document.getElementById('cover-preview');
        var root = editorConfigRoot();
        var uploadUrl = root ? (root.getAttribute('data-upload-url') || '') : '';
        var csrf = root ? (root.getAttribute('data-csrf') || '') : '';

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

        if (!coverBtn || !coverFile) return;
        coverBtn.addEventListener('click', function() {
            coverFile.click();
        });
        coverFile.addEventListener('change', function() {
            var file = coverFile.files && coverFile.files[0];
            if (!file) return;
            setBusy(coverBtn, true, adminLoadingMarkup('上传中'));
            uploadImage(file, 'cover', uploadUrl, csrf).then(function(data) {
                if (coverUrl) coverUrl.value = data.url || '';
                updateCoverPreview(data.url || '');
            }).catch(function(err) {
                notify(err.message || '特色图上传失败', 'error');
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

    function initEditor(root) {
        var textarea = root.querySelector('[data-editor-textarea]') || root.querySelector('textarea');
        if (!textarea) return;

        var preview = root.querySelector('[data-editor-preview]');
        var words = root.querySelector('[data-editor-words]');
        var lines = root.querySelector('[data-editor-lines]');
        var filePicker = root.querySelector('[data-md-file-picker]');
        var imagePicker = root.querySelector('[data-md-image-picker]');
        var titleInput = findBySelector(root.getAttribute('data-title-input')) || document.getElementById('post-title');
        var summaryInput = findBySelector(root.getAttribute('data-summary-input')) || document.getElementById('post-summary');
        var previewUrl = root.getAttribute('data-preview-url') || '';
        var uploadUrl = root.getAttribute('data-upload-url') || '';
        var uploadPurpose = root.getAttribute('data-upload-purpose') || 'editor';
        var summaryUrl = root.getAttribute('data-summary-url') || '';
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
            setBusy(btn, true, adminLoadingSpinnerSvg());
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
                notify(err.message || '生成摘要失败', 'error');
            }).finally(function() {
                setBusy(btn, false);
            });
        }

        function updateStats() {
            var value = textarea.value;
            var plain = value.replace(/[#>*_`\-[\]()!|]/g, '').trim();
            var count = plain ? plain.length : 0;
            var lineCount = value ? value.split('\n').length : 0;
            if (words) words.textContent = count + ' 字';
            if (lines) lines.textContent = lineCount + ' 行';
        }

        function updatePreview() {
            if (!previewUrl || !preview) return;
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
                setBusy(btn, true, adminLoadingSpinnerSvg());
                uploadImage(file, uploadPurpose, uploadUrl, csrf).then(function(data) {
                    insert('![', '](' + data.url + ')', data.name || '图片描述');
                }).catch(function(err) {
                    notify(err.message || '图片上传失败', 'error');
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
    }

    initCoverUpload();
    initLrcInputs();
    document.querySelectorAll('.markdown-editor').forEach(initEditor);
})();

function passkeyCsrfToken() {
    var field = document.querySelector('input[name="_csrf"]');
    var meta = document.querySelector('meta[name="csrf-token"]');
    return field ? field.value : (meta ? meta.getAttribute('content') : '');
}

function passkeyBase64UrlToBytes(value) {
    value = String(value || '').replace(/-/g, '+').replace(/_/g, '/');
    while (value.length % 4) value += '=';
    return Uint8Array.from(atob(value), function(c) { return c.charCodeAt(0); });
}

function passkeyBytesToBase64Url(buffer) {
    var bytes = new Uint8Array(buffer);
    var chunk = '';
    for (var i = 0; i < bytes.length; i += 1) {
        chunk += String.fromCharCode(bytes[i]);
    }
    return btoa(chunk).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/g, '');
}

async function passkeyJson(res) {
    var type = res.headers.get('content-type') || '';
    if (type.indexOf('application/json') === -1) {
        await res.text();
        throw new Error('Passkey 接口返回了非 JSON 响应,请检查登录路由配置');
    }
    var data = await res.json();
    if (!res.ok || data.success === false) {
        throw new Error(data.message || data.error || 'Passkey 请求失败');
    }
    return data;
}

function passkeySupported() {
    if (!window.PublicKeyCredential || !navigator.credentials) {
        throw new Error('当前浏览器不支持 Passkey');
    }
}

// 注册 Passkey
async function registerPasskey(deviceName = '我的设备') {
    passkeySupported();
    const res = await fetch('/admin/passkey/register-options', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin'
    });
    const options = await passkeyJson(res);

    const credential = await navigator.credentials.create({
        publicKey: {
            challenge: passkeyBase64UrlToBytes(options.challenge),
            rp: options.rp,
            user: {
                id: passkeyBase64UrlToBytes(options.user.id),
                name: options.user.name,
                displayName: options.user.displayName
            },
            pubKeyCredParams: options.pubKeyCredParams,
            timeout: options.timeout,
            attestation: options.attestation,
            authenticatorSelection: options.authenticatorSelection
        }
    });

    const data = {
        id: credential.id,
        rawId: passkeyBytesToBase64Url(credential.rawId),
        response: {
            clientDataJSON: passkeyBytesToBase64Url(credential.response.clientDataJSON),
            attestationObject: passkeyBytesToBase64Url(credential.response.attestationObject)
        }
    };

    const saveRes = await fetch('/admin/passkey/register', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': passkeyCsrfToken(),
            'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'same-origin',
        body: JSON.stringify({ credential: JSON.stringify(data), device_name: deviceName })
    });

    return await passkeyJson(saveRes);
}

// 使用 Passkey 登录
async function loginWithPasskey() {
    passkeySupported();
    const res = await fetch('/admin/passkey/login-options', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin'
    });
    const options = await passkeyJson(res);
    const allowCredentials = (options.allowCredentials || []).map(function(item) {
        return {
            type: item.type || 'public-key',
            id: passkeyBase64UrlToBytes(item.id)
        };
    });

    const assertion = await navigator.credentials.get({
        publicKey: {
            challenge: passkeyBase64UrlToBytes(options.challenge),
            timeout: options.timeout,
            rpId: options.rpId,
            allowCredentials: allowCredentials,
            userVerification: options.userVerification || 'preferred'
        }
    });

    const data = {
        id: assertion.id,
        rawId: passkeyBytesToBase64Url(assertion.rawId),
        response: {
            clientDataJSON: passkeyBytesToBase64Url(assertion.response.clientDataJSON),
            authenticatorData: passkeyBytesToBase64Url(assertion.response.authenticatorData),
            signature: passkeyBytesToBase64Url(assertion.response.signature),
            userHandle: assertion.response.userHandle ? passkeyBytesToBase64Url(assertion.response.userHandle) : ''
        }
    };

    const loginRes = await fetch('/admin/passkey/login', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': passkeyCsrfToken(),
            'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'same-origin',
        body: JSON.stringify({ credential: JSON.stringify(data) })
    });

    return await passkeyJson(loginRes);
}
