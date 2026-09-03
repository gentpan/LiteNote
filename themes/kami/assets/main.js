(function() {
    'use strict';

    var root = document.documentElement;
    var themeKey = 'litenote-theme';

    function preferredMode() {
        try {
            var saved = localStorage.getItem(themeKey);
            if (saved === 'light' || saved === 'dark') {
                return saved;
            }
        } catch (e) {}
        return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }

    function setMode(mode, persist) {
        mode = mode === 'dark' ? 'dark' : 'light';
        root.setAttribute('data-theme', mode);
        document.querySelectorAll('[data-theme-toggle]').forEach(function(button) {
            var next = mode === 'dark' ? '\u{6D45}\u{8272}\u{6A21}\u{5F0F}' : '\u{6DF1}\u{8272}\u{6A21}\u{5F0F}';
            button.setAttribute('aria-pressed', mode === 'dark' ? 'true' : 'false');
            button.setAttribute('aria-label', next);
            button.setAttribute('title', next);
            var label = button.querySelector('[data-theme-label]');
            if (label) {
                label.textContent = next;
            }
        });
        if (persist) {
            try { localStorage.setItem(themeKey, mode); } catch (e) {}
        }
    }

    setMode(preferredMode(), false);

    document.addEventListener('click', function(event) {
        var themeToggle = event.target.closest ? event.target.closest('[data-theme-toggle]') : null;
        if (themeToggle) {
            setMode(root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark', true);
            return;
        }

        var copyButton = event.target.closest ? event.target.closest('[data-copy-url]') : null;
        if (copyButton) {
            var value = copyButton.getAttribute('data-copy-url') || location.href;
            var url = value.charAt(0) === '/' ? location.origin + value : value;
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(url).then(function() {
                    toast('\u{5DF2}\u{590D}\u{5236}');
                }).catch(function() {
                    toast(url);
                });
            } else {
                toast(url);
            }
            return;
        }

        var replyButton = event.target.closest ? event.target.closest('.comment-reply-btn') : null;
        if (replyButton) {
            var form = document.querySelector('.comment-form');
            if (!form) return;
            var parent = form.querySelector('[name=parent_id]');
            var textarea = form.querySelector('textarea[name=content]');
            if (parent) {
                parent.value = replyButton.getAttribute('data-parent-id') || '0';
            }
            if (textarea) {
                var nickname = replyButton.getAttribute('data-nickname') || '';
                textarea.placeholder = nickname ? '\u{56DE}\u{590D} ' + nickname + '...' : '\u{5199}\u{8BC4}\u{8BBA}...';
                textarea.focus();
            }
        }
    });

    document.querySelectorAll('a[href^="#"]').forEach(function(link) {
        link.addEventListener('click', function(event) {
            var target = document.querySelector(link.getAttribute('href'));
            if (!target) return;
            event.preventDefault();
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });

    document.querySelectorAll('[data-toast-message]').forEach(function(node) {
        toast(node.getAttribute('data-toast-message') || '');
    });

    function runWhenIdle(fn) {
        if ('requestIdleCallback' in window) {
            window.requestIdleCallback(fn, { timeout: 900 });
            return;
        }
        window.setTimeout(function() {
            fn({ timeRemaining: function() { return 16; }, didTimeout: true });
        }, 80);
    }

    function bindDeferredFavicons() {
        var queue = Array.prototype.slice.call(document.querySelectorAll('img[data-favicon-src]')).filter(function(img) {
            if (img.dataset.faviconDeferredReady === '1') {
                return false;
            }
            img.dataset.faviconDeferredReady = '1';
            return true;
        });
        if (!queue.length) return;

        function loadIcon(img) {
            var src = img.getAttribute('data-favicon-src');
            if (!src || !img.isConnected) return;
            var probe = new Image();
            probe.decoding = 'async';
            probe.referrerPolicy = 'no-referrer';
            probe.onload = function() {
                img.src = src;
                img.classList.remove('is-deferred');
                img.classList.add('is-loaded');
                img.removeAttribute('data-favicon-src');
            };
            probe.onerror = function() {
                img.classList.remove('is-deferred');
                img.classList.add('is-failed');
                img.removeAttribute('data-favicon-src');
            };
            probe.src = src;
        }

        function pump(deadline) {
            var budget = deadline && typeof deadline.timeRemaining === 'function' ? deadline.timeRemaining() : 16;
            while (queue.length && (budget > 4 || (deadline && deadline.didTimeout))) {
                loadIcon(queue.shift());
                budget = deadline && typeof deadline.timeRemaining === 'function' ? deadline.timeRemaining() : budget - 4;
            }
            if (queue.length) runWhenIdle(pump);
        }

        runWhenIdle(pump);
    }
    bindDeferredFavicons();

    function toast(message) {
        if (!message) return;
        var item = document.createElement('div');
        item.className = 'kami-toast';
        item.textContent = message;
        document.body.appendChild(item);
        requestAnimationFrame(function() {
            item.classList.add('is-visible');
        });
        setTimeout(function() {
            item.classList.remove('is-visible');
            setTimeout(function() { item.remove(); }, 180);
        }, 1800);
    }
})();

(function () {
    if (window.LiteNoteIdentityShell) {
        window.LiteNoteIdentityShell.boot({ prefix: 'kami' });
    }
})();
