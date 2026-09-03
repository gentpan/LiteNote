(function() {
    'use strict';

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

(function () {
    if (window.LiteNoteIdentityShell) {
        window.LiteNoteIdentityShell.boot({ prefix: 'kero' });
    }
})();

(function () {
    'use strict';
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
