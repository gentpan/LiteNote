(function() {
    'use strict';

    function frontCsrf() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        if (meta && meta.content) return meta.content;
        var field = document.querySelector('input[name="_csrf"]');
        return field ? field.value : '';
    }

    document.documentElement.setAttribute('data-theme', 'dark');

    document.addEventListener('click', function(event) {
        var copyTextBtn = event.target.closest ? event.target.closest('[data-copy-text]') : null;
        if (copyTextBtn) {
            event.preventDefault();
            var text = copyTextBtn.getAttribute('data-copy-text') || '';
            var message = copyTextBtn.getAttribute('data-copy-message') || '\u{5DF2}\u{590D}\u{5236}';
            if (!text) {
                toast('\u{6CA1}\u{6709}\u{53EF}\u{590D}\u{5236}\u{7684}\u{5185}\u{5BB9}');
                return;
            }
            copyValue(text, message);
            return;
        }

        var copyButton = event.target.closest ? event.target.closest('[data-copy-url]') : null;
        if (copyButton) {
            var value = copyButton.getAttribute('data-copy-url') || location.href;
            var url = value.charAt(0) === '/' ? location.origin + value : value;
            copyValue(url, '\u{5DF2}\u{590D}\u{5236}');
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
        item.className = 'kero-toast';
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

    function copyValue(value, message) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(value).then(function() {
                toast(message || '\u{5DF2}\u{590D}\u{5236}');
            }).catch(function() {
                toast(value);
            });
            return;
        }
        toast(value);
    }

    window.keroToast = toast;
    window.siteToast = toast;
})();

/* kero: music player + likes + publish upload */
(function() {
    'use strict';

    function toast(msg) {
        if (typeof window.keroToast === 'function') window.keroToast(msg);
    }

    function csrf() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        if (meta && meta.content) return meta.content;
        var field = document.querySelector('input[name="_csrf"]');
        return field ? field.value : '';
    }

    function ajaxHeaders() {
        return {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': csrf()
        };
    }

    function likedKey(type) { return 'litenote_liked_' + type; }

    function hasLiked(type, id) {
        id = String(id || '');
        if (!id) return false;
        try {
            var list = JSON.parse(localStorage.getItem(likedKey(type)) || '[]');
            return Array.isArray(list) && list.indexOf(id) !== -1;
        } catch (e) { return false; }
    }

    function rememberLiked(type, id) {
        id = String(id || '');
        if (!id) return;
        try {
            var list = JSON.parse(localStorage.getItem(likedKey(type)) || '[]');
            if (!Array.isArray(list)) list = [];
            if (list.indexOf(id) === -1) {
                list.push(id);
                localStorage.setItem(likedKey(type), JSON.stringify(list));
            }
        } catch (e) {}
    }

    function setToggleIcon(btn, name) {
        var icon = btn.querySelector('i');
        if (!icon) return;
        icon.className = name === 'pause'
            ? 'fa-solid fa-pause'
            : (name === 'error' ? 'fa-solid fa-triangle-exclamation' : 'fa-solid fa-play');
    }

    function updateLikeButton(btn, liked) {
        if (!btn) return;
        btn.classList.toggle('is-liked', !!liked);
        var icon = btn.querySelector('i');
        if (!icon) return;
        if (icon.classList.contains('fa-thumbs-up') || icon.classList.contains('fa-heart') || icon.className.indexOf('heart') !== -1 || icon.className.indexOf('thumbs') !== -1) {
            if (icon.className.indexOf('heart') !== -1) {
                icon.className = liked ? 'fa-solid fa-heart' : 'fa-regular fa-heart';
            } else {
                icon.className = liked ? 'fa-solid fa-thumbs-up' : 'fa-regular fa-thumbs-up';
            }
        }
    }

    function formatDuration(seconds) {
        seconds = Math.floor(seconds || 0);
        if (!isFinite(seconds) || seconds < 0) seconds = 0;
        var m = Math.floor(seconds / 60);
        var s = seconds % 60;
        return m + ':' + (s < 10 ? '0' : '') + s;
    }

    function decodeBase64(value) {
        value = String(value || '').trim();
        if (!value) return '';
        try {
            return decodeURIComponent(Array.prototype.map.call(atob(value), function(c) {
                return '%' + ('00' + c.charCodeAt(0).toString(16)).slice(-2);
            }).join(''));
        } catch (e) {
            try { return atob(value); } catch (err) { return ''; }
        }
    }

    function plainLrcText(text) {
        return String(text || '').replace(/\[[0-9:.]+\]/g, '').replace(/\r/g, '');
    }

    function parseLrcText(text) {
        var lines = [];
        String(text || '').split(/\r?\n/).forEach(function(raw) {
            var matches = raw.match(/\[(\d{1,2}):(\d{2})(?:\.(\d{1,3}))?\]/g);
            var content = raw.replace(/\[[^\]]*\]/g, '').trim();
            if (!matches || !content) return;
            matches.forEach(function(token) {
                var m = token.match(/\[(\d{1,2}):(\d{2})(?:\.(\d{1,3}))?\]/);
                if (!m) return;
                var ms = m[3] ? parseInt((m[3] + '000').slice(0, 3), 10) : 0;
                lines.push({
                    time: parseInt(m[1], 10) * 60 + parseInt(m[2], 10) + ms / 1000,
                    text: content
                });
            });
        });
        lines.sort(function(a, b) { return a.time - b.time; });
        return lines;
    }

    function lyricFetchUrl(url) {
        url = String(url || '').trim();
        if (!url) return '';
        if (url.indexOf('http') === 0) return '/proxy/lyrics?url=' + encodeURIComponent(url);
        return url;
    }

    function pauseOthers(except) {
        document.querySelectorAll('.music-card audio, .music-disc-player audio').forEach(function(audio) {
            if (audio !== except) audio.pause();
        });
    }

    function bindTalkLike(btn) {
        if (btn.dataset.lnBound) return;
        btn.dataset.lnBound = '1';
        var id = btn.getAttribute('data-id') || '';
        updateLikeButton(btn, hasLiked('talk', id));
        btn.addEventListener('click', function() {
            if (btn.disabled || !id) return;
            if (hasLiked('talk', id)) {
                updateLikeButton(btn, true);
                toast('\u{5DF2}\u{7ECF}\u{70B9}\u{8D5E}\u{8FC7}\u{4E86}');
                return;
            }
            btn.disabled = true;
            fetch('/talk/' + encodeURIComponent(id) + '/like', { method: 'POST', headers: ajaxHeaders() })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data && (data.code === 0 || data.code === 2)) {
                        var count = btn.querySelector('.like-count');
                        if (count) count.textContent = data.likes;
                        rememberLiked('talk', id);
                        updateLikeButton(btn, true);
                        toast(data.code === 2 ? (data.msg || '\u{5DF2}\u{7ECF}\u{70B9}\u{8D5E}\u{8FC7}\u{4E86}') : '\u{5DF2}\u{70B9}\u{8D5E}');
                    } else {
                        toast((data && data.msg) || '\u{70B9}\u{8D5E}\u{5931}\u{8D25}');
                    }
                })
                .catch(function() { toast('\u{70B9}\u{8D5E}\u{5931}\u{8D25}'); })
                .finally(function() { btn.disabled = false; });
        });
    }

    function bindMusicShareLike(btn) {
        if (btn.dataset.lnBound) return;
        btn.dataset.lnBound = '1';
        var id = btn.getAttribute('data-music-id') || '';
        updateLikeButton(btn, hasLiked('music', id));
        btn.addEventListener('click', function() {
            if (btn.disabled || !id) return;
            if (hasLiked('music', id)) {
                updateLikeButton(btn, true);
                toast('\u{5DF2}\u{7ECF}\u{559C}\u{6B22}\u{8FC7}\u{8FD9}\u{9996}\u{97F3}\u{4E50}\u{4E86}');
                return;
            }
            btn.disabled = true;
            fetch('/music/' + encodeURIComponent(id) + '/like', { method: 'POST', headers: ajaxHeaders() })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data && (data.code === 0 || data.code === 2)) {
                        var count = btn.querySelector('.like-count');
                        if (count) count.textContent = data.likes;
                        rememberLiked('music', id);
                        updateLikeButton(btn, true);
                        toast(data.code === 2 ? (data.msg || '\u{5DF2}\u{7ECF}\u{559C}\u{6B22}\u{8FC7}\u{4E86}') : '\u{5DF2}\u{559C}\u{6B22}');
                    } else {
                        toast((data && data.msg) || '\u{70B9}\u{8D5E}\u{5931}\u{8D25}');
                    }
                })
                .catch(function() { toast('\u{70B9}\u{8D5E}\u{5931}\u{8D25}'); })
                .finally(function() { btn.disabled = false; });
        });
    }

    function bindMusicCard(card) {
        if (card.dataset.lnBound) return;
        card.dataset.lnBound = '1';
        var audio = card.querySelector('audio');
        var btn = card.querySelector('.music-card-btn');
        if (!audio || !btn) return;
        var played = card.querySelector('.music-card-played');
        var curEl = card.querySelector('.music-card-cur');
        var durEl = card.querySelector('.music-card-dur');
        var track = card.querySelector('.music-card-track');

        function sync() {
            if (audio.duration && isFinite(audio.duration)) {
                if (durEl) durEl.textContent = formatDuration(audio.duration);
                if (played) played.style.width = ((audio.currentTime / audio.duration) * 100) + '%';
            }
            if (curEl) curEl.textContent = formatDuration(audio.currentTime);
        }

        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            if (audio.paused) {
                pauseOthers(audio);
                var p = audio.play();
                if (p && p.catch) p.catch(function() {});
            } else {
                audio.pause();
            }
        });

        card.querySelectorAll('[data-music-skip]').forEach(function(skip) {
            skip.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                var delta = parseFloat(skip.getAttribute('data-music-skip') || '0') || 0;
                audio.currentTime = Math.max(0, (audio.currentTime || 0) + delta);
                sync();
            });
        });

        if (track) {
            track.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                if (!audio.duration) return;
                var rect = track.getBoundingClientRect();
                audio.currentTime = Math.max(0, Math.min(1, (e.clientX - rect.left) / rect.width)) * audio.duration;
                sync();
            });
        }

        audio.addEventListener('play', function() {
            card.classList.add('playing');
            setToggleIcon(btn, 'pause');
        });
        audio.addEventListener('pause', function() {
            card.classList.remove('playing');
            setToggleIcon(btn, 'play');
            sync();
        });
        audio.addEventListener('ended', function() {
            card.classList.remove('playing');
            setToggleIcon(btn, 'play');
            sync();
        });
        audio.addEventListener('timeupdate', sync);
        audio.addEventListener('loadedmetadata', sync);
    }

    function bindMusicDiscPlayer(player) {
        if (player.dataset.lnBound) return;
        player.dataset.lnBound = '1';
        var audio = player.querySelector('audio');
        var playBtns = Array.prototype.slice.call(player.querySelectorAll('[data-music-play]'));
        if (!audio || !playBtns.length) return;

        var tracks = Array.prototype.slice.call(document.querySelectorAll('[data-music-track]'));
        var prevBtns = Array.prototype.slice.call(player.querySelectorAll('[data-music-prev]'));
        var nextBtns = Array.prototype.slice.call(player.querySelectorAll('[data-music-next]'));
        var likeBtns = Array.prototype.slice.call(player.querySelectorAll('[data-music-like]'));
        var titleEls = Array.prototype.slice.call(player.querySelectorAll('[data-music-title]'));
        var artistEls = Array.prototype.slice.call(player.querySelectorAll('[data-music-artist]'));
        var likesEls = Array.prototype.slice.call(player.querySelectorAll('[data-music-likes]'));
        var lyricsEls = Array.prototype.slice.call(player.querySelectorAll('[data-music-lyrics]'));
        var coverImgs = Array.prototype.slice.call(player.querySelectorAll('[data-music-cover]'));
        var coverFallbacks = Array.prototype.slice.call(player.querySelectorAll('[data-music-cover-fallback]'));
        var progressTracks = Array.prototype.slice.call(player.querySelectorAll('[data-music-progress]'));
        var progressPlayeds = Array.prototype.slice.call(player.querySelectorAll('[data-music-progress-played]'));
        var currentEls = Array.prototype.slice.call(player.querySelectorAll('[data-music-current]'));
        var durationEls = Array.prototype.slice.call(player.querySelectorAll('[data-music-duration]'));
        var commentsRoot = document.querySelector('[data-music-comments]');
        var commentTitleEl = commentsRoot ? commentsRoot.querySelector('[data-music-comment-title]') : null;
        var commentCountEl = commentsRoot ? commentsRoot.querySelector('[data-music-comment-count]') : null;
        var commentForm = commentsRoot ? commentsRoot.querySelector('.music-comment-form') : null;
        var playedIds = {};
        var currentIndex = Math.max(0, parseInt(player.dataset.currentIndex || '0', 10) || 0);
        var currentLyrics = [];
        var currentLyricIndex = -1;
        var lyricsCache = {};

        function setTextAll(nodes, text) {
            nodes.forEach(function(node) { node.textContent = text; });
        }

        function setProgressWidth(width) {
            progressPlayeds.forEach(function(node) { node.style.width = width; });
        }

        function setPlayIcon(name) {
            playBtns.forEach(function(btn) { setToggleIcon(btn, name); });
        }

        function renderLyrics(text, fallback) {
            if (!lyricsEls.length) return;
            lyricsEls.forEach(function(el) {
                el.innerHTML = '';
                el.scrollTop = 0;
            });
            currentLyrics = parseLrcText(text);
            currentLyricIndex = -1;
            var lines = currentLyrics.length
                ? currentLyrics.map(function(line) { return line.text; })
                : plainLrcText(text).split(/\r?\n/).map(function(line) { return line.trim(); }).filter(Boolean);
            if (!lines.length) lines = [fallback || '\u{6682}\u{65E0}\u{6B4C}\u{8BCD}'];
            lyricsEls.forEach(function(el) {
                lines.forEach(function(line, index) {
                    var span = document.createElement('span');
                    span.textContent = line;
                    span.dataset.lyricIndex = String(index);
                    el.appendChild(span);
                });
            });
            updateLyric(0, true);
        }

        function loadLyrics(url, fallbackText, fallbackLabel) {
            url = String(url || '').trim();
            if (!url) {
                renderLyrics(fallbackText || '', fallbackLabel || '');
                return;
            }
            renderLyrics('', '\u{6B4C}\u{8BCD}\u{52A0}\u{8F7D}\u{4E2D}...');
            if (Object.prototype.hasOwnProperty.call(lyricsCache, url)) {
                renderLyrics(lyricsCache[url], fallbackLabel || '');
                return;
            }
            fetch(lyricFetchUrl(url), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function(res) {
                    if (!res.ok) throw new Error('lyrics');
                    return res.text();
                })
                .then(function(text) {
                    lyricsCache[url] = text || '';
                    renderLyrics(text || fallbackText || '', fallbackLabel || '');
                })
                .catch(function() {
                    renderLyrics(fallbackText || '', fallbackLabel || '');
                });
        }

        function updateLyric(time, force) {
            if (!lyricsEls.length) return;
            if (!currentLyrics.length) {
                if (force) {
                    lyricsEls.forEach(function(el) {
                        Array.prototype.slice.call(el.querySelectorAll('span')).forEach(function(span, index) {
                            span.classList.toggle('is-active', index === 0);
                        });
                    });
                }
                return;
            }
            var next = 0;
            for (var i = 0; i < currentLyrics.length; i += 1) {
                if (currentLyrics[i].time <= time + 0.15) next = i;
                else break;
            }
            if (!force && next === currentLyricIndex) return;
            currentLyricIndex = next;
            lyricsEls.forEach(function(el) {
                var spans = Array.prototype.slice.call(el.querySelectorAll('span'));
                spans.forEach(function(span, index) {
                    span.classList.toggle('is-active', index === next);
                });
                var active = spans[next];
                if (active && typeof active.scrollIntoView === 'function') {
                    active.scrollIntoView({ block: 'center', behavior: 'smooth' });
                }
            });
        }

        function syncMusicComments(id, title, count) {
            if (!commentsRoot) return;
            commentsRoot.querySelectorAll('[data-music-comment-thread]').forEach(function(thread) {
                var active = String(thread.dataset.musicCommentThread || '') === String(id || '');
                thread.classList.toggle('is-active', active);
                thread.hidden = !active;
                if (active) count = thread.dataset.commentCount || count || '0';
            });
            if (commentTitleEl) commentTitleEl.textContent = title || '\u{97F3}\u{4E50}';
            if (commentCountEl) commentCountEl.textContent = String(count || '0');
            if (commentForm) {
                var musicInput = commentForm.querySelector('[name=music_id]');
                var parentInput = commentForm.querySelector('[name=parent_id]');
                if (musicInput) musicInput.value = id || '';
                if (parentInput) parentInput.value = '0';
            }
        }

        function applyTrack(index, autoplay) {
            if (!tracks.length) return;
            if (index < 0) index = tracks.length - 1;
            if (index >= tracks.length) index = 0;
            var track = tracks[index];
            var d = track.dataset;
            currentIndex = index;
            player.dataset.currentIndex = String(index);
            player.dataset.currentId = d.id || '';
            tracks.forEach(function(row) { row.classList.toggle('is-active', row === track); });
            setTextAll(titleEls, d.title || '\u{672A}\u{547D}\u{540D}\u{97F3}\u{4E50}');
            setTextAll(artistEls, d.artist || '\u{672A}\u{77E5}\u{6B4C}\u{624B}');
            setTextAll(likesEls, d.likes || '0');
            setTextAll(durationEls, d.duration || '0:00');
            setTextAll(currentEls, '0:00');
            setProgressWidth('0%');
            likeBtns.forEach(function(btn) { updateLikeButton(btn, hasLiked('music', d.id || '')); });
            syncMusicComments(d.id || '', d.title || '', d.comments || '0');
            loadLyrics(d.lyricsUrl || '', decodeBase64(d.lyrics || ''), d.description || '');

            if (d.cover) {
                coverImgs.forEach(function(img) {
                    img.src = d.cover;
                    img.alt = d.title || '';
                    img.classList.remove('is-hidden');
                });
                coverFallbacks.forEach(function(node) { node.classList.add('is-hidden'); });
            } else {
                coverImgs.forEach(function(img) {
                    img.removeAttribute('src');
                    img.classList.add('is-hidden');
                });
                coverFallbacks.forEach(function(node) {
                    node.textContent = (d.title || '\u266A').trim().slice(0, 1) || '\u266A';
                    node.classList.remove('is-hidden');
                });
            }

            if (audio.src !== d.audio) {
                audio.pause();
                audio.src = d.audio || '';
                audio.load();
            }
            if (autoplay) playAudio();
        }

        function playAudio() {
            pauseOthers(audio);
            var playing = audio.play();
            if (playing && playing.catch) {
                playing.catch(function() {
                    player.classList.add('is-error');
                    setPlayIcon('error');
                });
            }
        }

        playBtns.forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                if (audio.paused) playAudio();
                else audio.pause();
            });
        });
        prevBtns.forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                applyTrack(currentIndex - 1, true);
            });
        });
        nextBtns.forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                applyTrack(currentIndex + 1, true);
            });
        });
        tracks.forEach(function(track, index) {
            track.addEventListener('click', function() { applyTrack(index, true); });
        });
        progressTracks.forEach(function(bar) {
            bar.addEventListener('click', function(e) {
                if (!audio.duration) return;
                var rect = bar.getBoundingClientRect();
                audio.currentTime = Math.max(0, Math.min(1, (e.clientX - rect.left) / rect.width)) * audio.duration;
                updateLyric(audio.currentTime, true);
            });
        });
        likeBtns.forEach(function(likeBtn) {
            likeBtn.addEventListener('click', function(e) {
                e.preventDefault();
                if (likeBtn.disabled) return;
                var id = player.dataset.currentId || '';
                if (!id) return;
                if (hasLiked('music', id)) {
                    likeBtns.forEach(function(btn) { updateLikeButton(btn, true); });
                    toast('\u{5DF2}\u{7ECF}\u{559C}\u{6B22}\u{8FC7}\u{8FD9}\u{9996}\u{97F3}\u{4E50}\u{4E86}');
                    return;
                }
                likeBtns.forEach(function(btn) { btn.disabled = true; });
                fetch('/music/' + encodeURIComponent(id) + '/like', { method: 'POST', headers: ajaxHeaders() })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (data && (data.code === 0 || data.code === 2)) {
                            setTextAll(likesEls, data.likes);
                            if (tracks[currentIndex]) tracks[currentIndex].dataset.likes = String(data.likes);
                            rememberLiked('music', id);
                            likeBtns.forEach(function(btn) { updateLikeButton(btn, true); });
                            toast(data.code === 2 ? (data.msg || '\u{5DF2}\u{7ECF}\u{559C}\u{6B22}\u{8FC7}\u{4E86}') : '\u{5DF2}\u{559C}\u{6B22}');
                        } else {
                            toast((data && data.msg) || '\u{70B9}\u{8D5E}\u{5931}\u{8D25}');
                        }
                    })
                    .catch(function() { toast('\u{70B9}\u{8D5E}\u{5931}\u{8D25}'); })
                    .finally(function() { likeBtns.forEach(function(btn) { btn.disabled = false; }); });
            });
        });

        audio.addEventListener('play', function() {
            player.classList.add('is-playing');
            player.classList.remove('is-error');
            setPlayIcon('pause');
            var id = player.dataset.currentId || '';
            if (id && !playedIds[id]) {
                playedIds[id] = true;
                fetch('/music/' + encodeURIComponent(id) + '/play', { method: 'POST', headers: ajaxHeaders() }).catch(function() {});
            }
        });
        audio.addEventListener('pause', function() {
            player.classList.remove('is-playing');
            setPlayIcon('play');
        });
        audio.addEventListener('ended', function() {
            player.classList.remove('is-playing');
            setPlayIcon('play');
            if (tracks.length > 1) applyTrack(currentIndex + 1, true);
        });
        audio.addEventListener('timeupdate', function() {
            setTextAll(currentEls, formatDuration(audio.currentTime));
            if (audio.duration && isFinite(audio.duration)) {
                setProgressWidth((audio.currentTime / audio.duration * 100) + '%');
            }
            updateLyric(audio.currentTime, false);
        });
        audio.addEventListener('loadedmetadata', function() {
            if (audio.duration && isFinite(audio.duration)) {
                setTextAll(durationEls, formatDuration(audio.duration));
            }
        });
        audio.addEventListener('error', function() {
            player.classList.remove('is-playing');
            player.classList.add('is-error');
            setPlayIcon('error');
        });

        if (tracks.length) applyTrack(currentIndex, false);
    }

    function bindPublishForm(form) {
        if (form.dataset.lnBound) return;
        form.dataset.lnBound = '1';
        var btn = form.querySelector('.fp-upload-btn');
        var file = form.querySelector('.fp-upload-file');
        var status = form.querySelector('.fp-upload-status');
        var progress = status ? status.querySelector('.fp-upload-progress span') : null;
        var percent = status ? status.querySelector('.fp-upload-percent') : null;
        var imagesInput = form.querySelector('[name=images]');
        if (!btn || !file) return;

        btn.addEventListener('click', function() { file.click(); });
        file.addEventListener('change', function() {
            if (!file.files || !file.files.length) return;
            var body = new FormData();
            body.append('_csrf', csrf());
            body.append('purpose', 'talk');
            body.append('image', file.files[0]);
            if (status) {
                status.hidden = false;
                status.classList.remove('is-done', 'is-error');
            }
            if (progress) progress.style.width = '12%';
            if (percent) percent.textContent = '12%';
            fetch('/talk/upload-image', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': csrf() },
                body: body,
                credentials: 'same-origin'
            })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (!(data && data.code === 0 && data.data && data.data.url)) {
                        throw new Error((data && data.msg) || 'upload failed');
                    }
                    if (imagesInput) {
                        var current = String(imagesInput.value || '').trim().replace(/,\s*$/, '');
                        imagesInput.value = current ? (current + ',' + data.data.url) : data.data.url;
                    }
                    if (progress) progress.style.width = '100%';
                    if (percent) percent.textContent = '100%';
                    if (status) status.classList.add('is-done');
                    toast('\u{4E0A}\u{4F20}\u{6210}\u{529F}');
                })
                .catch(function() {
                    if (status) status.classList.add('is-error');
                    toast('\u{4E0A}\u{4F20}\u{5931}\u{8D25}');
                })
                .finally(function() { file.value = ''; });
        });
    }

    document.querySelectorAll('.talk-like-btn[data-id]').forEach(bindTalkLike);
    document.querySelectorAll('.music-share-like-btn[data-music-id]').forEach(bindMusicShareLike);
    document.querySelectorAll('.music-card').forEach(bindMusicCard);
    document.querySelectorAll('[data-music-disc-player]').forEach(bindMusicDiscPlayer);
    document.querySelectorAll('.front-publish-form').forEach(bindPublishForm);

    var commentsToggle = document.querySelector('[data-music-comments-toggle]');
    if (commentsToggle) {
        commentsToggle.addEventListener('click', function() {
            var root = commentsToggle.closest('[data-music-comments]');
            if (!root) return;
            root.classList.toggle('is-collapsed');
        });
    }
})();

/* kero: 访客身份卡 + 进入后台/登录 + 登录 dialog(密码+Passkey) —— 与 ember 同套,自含 md5/gravatar */
(function () {
    'use strict';
    var IDENTITY_KEY = 'litenote_comment_identity';

    function md5(input) {
        function cmn(q, a, b, x, s, t) { a = add32(add32(a, q), add32(x, t)); return add32((a << s) | (a >>> (32 - s)), b); }
        function ff(a, b, c, d, x, s, t) { return cmn((b & c) | ((~b) & d), a, b, x, s, t); }
        function gg(a, b, c, d, x, s, t) { return cmn((b & d) | (c & (~d)), a, b, x, s, t); }
        function hh(a, b, c, d, x, s, t) { return cmn(b ^ c ^ d, a, b, x, s, t); }
        function ii(a, b, c, d, x, s, t) { return cmn(c ^ (b | (~d)), a, b, x, s, t); }
        function add32(a, b) { return (a + b) & 0xffffffff; }
        function md5blk(s) { var blocks = [], i; for (i = 0; i < 64; i += 4) { blocks[i >> 2] = s.charCodeAt(i) + (s.charCodeAt(i + 1) << 8) + (s.charCodeAt(i + 2) << 16) + (s.charCodeAt(i + 3) << 24); } return blocks; }
        function md5cycle(x, k) {
            var a = x[0], b = x[1], c = x[2], d = x[3];
            a = ff(a, b, c, d, k[0], 7, -680876936); d = ff(d, a, b, c, k[1], 12, -389564586);
            c = ff(c, d, a, b, k[2], 17, 606105819); b = ff(b, c, d, a, k[3], 22, -1044525330);
            a = ff(a, b, c, d, k[4], 7, -176418897); d = ff(d, a, b, c, k[5], 12, 1200080426);
            c = ff(c, d, a, b, k[6], 17, -1473231341); b = ff(b, c, d, a, k[7], 22, -45705983);
            a = ff(a, b, c, d, k[8], 7, 1770035416); d = ff(d, a, b, c, k[9], 12, -1958414417);
            c = ff(c, d, a, b, k[10], 17, -42063); b = ff(b, c, d, a, k[11], 22, -1990404162);
            a = ff(a, b, c, d, k[12], 7, 1804603682); d = ff(d, a, b, c, k[13], 12, -40341101);
            c = ff(c, d, a, b, k[14], 17, -1502002290); b = ff(b, c, d, a, k[15], 22, 1236535329);
            a = gg(a, b, c, d, k[1], 5, -165796510); d = gg(d, a, b, c, k[6], 9, -1069501632);
            c = gg(c, d, a, b, k[11], 14, 643717713); b = gg(b, c, d, a, k[0], 20, -373897302);
            a = gg(a, b, c, d, k[5], 5, -701558691); d = gg(d, a, b, c, k[10], 9, 38016083);
            c = gg(c, d, a, b, k[15], 14, -660478335); b = gg(b, c, d, a, k[4], 20, -405537848);
            a = gg(a, b, c, d, k[9], 5, 568446438); d = gg(d, a, b, c, k[14], 9, -1019803690);
            c = gg(c, d, a, b, k[3], 14, -187363961); b = gg(b, c, d, a, k[8], 20, 1163531501);
            a = gg(a, b, c, d, k[13], 5, -1444681467); d = gg(d, a, b, c, k[2], 9, -51403784);
            c = gg(c, d, a, b, k[7], 14, 1735328473); b = gg(b, c, d, a, k[12], 20, -1926607734);
            a = hh(a, b, c, d, k[5], 4, -378558); d = hh(d, a, b, c, k[8], 11, -2022574463);
            c = hh(c, d, a, b, k[11], 16, 1839030562); b = hh(b, c, d, a, k[14], 23, -35309556);
            a = hh(a, b, c, d, k[1], 4, -1530992060); d = hh(d, a, b, c, k[4], 11, 1272893353);
            c = hh(c, d, a, b, k[7], 16, -155497632); b = hh(b, c, d, a, k[10], 23, -1094730640);
            a = hh(a, b, c, d, k[13], 4, 681279174); d = hh(d, a, b, c, k[0], 11, -358537222);
            c = hh(c, d, a, b, k[3], 16, -722521979); b = hh(b, c, d, a, k[6], 23, 76029189);
            a = hh(a, b, c, d, k[9], 4, -640364487); d = hh(d, a, b, c, k[12], 11, -421815835);
            c = hh(c, d, a, b, k[15], 16, 530742520); b = hh(b, c, d, a, k[2], 23, -995338651);
            a = ii(a, b, c, d, k[0], 6, -198630844); d = ii(d, a, b, c, k[7], 10, 1126891415);
            c = ii(c, d, a, b, k[14], 15, -1416354905); b = ii(b, c, d, a, k[5], 21, -57434055);
            a = ii(a, b, c, d, k[12], 6, 1700485571); d = ii(d, a, b, c, k[3], 10, -1894986606);
            c = ii(c, d, a, b, k[10], 15, -1051523); b = ii(b, c, d, a, k[1], 21, -2054922799);
            a = ii(a, b, c, d, k[8], 6, 1873313359); d = ii(d, a, b, c, k[15], 10, -30611744);
            c = ii(c, d, a, b, k[6], 15, -1560198380); b = ii(b, c, d, a, k[13], 21, 1309151649);
            a = ii(a, b, c, d, k[4], 6, -145523070); d = ii(d, a, b, c, k[11], 10, -1120210379);
            c = ii(c, d, a, b, k[2], 15, 718787259); b = ii(b, c, d, a, k[9], 21, -343485551);
            x[0] = add32(a, x[0]); x[1] = add32(b, x[1]); x[2] = add32(c, x[2]); x[3] = add32(d, x[3]);
        }
        function md51(s) {
            s = unescape(encodeURIComponent(s));
            var n = s.length, state = [1732584193, -271733879, -1732584194, 271733878], i, tail = new Array(16).fill(0);
            for (i = 64; i <= n; i += 64) md5cycle(state, md5blk(s.substring(i - 64, i)));
            s = s.substring(i - 64);
            for (i = 0; i < s.length; i++) tail[i >> 2] |= s.charCodeAt(i) << ((i % 4) << 3);
            tail[i >> 2] |= 0x80 << ((i % 4) << 3);
            if (i > 55) { md5cycle(state, tail); tail = new Array(16).fill(0); }
            tail[14] = n * 8;
            md5cycle(state, tail);
            return state;
        }
        function hex(x) { var out = '', i, j; for (i = 0; i < x.length; i++) for (j = 0; j < 4; j++) out += ('0' + ((x[i] >> (j * 8 + 4)) & 15).toString(16)).slice(-1) + ('0' + ((x[i] >> (j * 8)) & 15).toString(16)).slice(-1); return out; }
        return hex(md51(input));
    }

    function gravatarUrl(email, size) {
        email = String(email || '').trim().toLowerCase();
        if (!email) return '';
        return 'https://gravatar.bluecdn.com/avatar/' + md5(email) + '?s=' + (size || 80);
    }
    // 无邮箱时的灰色默认头像(gravatar mystery-person),不回退到博主头像
    function grayGravatar(size) {
        return 'https://gravatar.bluecdn.com/avatar/00000000000000000000000000000000?s=' + (size || 80);
    }
    function loadIdentity() { try { var raw = localStorage.getItem(IDENTITY_KEY); return raw ? JSON.parse(raw) : null; } catch (e) { return null; } }
    function saveIdentity(identity) { try { localStorage.setItem(IDENTITY_KEY, JSON.stringify(identity)); } catch (e) {} }
    function clearIdentity() { try { localStorage.removeItem(IDENTITY_KEY); } catch (e) {} }

    // 免验证码白名单(与 ember 同一 localStorage 键,跨主题一致):
    // 白名单 = 有审核通过的评论,或已通过身份表单验证码;后端 /api/visitor/stats 返回 trusted 为准
    var TRUSTED_KEY = 'litenote_trusted_emails';
    function loadTrustedEmails() { try { var v = JSON.parse(localStorage.getItem(TRUSTED_KEY) || '[]'); return Array.isArray(v) ? v : []; } catch (e) { return []; } }
    function isEmailTrusted(email) { email = String(email || '').trim().toLowerCase(); return !!email && loadTrustedEmails().indexOf(email) !== -1; }
    function addTrustedEmail(email) {
        email = String(email || '').trim().toLowerCase();
        if (!email) return;
        var list = loadTrustedEmails();
        if (list.indexOf(email) === -1) { list.push(email); try { localStorage.setItem(TRUSTED_KEY, JSON.stringify(list)); } catch (e) {} }
    }

    function updateSideIdentity(identity) {
        var wrap = document.querySelector('[data-side-identity]');
        if (!wrap) return;
        var hasIdentity = !!(identity && (identity.nickname || identity.email));
        wrap.classList.toggle('has-identity', hasIdentity);
        var img = wrap.querySelector('[data-side-identity-avatar]');
        var nameEl = wrap.querySelector('[data-side-identity-name]');
        var statEl = wrap.querySelector('[data-side-identity-stat]');
        var avatar = identity && (identity.avatar_url || gravatarUrl(identity.email, 80));
        if (img) { if (avatar) { img.src = avatar; img.hidden = false; } else { img.removeAttribute('src'); img.hidden = true; } }
        if (nameEl) nameEl.textContent = (identity && identity.nickname) ? identity.nickname : '';
        if (!hasIdentity) { if (statEl) statEl.textContent = '设置评论身份，留下你的足迹'; return; }
        if (statEl && identity.email) {
            statEl.textContent = '统计中…';
            fetch('/api/visitor/stats?email=' + encodeURIComponent(identity.email), { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (d) { var n = (d && d.comments) || 0; statEl.textContent = n > 0 ? ('已留下 ' + n + ' 条评论 · 欢迎回来 👋') : '期待你的第一条评论'; })
                .catch(function () { statEl.textContent = '欢迎回来 👋'; });
        } else if (statEl) { statEl.textContent = '欢迎回来 👋'; }
    }

    var identitySaveCb = null;
    function openIdentityDialog(options) {
        options = options || {};
        identitySaveCb = typeof options.onSave === 'function' ? options.onSave : null;
        var identity = loadIdentity() || {};
        var dialog = document.querySelector('.kero-identity-dialog');
        if (!dialog) {
            dialog = document.createElement('div');
            dialog.className = 'kero-identity-dialog login-overlay';
            dialog.innerHTML = '<div class="login-modal"><button type="button" class="login-modal-close" data-id-close aria-label="关闭"><i class="fa-solid fa-circle-xmark" aria-hidden="true"></i></button><div class="login-modal-head"><img class="kero-id-preview login-modal-avatar" alt=""><div><p class="login-modal-title">评论身份</p><p class="login-modal-subtitle">保存后评论会自动使用这份资料</p></div></div><form class="login-modal-form" data-id-form><label class="login-modal-field"><i class="fa-regular fa-circle-user" aria-hidden="true"></i><input name="nickname" placeholder="昵称 *" required></label><label class="login-modal-field"><i class="fa-regular fa-envelope" aria-hidden="true"></i><input name="email" type="email" placeholder="邮箱 *" required></label><label class="login-modal-field kero-id-captcha" data-id-captcha hidden><i class="fa-solid fa-shield-halved" aria-hidden="true"></i><input name="captcha" placeholder="验证码 *" autocomplete="off" maxlength="4"><img class="kero-id-captcha-img" data-id-captcha-img src="" alt="点击刷新验证码" title="看不清?点击刷新"></label><label class="login-modal-field"><i class="fa-solid fa-link" aria-hidden="true"></i><input name="website" placeholder="网站(选填)"></label><button type="submit" class="login-modal-submit">保存</button><button type="button" class="login-modal-passkey" data-id-clear>清除身份</button></form></div>';
            document.body.appendChild(dialog);
            dialog.addEventListener('click', function (e) { if (e.target === dialog) closeIdentityDialog(); });
            dialog.querySelector('[data-id-close]').addEventListener('click', closeIdentityDialog);
            dialog.querySelector('[data-id-clear]').addEventListener('click', function () { clearIdentity(); updateSideIdentity(null); applyIdentityToForms(null); closeIdentityDialog(); });
            dialog.querySelector('[data-id-captcha-img]').addEventListener('click', function () { this.src = '/captcha?t=' + Date.now(); });
            dialog.querySelector('[name=email]').addEventListener('input', function (e) {
                dialog.querySelector('.kero-id-preview').src = gravatarUrl(e.target.value, 80) || grayGravatar(80);
                refreshIdentityCaptcha(dialog, e.target.value);
            });
            dialog.querySelector('[data-id-form]').addEventListener('submit', function (e) {
                e.preventDefault();
                var f = e.currentTarget;
                var next = { nickname: f.nickname.value.trim(), email: f.email.value.trim(), website: f.website.value.trim() };
                if (!next.nickname && !next.email) { closeIdentityDialog(); return; }
                var finishSave = function () {
                    next.avatar_url = gravatarUrl(next.email, 80);
                    addTrustedEmail(next.email);
                    saveIdentity(next); updateSideIdentity(next); applyIdentityToForms(next);
                    var cb = identitySaveCb; identitySaveCb = null;
                    closeIdentityDialog();
                    if (cb) cb(next);
                };
                var capWrap = dialog.querySelector('[data-id-captcha]');
                if (!capWrap || capWrap.hidden) { finishSave(); return; }
                // 非白名单邮箱:验证码交后端校验,通过后该邮箱进白名单(任何设备生效)
                var capVal = (f.captcha.value || '').trim();
                if (capVal.length < 4) { f.captcha.focus(); return; }
                var saveBtn = f.querySelector('.login-modal-submit');
                if (saveBtn) saveBtn.disabled = true;
                var body = new FormData();
                body.append('email', next.email);
                body.append('captcha', capVal);
                body.append('_csrf', frontCsrf());
                fetch('/comment/verify-identity', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': frontCsrf() }, body: body, credentials: 'same-origin' })
                    .then(function (r) { return r.json(); })
                    .then(function (d) {
                        if (saveBtn) saveBtn.disabled = false;
                        if (d && d.ok) { finishSave(); return; }
                        var img = dialog.querySelector('[data-id-captcha-img]');
                        if (img) img.src = '/captcha?t=' + Date.now();
                        f.captcha.value = '';
                        f.captcha.placeholder = (d && d.msg) || '验证码错误，请重新输入';
                        f.captcha.focus();
                    })
                    .catch(function () { if (saveBtn) saveBtn.disabled = false; });
            });
        }
        dialog.querySelector('[name=nickname]').value = identity.nickname || '';
        dialog.querySelector('[name=email]').value = identity.email || '';
        dialog.querySelector('[name=website]').value = identity.website || '';
        var capInput = dialog.querySelector('[name=captcha]');
        if (capInput) { capInput.value = ''; capInput.placeholder = '验证码 *'; }
        dialog.querySelector('.kero-id-preview').src = identity.avatar_url || gravatarUrl(identity.email, 80) || grayGravatar(80);
        refreshIdentityCaptcha(dialog, identity.email || '');
        dialog.hidden = false; dialog.classList.add('is-open');
        document.body.classList.add('login-modal-open');
    }

    // 验证码显隐(与 ember 同策略):默认显示;白名单邮箱(本地或后端确认)隐藏;管理员不走此弹窗
    function refreshIdentityCaptcha(dialog, email) {
        var wrap = dialog.querySelector('[data-id-captcha]');
        if (!wrap) return;
        email = String(email || '').trim();
        function show(visible) {
            var wasHidden = wrap.hidden;
            wrap.hidden = !visible;
            if (visible) {
                var img = dialog.querySelector('[data-id-captcha-img]');
                if (img && (wasHidden || !img.getAttribute('src'))) img.src = '/captcha?t=' + Date.now();
            }
        }
        if (email && email.indexOf('@') !== -1 && isEmailTrusted(email)) { show(false); return; }
        show(true);
        if (!email || email.indexOf('@') === -1) return;
        fetch('/api/visitor/stats?email=' + encodeURIComponent(email), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d && d.trusted) {
                    addTrustedEmail(email);
                    var cur = dialog.querySelector('[name=email]');
                    if (cur && (cur.value || '').trim() === email) show(false);
                }
            })
            .catch(function () {});
    }
    function closeIdentityDialog() {
        identitySaveCb = null;
        var d = document.querySelector('.kero-identity-dialog');
        if (d) { d.hidden = true; d.classList.remove('is-open'); }
        document.body.classList.remove('login-modal-open');
    }

    function hasUsableIdentity() {
        var id = loadIdentity();
        return !!(id && id.nickname && id.email);
    }
    function applyIdentityToForms(identity) {
        document.querySelectorAll('.comment-form').forEach(function (form) {
            if (form.dataset.commentAdmin === '1') return;
            var n = form.querySelector('[name=nickname]'), em = form.querySelector('[name=email]'), w = form.querySelector('[name=website]');
            if (n) n.value = (identity && identity.nickname) || '';
            if (em) em.value = (identity && identity.email) || '';
            if (w) w.value = (identity && identity.website) || '';
        });
    }
    function bindCommentForms() {
        document.querySelectorAll('.comment-form').forEach(function (form) {
            if (form.dataset.lnCommentBound) return; form.dataset.lnCommentBound = '1';
            if (form.dataset.commentAdmin === '1') return;
            var ta = form.querySelector('[name=content]');
            if (ta) {
                ta.addEventListener('focus', function () {
                    if (hasUsableIdentity()) return;
                    openIdentityDialog({ onSave: function () { try { ta.focus(); } catch (e) {} } });
                });
            }
            // 原生提交前:存身份;非白名单邮箱(没验证过验证码)先弹身份弹窗,验证通过后再真正提交
            form.addEventListener('submit', function (e) {
                var n = form.querySelector('[name=nickname]'), em = form.querySelector('[name=email]'), w = form.querySelector('[name=website]');
                var id = { nickname: n ? n.value.trim() : '', email: em ? em.value.trim() : '', website: w ? w.value.trim() : '' };
                if (id.nickname || id.email) { id.avatar_url = gravatarUrl(id.email, 80); saveIdentity(id); }
                if (form.dataset.lnCaptchaPass === '1') { form.dataset.lnCaptchaPass = ''; return; }
                if (!isEmailTrusted(id.email)) {
                    e.preventDefault();
                    openIdentityDialog({ onSave: function () {
                        form.dataset.lnCaptchaPass = '1';
                        if (form.requestSubmit) form.requestSubmit();
                        else form.submit();
                    } });
                }
            });
        });
        // 滚动到评论区,访客无身份时自动弹一次身份表单
        var section = document.querySelector('.comment-form');
        if (section && !section.dataset.lnPromptBound && section.dataset.commentAdmin !== '1') {
            section.dataset.lnPromptBound = '1';
            if (!hasUsableIdentity() && 'IntersectionObserver' in window) {
                var ob = new IntersectionObserver(function (entries) {
                    entries.forEach(function (en) {
                        if (!en.isIntersecting) return;
                        ob.disconnect();
                        if (!hasUsableIdentity()) openIdentityDialog();
                    });
                }, { rootMargin: '0px 0px -20% 0px', threshold: 0.16 });
                ob.observe(section);
            }
        }
    }

    function loginCsrf() { var f = document.querySelector('[data-login-form] input[name=_csrf]') || document.querySelector('input[name=_csrf]'); return f ? f.value : ''; }
    function b64urlToBytes(v) { v = String(v || '').replace(/-/g, '+').replace(/_/g, '/'); while (v.length % 4) v += '='; return Uint8Array.from(atob(v), function (c) { return c.charCodeAt(0); }); }
    function bytesToB64url(b) { var a = new Uint8Array(b), s = ''; for (var i = 0; i < a.length; i++) s += String.fromCharCode(a[i]); return btoa(s).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/g, ''); }
    function pkJson(res) { var t = res.headers.get('content-type') || ''; if (t.indexOf('application/json') === -1) { return res.text().then(function () { throw new Error('Passkey 接口返回非 JSON'); }); } return res.json().then(function (d) { if (!res.ok || d.success === false) throw new Error(d.message || d.error || 'Passkey 请求失败'); return d; }); }
    function loginWithPasskey() {
        if (!window.PublicKeyCredential || !navigator.credentials) return Promise.reject(new Error('当前浏览器不支持 Passkey'));
        return fetch('/admin/passkey/login-options', { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
            .then(pkJson).then(function (options) {
                var allow = (options.allowCredentials || []).map(function (it) { return { type: it.type || 'public-key', id: b64urlToBytes(it.id) }; });
                return navigator.credentials.get({ publicKey: { challenge: b64urlToBytes(options.challenge), timeout: options.timeout, rpId: options.rpId, allowCredentials: allow, userVerification: options.userVerification || 'preferred' } });
            }).then(function (assertion) {
                var data = { id: assertion.id, rawId: bytesToB64url(assertion.rawId), response: { clientDataJSON: bytesToB64url(assertion.response.clientDataJSON), authenticatorData: bytesToB64url(assertion.response.authenticatorData), signature: bytesToB64url(assertion.response.signature), userHandle: assertion.response.userHandle ? bytesToB64url(assertion.response.userHandle) : '' } };
                return fetch('/admin/passkey/login', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': loginCsrf(), 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin', body: JSON.stringify({ credential: JSON.stringify(data) }) }).then(pkJson);
            });
    }
    function loginOverlay() { return document.querySelector('[data-login-overlay]'); }
    function loginErr(m) { var e = document.querySelector('[data-login-error]'); if (e) { e.textContent = m || ''; e.hidden = !m; } }
    function openLogin() { var o = loginOverlay(); if (!o) return; o.hidden = false; document.body.classList.add('login-modal-open'); var u = o.querySelector('[name=username]'); if (u) setTimeout(function () { try { u.focus(); } catch (e) {} }, 60); }
    function closeLogin() { var o = loginOverlay(); if (o) { o.hidden = true; document.body.classList.remove('login-modal-open'); loginErr(''); } }

    document.addEventListener('click', function (e) {
        var t = e.target;
        if (t.closest('[data-login-open]')) { e.preventDefault(); openLogin(); return; }
        if (t.closest('[data-login-close]')) { e.preventDefault(); closeLogin(); return; }
        if (t.closest('[data-identity-open]')) { e.preventDefault(); openIdentityDialog(); return; }
        var o = loginOverlay(); if (o && !o.hidden && t === o) closeLogin();
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { closeLogin(); closeIdentityDialog(); } });

    // footer 注入,脚本运行时元素已存在,直接初始化
    updateSideIdentity(loadIdentity());
    applyIdentityToForms(loadIdentity());
    bindCommentForms();
    var loginForm = document.querySelector('[data-login-form]');
    if (loginForm) {
        loginForm.addEventListener('submit', function (e) {
            e.preventDefault(); loginErr('');
            var btn = loginForm.querySelector('.login-modal-submit'); if (btn) btn.disabled = true;
            var body = new URLSearchParams();
            body.set('_csrf', loginCsrf()); body.set('username', (loginForm.username.value || '').trim()); body.set('password', loginForm.password.value || '');
            fetch('/admin/login', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' }, credentials: 'same-origin', body: body.toString() })
                .then(function (res) { return res.json().catch(function () { return {}; }).then(function (d) { return { ok: res.ok, data: d }; }); })
                .then(function (r) { if (r.ok && r.data && r.data.ok) { window.location.href = r.data.redirect || '/admin'; } else { loginErr((r.data && r.data.message) || '用户名或密码错误'); if (btn) btn.disabled = false; } })
                .catch(function (err) { loginErr('登录失败：' + err.message); if (btn) btn.disabled = false; });
        });
        var pk = document.querySelector('[data-login-passkey]');
        if (pk) pk.addEventListener('click', function () { loginErr(''); loginWithPasskey().then(function (r) { if (r && r.success !== false) window.location.href = '/admin'; else loginErr((r && r.message) || 'Passkey 登录失败'); }).catch(function (err) { loginErr('Passkey 登录失败：' + err.message); }); });
    }
    try {
        var params = new URLSearchParams(window.location.search || '');
        if (params.get('login') === '1') {
            openLogin();
            if (params.get('password_changed') === '1') {
                loginErr('密码已修改，请重新登录');
            }
        }
    } catch (e) {}

    document.querySelectorAll('.comment-load-more').forEach(function(btn) {
        if (btn.dataset.lnBound) return;
        btn.dataset.lnBound = '1';
        var section = btn.closest('.comments');
        if (!section) return;
        var ul = section.querySelector('.comment-list');
        if (!ul) return;
        var busy = false;
        btn.addEventListener('click', function() {
            if (busy) return;
            var postId = btn.dataset.postId;
            var offset = parseInt(btn.dataset.offset, 10) || 0;
            var limit = parseInt(btn.dataset.limit, 10) || 50;
            if (!postId) return;
            busy = true;
            btn.disabled = true;
            var originalText = btn.textContent;
            btn.textContent = '加载中...';
            fetch('/post/' + encodeURIComponent(postId) + '/comments?offset=' + offset + '&limit=' + limit, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data && data.code === 0 && data.html) {
                        var wrap = document.createElement('div');
                        wrap.innerHTML = data.html;
                        Array.prototype.slice.call(wrap.children).forEach(function(node) {
                            ul.appendChild(node);
                        });
                        if (data.hasMore) {
                            btn.dataset.offset = String(data.nextOffset || (offset + (data.count || 0)));
                            btn.disabled = false;
                            btn.textContent = originalText;
                        } else if (btn.parentNode) {
                            btn.parentNode.removeChild(btn);
                        }
                    } else {
                        btn.disabled = false;
                        btn.textContent = originalText;
                    }
                })
                .catch(function() {
                    btn.disabled = false;
                    btn.textContent = originalText;
                })
                .finally(function() { busy = false; });
        });
    });

    document.addEventListener('click', function(event) {
        var toggle = event.target.closest ? event.target.closest('.talk-comment-toggle') : null;
        if (!toggle) return;
        var targetId = toggle.getAttribute('data-target');
        if (!targetId) return;
        var panel = document.getElementById(targetId);
        if (!panel) return;
        panel.classList.toggle('is-open');
    });
})();
