// \u{540E}\u{53F0}\u{811A}\u{672C}
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
        toast._adminToastTimer = timer;
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
            + '<span class="admin-toast-message"></span>';
        toast.querySelector('.admin-toast-message').textContent = message || (isError ? '\u{64CD}\u{4F5C}\u{5931}\u{8D25}' : '\u{64CD}\u{4F5C}\u{6210}\u{529F}');
        stack.appendChild(toast);
        bindToast(toast);
    }

    function uploadToast(filename) {
        var stack = document.querySelector('.admin-toast-stack');
        if (!stack) {
            stack = document.createElement('div');
            stack.className = 'admin-toast-stack';
            stack.setAttribute('aria-live', 'polite');
            stack.setAttribute('aria-atomic', 'true');
            document.body.appendChild(stack);
        }

        var toast = document.createElement('div');
        toast.className = 'admin-toast admin-upload-toast';
        toast.setAttribute('role', 'status');
        toast.innerHTML = ''
            + '<span class="admin-upload-toast-icon">' + adminLoadingSpinnerSvg() + '</span>'
            + '<div class="admin-upload-toast-body">'
            + '  <div class="admin-upload-toast-head"><span class="admin-upload-toast-title"></span><strong class="admin-upload-toast-percent">0%</strong></div>'
            + '  <div class="admin-upload-toast-bar"><span></span></div>'
            + '</div>';
        toast.querySelector('.admin-upload-toast-title').textContent = filename ? '\u{4E0A}\u{4F20} ' + filename : '\u{6B63}\u{5728}\u{4E0A}\u{4F20}';
        stack.appendChild(toast);

        var bar = toast.querySelector('.admin-upload-toast-bar span');
        var percent = toast.querySelector('.admin-upload-toast-percent');
        var icon = toast.querySelector('.admin-upload-toast-icon');

        function close(delay) {
            setTimeout(function() {
                if (toast.classList.contains('is-leaving')) return;
                toast.classList.add('is-leaving');
                setTimeout(function() { toast.remove(); }, 180);
            }, delay || 0);
        }

        return {
            progress: function(value) {
                value = Math.max(0, Math.min(100, Math.round(value || 0)));
                if (bar) bar.style.width = value + '%';
                if (percent) percent.textContent = value + '%';
            },
            success: function(message) {
                toast.classList.add('admin-upload-toast-success');
                if (icon) icon.innerHTML = '<i class="fa-solid fa-circle-check"></i>';
                if (bar) bar.style.width = '100%';
                if (percent) percent.textContent = '100%';
                toast.querySelector('.admin-upload-toast-title').textContent = message || '\u{4E0A}\u{4F20}\u{5B8C}\u{6210}';
                close(1400);
            },
            error: function(message) {
                toast.classList.add('admin-upload-toast-error');
                if (icon) icon.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i>';
                toast.querySelector('.admin-upload-toast-title').textContent = message || '\u{4E0A}\u{4F20}\u{5931}\u{8D25}';
                close(3200);
            }
        };
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
        return /\u{5220}\u{9664}|\u{79FB}\u{9664}|\u{6E05}\u{7A7A}|Deactivate|\u{5371}\u{9669}|\u{4E0D}\u{53EF}\u{6062}\u{590D}/.test(message || '') ? 'danger' : 'primary';
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
            title: el.dataset.confirmTitle || (tone === 'danger' ? '\u{786E}\u{8BA4}\u{5220}\u{9664}' : '\u{786E}\u{8BA4}\u{64CD}\u{4F5C}'),
            message: message,
            tone: tone,
            confirmText: el.dataset.confirmText || (tone === 'danger' ? '\u{786E}\u{8BA4}\u{5220}\u{9664}' : '\u{786E}\u{8BA4}'),
            cancelText: el.dataset.cancelText || '\u{53D6}\u{6D88}'
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
        var message = opts.message || '\u{786E}\u{8BA4}\u{6267}\u{884C}\u{6B64}\u{64CD}\u{4F5C}\u{FF1F}';
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
            title.textContent = opts.title || (tone === 'danger' ? '\u{786E}\u{8BA4}\u{5220}\u{9664}' : '\u{786E}\u{8BA4}\u{64CD}\u{4F5C}');
            desc.textContent = message;
            confirmBtn.textContent = opts.confirmText || (tone === 'danger' ? '\u{786E}\u{8BA4}\u{5220}\u{9664}' : '\u{786E}\u{8BA4}');
            cancelBtn.textContent = opts.cancelText || '\u{53D6}\u{6D88}';

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
    window.adminUploadToast = uploadToast;

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

    // \u{5168}\u{9009}
    var checkAll = document.getElementById('check-all');
    if (checkAll) {
        var bulkForm = checkAll.closest('form');
        var bulkBar = bulkForm ? bulkForm.querySelector('[data-bulk-bar]') : document.querySelector('[data-bulk-bar]');
        var bulkItems = bulkForm ? bulkForm.querySelectorAll('input[name="ids[]"]') : document.querySelectorAll('input[name="ids[]"]');
        function syncBulkBar() {
            var checkedCount = Array.prototype.filter.call(bulkItems, function(cb) {
                return cb.checked;
            }).length;
            var total = bulkItems.length;
            var allChecked = total > 0 && checkedCount === total;
            checkAll.checked = allChecked;
            checkAll.indeterminate = checkedCount > 0 && checkedCount < total;
            if (bulkBar) {
                bulkBar.hidden = !(checkedCount > 1 || allChecked);
            }
        }
        checkAll.addEventListener('change', function() {
            bulkItems.forEach(function(cb) {
                cb.checked = checkAll.checked;
            });
            syncBulkBar();
        });
        bulkItems.forEach(function(cb) {
            cb.addEventListener('change', syncBulkBar);
        });
        syncBulkBar();
    }

    // \u{8868}\u{5355}\u{63D0}\u{4EA4}\u{7981}\u{7528}\u{6309}\u{94AE}
    document.querySelectorAll('form').forEach(function(form) {
        form.addEventListener('submit', function() {
            var btn = form.querySelector('button[type=submit]');
            if (btn) {
                btn.disabled = true;
                btn.textContent = '\u{5904}\u{7406}\u{4E2D}...';
                setTimeout(function() { btn.disabled = false; }, 5000);
            }
        });
    });

    // \u{79BB}\u{5F00}\u{9875}\u{9762}\u{524D}\u{63D0}\u{793A}\u{FF1A}\u{4EC5}\u{76D1}\u{542C}\u{660E}\u{786E}\u{5F00}\u{542F}\u{7684}\u{7F16}\u{8F91}\u{8868}\u{5355}\u{3002}
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
            e.returnValue = '\u{6709}\u{672A}\u{4FDD}\u{5B58}\u{7684}\u{4FEE}\u{6539}\u{FF0C}\u{786E}\u{5B9A}\u{79BB}\u{5F00}\u{FF1F}';
        }
    });
    document.querySelectorAll('form.admin-form').forEach(function(form) {
        form.addEventListener('submit', function() {
            markFormClean(form);
            isDirty = false;
            suppressDirtyWarning = true;
        });
    });

    // AJAX \u{5F00}\u{5173}\u{FF1A}\u{4F8B}\u{5982}\u{5206}\u{7C7B}\u{300C}\u{83DC}\u{5355}\u{663E}\u{793A}\u{300D}
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
                        throw new Error(payload.msg || '\u{4FDD}\u{5B58}\u{5931}\u{8D25}');
                    }
                    return payload;
                });
            }).then(function(payload) {
                var saved = payload.data && Number(payload.data.show_in_nav) === 1;
                checkbox.checked = saved;
                lastChecked = saved;
                var label = checkbox.closest('label');
                if (label) {
                    label.title = saved ? '\u{70B9}\u{51FB}\u{4ECE}\u{83DC}\u{5355}\u{680F}\u{9690}\u{85CF}' : '\u{70B9}\u{51FB}\u{5728}\u{83DC}\u{5355}\u{680F}\u{663E}\u{793A}';
                }
                showToast(payload.msg || '\u{4FDD}\u{5B58}\u{6210}\u{529F}', 'success');
            }).catch(function(err) {
                checkbox.checked = lastChecked;
                showToast(err.message || '\u{4FDD}\u{5B58}\u{5931}\u{8D25}\u{FF0C}\u{8BF7}\u{7A0D}\u{540E}\u{518D}\u{8BD5}', 'error');
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
            is_top: isActive ? '\u{53D6}\u{6D88}\u{7F6E}\u{9876}' : '\u{7F6E}\u{9876}',
            is_recommend: isActive ? '\u{53D6}\u{6D88}\u{63A8}\u{8350}' : '\u{63A8}\u{8350}'
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
            badge.textContent = '\u{9876}';
        } else {
            badge.className = 'badge badge-recommend';
            badge.textContent = '\u{8350}';
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
                showToast('\u{7F3A}\u{5C11}\u{64CD}\u{4F5C}\u{53C2}\u{6570}', 'error');
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
                        throw new Error(payload.msg || payload.error || '\u{4FDD}\u{5B58}\u{5931}\u{8D25}');
                    }
                    return payload;
                });
            }).then(function(payload) {
                var active = payload.data && Number(payload.data.value) === 1;
                setPostFlagButton(button, active);
                syncPostFlagBadge(button.closest('tr'), field, active);
                showToast(payload.msg || '\u{4FDD}\u{5B58}\u{6210}\u{529F}', 'success');
            }).catch(function(err) {
                showToast(err.message || '\u{4FDD}\u{5B58}\u{5931}\u{8D25}\u{FF0C}\u{8BF7}\u{7A0D}\u{540E}\u{518D}\u{8BD5}', 'error');
            }).finally(function() {
                button.disabled = false;
                button.classList.remove('is-saving');
            });
        });
    });

    // \u{540E}\u{53F0} flash toast
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

    function loadingSpinnerMarkup(extraClass) {
        if (typeof window.adminLoadingSpinner === 'function') {
            return window.adminLoadingSpinner(extraClass || '');
        }
        return '<i class="fa-solid fa-spinner fa-spin"></i>';
    }

    function uploadProgressToast(filename) {
        if (typeof window.adminUploadToast === 'function') {
            return window.adminUploadToast(filename);
        }
        return {
            progress: function() {},
            success: function(message) { notify(message || '\u{4E0A}\u{4F20}\u{5B8C}\u{6210}', 'success'); },
            error: function(message) { notify(message || '\u{4E0A}\u{4F20}\u{5931}\u{8D25}', 'error'); }
        };
    }

    function uploadImage(file, purpose, uploadUrl, csrf) {
        if (!uploadUrl || !file) {
            return Promise.reject(new Error('\u{4E0A}\u{4F20}\u{63A5}\u{53E3}\u{4E0D}\u{53EF}\u{7528}'));
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
            return res.ok ? res.json() : Promise.reject(new Error('\u{4E0A}\u{4F20}\u{5931}\u{8D25}'));
        }).then(function(data) {
            if (data.code !== 0) {
                throw new Error(data.msg || '\u{4E0A}\u{4F20}\u{5931}\u{8D25}');
            }
            return data.data;
        });
    }

    function uploadAttachment(file, uploadUrl, csrf, onProgress) {
        if (!uploadUrl || !file) {
            return Promise.reject(new Error('\u{4E0A}\u{4F20}\u{63A5}\u{53E3}\u{4E0D}\u{53EF}\u{7528}'));
        }
        return new Promise(function(resolve, reject) {
            var xhr = new XMLHttpRequest();
            var data = new FormData();
            data.append('_csrf', csrf || '');
            data.append('file', file);

            xhr.open('POST', uploadUrl, true);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.upload.addEventListener('progress', function(event) {
                if (!event.lengthComputable || !onProgress) return;
                onProgress((event.loaded / event.total) * 100);
            });
            xhr.onload = function() {
                var payload = null;
                try {
                    payload = JSON.parse(xhr.responseText || '{}');
                } catch (e) {
                    reject(new Error('\u{4E0A}\u{4F20}\u{54CD}\u{5E94}\u{89E3}\u{6790}\u{5931}\u{8D25}'));
                    return;
                }
                if (xhr.status < 200 || xhr.status >= 300 || !payload || payload.code !== 0) {
                    reject(new Error((payload && payload.msg) || '\u{4E0A}\u{4F20}\u{5931}\u{8D25}'));
                    return;
                }
                resolve(payload.data || {});
            };
            xhr.onerror = function() {
                reject(new Error('\u{7F51}\u{7EDC}\u{9519}\u{8BEF}\u{FF0C}\u{4E0A}\u{4F20}\u{5931}\u{8D25}'));
            };
            xhr.send(data);
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
        return String(value || '').replace(/\r\n?/g, '\n').trim();
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

    function initMetingSearch() {
        var panel = document.querySelector('[data-meting-search]');
        if (!panel || panel.dataset.bound === '1') return;
        panel.dataset.bound = '1';

        var form = document.querySelector('.music-editor-form');
        var provider = panel.querySelector('[data-meting-provider]');
        var keyword = panel.querySelector('[data-meting-keyword]');
        var submit = panel.querySelector('[data-meting-submit]');
        var results = panel.querySelector('[data-meting-results]');
        var status = panel.querySelector('[data-meting-status]');
        var searchUrl = panel.dataset.searchUrl || '/admin/music/meting/search';
        var songUrl = panel.dataset.songUrl || '/admin/music/meting/song';
        var requestTimeout = 15000;
        if (!form || !provider || !keyword || !submit || !results || !status) return;

        function fetchJsonWithTimeout(url) {
            var controller = window.AbortController ? new AbortController() : null;
            var timer = controller ? setTimeout(function() { controller.abort(); }, requestTimeout) : null;
            return fetch(url, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                signal: controller ? controller.signal : undefined
            }).finally(function() {
                if (timer) clearTimeout(timer);
            });
        }

        function setStatus(text, tone) {
            status.textContent = text || '';
            status.dataset.tone = tone || '';
        }

        function setField(name, value) {
            var field = form.querySelector('[name="' + name + '"]');
            if (!field) return;
            field.value = value || '';
            field.dispatchEvent(new Event('input', { bubbles: true }));
            field.dispatchEvent(new Event('change', { bubbles: true }));
        }

        function itemLabel(item) {
            var parts = [];
            if (item.artist) parts.push(item.artist);
            if (item.album) parts.push(item.album);
            return parts.join(' · ');
        }

        function renderItems(items) {
            results.innerHTML = '';
            if (!items.length) {
                results.innerHTML = '<p class="empty">\u{6CA1}\u{6709}\u{641C}\u{5230}\u{97F3}\u{4E50}</p>';
                return;
            }
            items.forEach(function(item) {
                var row = document.createElement('button');
                row.type = 'button';
                row.className = 'meting-result-item';
                row.dataset.id = item.id || '';
                row.dataset.provider = item.provider || provider.value || 'netease';
                row.dataset.title = item.title || item.name || '';
                row.dataset.artist = item.artist || '';
                row.dataset.album = item.album || '';
                row.dataset.duration = item.duration || '';
                row.innerHTML = ''
                    + '<span class="meting-result-main">'
                    + '  <strong></strong>'
                    + '  <em></em>'
                    + '</span>'
                    + '<span class="meting-result-side">'
                    + '  <em class="meting-result-duration"></em>'
                    + '  <span class="meting-result-action"><i class="fa-solid fa-arrow-right"></i></span>'
                    + '</span>';
                row.querySelector('strong').textContent = item.title || item.name || '\u{672A}\u{547D}\u{540D}\u{6B4C}\u{66F2}';
                row.querySelector('em').textContent = itemLabel(item) || row.dataset.provider;
                row.querySelector('.meting-result-duration').textContent = item.duration || '';
                row.addEventListener('click', function() {
                    fillSong(row.dataset.provider, row.dataset.id, row);
                });
                results.appendChild(row);
            });
        }

        function search() {
            var q = keyword.value.trim();
            if (!q) {
                setStatus('\u{8BF7}\u{8F93}\u{5165}\u{641C}\u{7D22}\u{5173}\u{952E}\u{8BCD}', 'error');
                keyword.focus();
                return;
            }
            var url = searchUrl + '?provider=' + encodeURIComponent(provider.value || 'netease') + '&q=' + encodeURIComponent(q) + '&limit=10';
            setBusy(submit, true, loadingSpinnerMarkup('admin-loading-spinner-light'));
            setStatus('\u{6B63}\u{5728}\u{641C}\u{7D22}...', '');
            fetchJsonWithTimeout(url)
                .then(function(res) { return res.json().then(function(data) {
                    if (!res.ok || data.ok === false) {
                        throw new Error((data.error && data.error.message) || '\u{641C}\u{7D22}\u{5931}\u{8D25}');
                    }
                    return data.data || {};
                }); })
                .then(function(data) {
                    var items = Array.isArray(data.items) ? data.items : [];
                    renderItems(items);
                    setStatus(items.length ? '\u{9009}\u{62E9}\u{4E00}\u{9996}\u{6B4C}\u{586B}\u{5165}\u{8868}\u{5355}' : '\u{6CA1}\u{6709}\u{641C}\u{5230}\u{7ED3}\u{679C}', items.length ? 'success' : '');
                })
                .catch(function(err) {
                    results.innerHTML = '';
                    var message = err.name === 'AbortError' ? '\u{641C}\u{7D22}\u{8D85}\u{65F6}\u{FF0C}\u{8BF7}\u{7A0D}\u{540E}\u{91CD}\u{8BD5}' : (err.message || '\u{641C}\u{7D22}\u{5931}\u{8D25}');
                    setStatus(message, 'error');
                    notify(message, 'error');
                })
                .finally(function() {
                    setBusy(submit, false);
                });
        }

        function fillSong(songProvider, id, row) {
            if (!id) {
                setStatus('\u{6B4C}\u{66F2} ID \u{65E0}\u{6548}', 'error');
                return;
            }
            if (row) {
                setField('title', row.dataset.title || '');
                setField('artist', row.dataset.artist || '');
                setField('album', row.dataset.album || '');
                if (row.dataset.duration) setField('duration', row.dataset.duration);
                setField('source', 'meting');
                setField('source_provider', row.dataset.provider || songProvider || provider.value || 'netease');
                setField('source_id', id);
            }
            var url = songUrl + '?provider=' + encodeURIComponent(songProvider || provider.value || 'netease') + '&id=' + encodeURIComponent(id);
            if (row) row.classList.add('is-loading');
            setStatus('\u{6B63}\u{5728}\u{83B7}\u{53D6}\u{97F3}\u{9891}\u{3001}\u{5C01}\u{9762}\u{548C}\u{6B4C}\u{8BCD}\u{94FE}\u{63A5}...', '');
            fetchJsonWithTimeout(url)
                .then(function(res) { return res.json().then(function(data) {
                    if (!res.ok || data.ok === false) {
                        throw new Error((data.error && data.error.message) || '\u{83B7}\u{53D6}\u{6B4C}\u{66F2}\u{5931}\u{8D25}');
                    }
                    return data.data || {};
                }); })
                .then(function(song) {
                    if (song.title) setField('title', song.title);
                    if (song.artist) setField('artist', song.artist);
                    if (song.album) setField('album', song.album);
                    setField('audio_url', song.audio_url || song.raw_url || '');
                    setField('cover_url', song.cover_url || song.raw_cover || '');
                    setField('lyrics_url', song.lyrics_url || song.raw_lyric || '');
                    setField('source', song.source || 'meting');
                    setField('source_provider', song.provider || songProvider || provider.value || 'netease');
                    setField('source_id', song.id || id);
                    if (song.lyrics_url || song.raw_lyric) {
                        setField('lyrics', '');
                    }
                    if (song.duration) setField('duration', song.duration);
                    setStatus('\u{5DF2}\u{586B}\u{5165}\u{8868}\u{5355}\u{FF0C}\u{68C0}\u{67E5}\u{540E}\u{4FDD}\u{5B58}', 'success');
                    notify('\u{97F3}\u{4E50}\u{4FE1}\u{606F}\u{5DF2}\u{586B}\u{5165}', 'success');
                })
                .catch(function(err) {
                    var message = err.name === 'AbortError' ? '\u{83B7}\u{53D6}\u{8D85}\u{65F6}\u{FF0C}\u{8BF7}\u{7A0D}\u{540E}\u{91CD}\u{8BD5}' : (err.message || '\u{83B7}\u{53D6}\u{6B4C}\u{66F2}\u{5931}\u{8D25}');
                    setStatus(message, 'error');
                    notify(message, 'error');
                })
                .finally(function() {
                    if (row) row.classList.remove('is-loading');
                });
        }

        submit.addEventListener('click', search);
        keyword.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                search();
            }
        });
    }

    function initUploadFields() {
        document.querySelectorAll('[data-upload-field]').forEach(function(root) {
            if (root.dataset.uploadBound === '1') return;
            root.dataset.uploadBound = '1';

            var textInput = root.querySelector('input[type="text"], input[type="url"]');
            var fileInput = root.querySelector('[data-upload-input]');
            var trigger = root.querySelector('[data-upload-trigger]');
            var uploadUrl = root.dataset.uploadUrl || '/admin/attachments/upload';
            var csrf = root.dataset.csrf || (root.closest('form') ? (root.closest('form').querySelector('input[name="_csrf"]') || {}).value : '');
            var accept = root.dataset.uploadAccept || '';
            if (fileInput && accept) {
                fileInput.setAttribute('accept', accept);
            }
            if (!textInput || !fileInput || !trigger) return;

            trigger.addEventListener('click', function() {
                fileInput.click();
            });

            fileInput.addEventListener('change', function() {
                var file = fileInput.files && fileInput.files[0];
                if (!file) return;
                var toast = uploadProgressToast(file.name || '');
                setBusy(trigger, true, loadingSpinnerMarkup());
                root.classList.add('is-uploading');
                uploadAttachment(file, uploadUrl, csrf, function(progress) {
                    toast.progress(progress);
                }).then(function(data) {
                    var url = data.url || data.fileurl || '';
                    if (!url) {
                        throw new Error('\u{4E0A}\u{4F20}\u{5B8C}\u{6210}\u{FF0C}\u{4F46}\u{672A}\u{8FD4}\u{56DE}\u{6587}\u{4EF6}\u{5730}\u{5740}');
                    }
                    textInput.value = url;
                    textInput.dispatchEvent(new Event('input', { bubbles: true }));
                    textInput.dispatchEvent(new Event('change', { bubbles: true }));
                    toast.success('\u{4E0A}\u{4F20}\u{5B8C}\u{6210}');
                }).catch(function(err) {
                    toast.error(err.message || '\u{4E0A}\u{4F20}\u{5931}\u{8D25}');
                }).finally(function() {
                    setBusy(trigger, false);
                    root.classList.remove('is-uploading');
                    fileInput.value = '';
                });
            });
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
            setBusy(coverBtn, true, loadingSpinnerMarkup());
            uploadImage(file, 'cover', uploadUrl, csrf).then(function(data) {
                if (coverUrl) coverUrl.value = data.url || '';
                updateCoverPreview(data.url || '');
            }).catch(function(err) {
                notify(err.message || '\u{7279}\u{8272}\u{56FE}\u{4E0A}\u{4F20}\u{5931}\u{8D25}', 'error');
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
            var table = '| \u{5217}\u{4E00} | \u{5217}\u{4E8C} |\n| --- | --- |\n| \u{5185}\u{5BB9} | \u{5185}\u{5BB9} |';
            switch (type) {
                case 'heading': insertLine('## ', '\u{5C0F}\u{6807}\u{9898}'); break;
                case 'bold': insert('**', '**', '\u{52A0}\u{7C97}\u{6587}\u{5B57}'); break;
                case 'italic': insert('*', '*', '\u{659C}\u{4F53}\u{6587}\u{5B57}'); break;
                case 'quote': insertLine('> ', '\u{5F15}\u{7528}\u{5185}\u{5BB9}'); break;
                case 'code': insert('```php\n', '\n```', 'echo "Hello LiteNote";'); break;
                case 'link': insert('[', '](https://example.com)', '\u{94FE}\u{63A5}\u{6587}\u{5B57}'); break;
                case 'image-upload':
                    if (imagePicker) imagePicker.click();
                    break;
                case 'ul': insertLine('- ', '\u{5217}\u{8868}\u{9879}'); break;
                case 'ol': insertLine('1. ', '\u{5217}\u{8868}\u{9879}'); break;
                case 'table': insert('\n', '\n', table); break;
                case 'summary': generateSummary(); break;
            }
        }

        function generateSummary() {
            if (!summaryUrl) return;
            var btn = root.querySelector('[data-md="summary"]');
            setBusy(btn, true, loadingSpinnerMarkup());
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
                return res.ok ? res.json() : Promise.reject(new Error('\u{6458}\u{8981}\u{63A5}\u{53E3}\u{4E0D}\u{53EF}\u{7528}'));
            }).then(function(data) {
                if (data.code !== 0) {
                    throw new Error(data.msg || '\u{751F}\u{6210}\u{6458}\u{8981}\u{5931}\u{8D25}');
                }
                if (summaryInput) {
                    summaryInput.value = data.data.summary || '';
                    summaryInput.focus();
                }
            }).catch(function(err) {
                notify(err.message || '\u{751F}\u{6210}\u{6458}\u{8981}\u{5931}\u{8D25}', 'error');
            }).finally(function() {
                setBusy(btn, false);
            });
        }

        function updateStats() {
            var value = textarea.value;
            var plain = value.replace(/[#>*_`\-[\]()!|]/g, '').trim();
            var count = plain ? plain.length : 0;
            var lineCount = value ? value.split('\n').length : 0;
            if (words) words.textContent = count + ' \u{5B57}';
            if (lines) lines.textContent = lineCount + ' \u{884C}';
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
                preview.innerHTML = data.html || '<div class="empty">\u{9884}\u{89C8}\u{4F1A}\u{663E}\u{793A}\u{5728}\u{8FD9}\u{91CC}</div>';
            }).catch(function() {
                preview.innerHTML = '<div class="empty">\u{9884}\u{89C8}\u{6682}\u{65F6}\u{4E0D}\u{53EF}\u{7528}</div>';
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
                setBusy(btn, true, loadingSpinnerMarkup());
                uploadImage(file, uploadPurpose, uploadUrl, csrf).then(function(data) {
                    insert('![', '](' + data.url + ')', data.name || '\u{56FE}\u{7247}\u{63CF}\u{8FF0}');
                }).catch(function(err) {
                    notify(err.message || '\u{56FE}\u{7247}\u{4E0A}\u{4F20}\u{5931}\u{8D25}', 'error');
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
    initUploadFields();
    initLrcInputs();
    initMetingSearch();
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
        throw new Error('Passkey \u{63A5}\u{53E3}\u{8FD4}\u{56DE}\u{4E86}\u{975E} JSON \u{54CD}\u{5E94},\u{8BF7}\u{68C0}\u{67E5}\u{767B}\u{5F55}\u{8DEF}\u{7531}\u{914D}\u{7F6E}');
    }
    var data = await res.json();
    if (!res.ok || data.success === false) {
        throw new Error(data.message || data.error || 'Passkey \u{8BF7}\u{6C42}\u{5931}\u{8D25}');
    }
    return data;
}

function passkeySupported() {
    if (!window.PublicKeyCredential || !navigator.credentials) {
        throw new Error('\u{5F53}\u{524D}\u{6D4F}\u{89C8}\u{5668}\u{4E0D}\u{652F}\u{6301} Passkey');
    }
}

// \u{6CE8}\u{518C} Passkey
async function registerPasskey(deviceName = '\u{6211}\u{7684}\u{8BBE}\u{5907}') {
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

// \u{4F7F}\u{7528} Passkey \u{767B}\u{5F55}
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
