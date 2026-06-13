@extends('layouts.front')

@section('content')
    @php
        $links = $links ?? [];
        $siteCopyItems = $siteCopyItems ?? [];
        $rssItems = $rssItems ?? [];
        $rssFreshByLink = $rssFreshByLink ?? [];
        $activeFriendTab = ($activeFriendTab ?? 'links') === 'feeds' ? 'feeds' : 'links';
        $friendIconPlaceholder = 'data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 32 32%22%3E%3Crect width=%2232%22 height=%2232%22 rx=%226%22 fill=%22%23f3f4f6%22/%3E%3Cpath d=%22M10 16h12M16 10v12%22 stroke=%22%239ca3af%22 stroke-width=%222.4%22 stroke-linecap=%22round%22/%3E%3C/svg%3E';
    @endphp

    <section class="friend-page">
        <header class="friend-header">
            <div class="friend-hero">
                <div class="friend-hero-copy">
                    <h1><i class="fa-solid fa-user-group" aria-hidden="true"></i> 友邻</h1>
                    <p>友情链接与最近订阅更新</p>
                </div>
                <nav class="friend-tabs friend-title-tabs" aria-label="友情链接页面切换" data-friend-tabs>
                    <a href="/links"
                       class="{{ $activeFriendTab === 'links' ? 'active' : '' }}"
                       data-friend-tab="links"
                       aria-current="{{ $activeFriendTab === 'links' ? 'page' : 'false' }}">
                        <i class="fa-solid fa-user-group" aria-hidden="true"></i>
                        <span>友情链接</span>
                        <strong>{{ count($links) }}</strong>
                    </a>
                    <a href="/subscribe"
                       class="{{ $activeFriendTab === 'feeds' ? 'active' : '' }}"
                       data-friend-tab="feeds"
                       aria-current="{{ $activeFriendTab === 'feeds' ? 'page' : 'false' }}">
                        <i class="fa-solid fa-square-rss" aria-hidden="true"></i>
                        <span>订阅文章</span>
                        <strong>{{ count($rssItems) }}</strong>
                    </a>
                </nav>
            </div>
        </header>

        <div class="friend-tab-panel" data-friend-panel="links" {{ $activeFriendTab === 'feeds' ? 'hidden' : '' }}>
            <div class="friend-panel">
                <div class="friend-panel-head">
                    <div>
                        <h3><i class="fa-solid fa-user-group" aria-hidden="true"></i> 站点列表</h3>
                        <p>{{ count($links) }} 个站点</p>
                    </div>
                </div>

                @if(\App\Core\Session::hasFlash('friend_link_success'))
                    <div hidden data-toast-type="success" data-toast-message="{{ \App\Core\Session::getFlash('friend_link_success') }}"></div>
                @endif
                @if(\App\Core\Session::hasFlash('friend_link_error'))
                    <div hidden data-toast-type="error" data-toast-message="{{ \App\Core\Session::getFlash('friend_link_error') }}"></div>
                @endif

                <div class="friend-list">
                    @if(empty($links))
                        <p class="empty friend-empty">还没有添加友情链接。</p>
                    @else
                        @foreach($links as $l)
                            @php
                                $friendHost = parse_url((string)$l->url, PHP_URL_HOST) ?: (string)$l->url;
                                $friendHost = preg_replace('/^www\./i', '', $friendHost) ?: $friendHost;
                                $friendLogo = trim((string)($l->logo ?? ''));
                                $friendIcon = $friendLogo !== '' ? $friendLogo : 'https://favicon.im/' . rawurlencode($friendHost);
                                $deferFriendIcon = $friendLogo === '';
                                $hasRss = trim((string)$l->rss_url) !== '';
                                $isFresh = !empty($rssFreshByLink[(int)$l->id]);
                            @endphp
                            <a class="friend-row" href="{{ $l->url }}" target="_blank" rel="nofollow noopener">
                                <img src="{{ $deferFriendIcon ? $friendIconPlaceholder : $friendIcon }}"
                                     @if($deferFriendIcon) data-favicon-src="{{ $friendIcon }}" @endif
                                     alt="{{ $friendHost }} favicon"
                                     class="friend-logo {{ $deferFriendIcon ? 'is-deferred' : '' }}"
                                     loading="lazy"
                                     decoding="async"
                                     fetchpriority="low"
                                     referrerpolicy="no-referrer">
                                <span class="friend-info">
                                    <strong>{{ $l->name }}</strong>
                                    @if($l->description)
                                        <span>-</span>
                                        <em>{{ $l->description }}</em>
                                    @endif
                                </span>
                                @if($hasRss)
                                    <span class="friend-rss-indicator {{ $isFresh ? 'is-fresh' : '' }}" title="{{ $isFresh ? '最近一周有更新' : '已配置 RSS' }}" aria-label="{{ $isFresh ? '最近一周有更新' : '已配置 RSS' }}">
                                        <i class="fa-solid fa-square-rss" aria-hidden="true"></i>
                                    </span>
                                @endif
                                <span class="friend-host">{{ $friendHost }}</span>
                            </a>
                        @endforeach
                    @endif
                </div>

                <div class="friend-request-box" id="friend-link-request">
                    <div class="friend-request-actions" aria-label="友链申请方式">
                        <button type="button" class="friend-request-btn" data-friend-request-open="apply">
                            <i class="fa-solid fa-user-plus" aria-hidden="true"></i>
                            <span>申请链接</span>
                        </button>
                        <button type="button" class="friend-request-btn" data-friend-request-open="modify">
                            <i class="fa-regular fa-pen-to-square" aria-hidden="true"></i>
                            <span>修改链接</span>
                        </button>
                    </div>
                </div>

                <div class="friend-site-info">
                    <div class="friend-site-head">
                        <h3><i class="fa-regular fa-address-card" aria-hidden="true"></i> 本站信息</h3>
                    </div>
                    <div class="friend-site-grid">
                        @foreach($siteCopyItems as $item)
                            @php $copyValue = trim((string)($item['value'] ?? '')); @endphp
                            <div class="friend-site-field">
                                <span class="friend-site-label"><i class="fa-regular fa-copy" aria-hidden="true"></i> {{ $item['label'] ?? '' }}</span>
                                <code>{{ $copyValue !== '' ? $copyValue : '未设置' }}</code>
                                <button type="button"
                                        class="friend-copy-btn"
                                        data-copy-text="{{ $copyValue }}"
                                        data-copy-label="{{ $item['label'] ?? '内容' }}"
                                        data-copy-message="{{ ($item['label'] ?? '内容') . '已复制' }}"
                                        title="复制{{ $item['label'] ?? '内容' }}"
                                        aria-label="复制{{ $item['label'] ?? '内容' }}"
                                        {{ $copyValue === '' ? 'disabled' : '' }}>
                                    <i class="fa-regular fa-copy" aria-hidden="true"></i>
                                </button>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <div class="friend-request-modal" data-friend-request-dialog="apply" hidden>
            <section class="friend-request-dialog" role="dialog" aria-modal="true" aria-labelledby="friend-apply-title">
                <div class="friend-request-dialog-head">
                    <div>
                        <h3 id="friend-apply-title"><i class="fa-solid fa-user-plus" aria-hidden="true"></i> 申请友链</h3>
                        <p>填写站点信息，提交后进入后台审核。</p>
                    </div>
                    <button type="button" class="friend-request-close" data-friend-request-close aria-label="关闭">
                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                    </button>
                </div>
                <div class="friend-request-dialog-body">
                    <form class="friend-request-form" method="post" action="/links/apply">
                        <input type="hidden" name="_csrf" value="{{ \App\Core\Session::csrfToken() }}">
                        <div class="friend-request-grid">
                            <label class="friend-request-field">
                                <span>站点名称 *</span>
                                <input type="text" name="name" required autocomplete="organization">
                            </label>
                            <label class="friend-request-field">
                                <span>站点地址 *</span>
                                <input type="text" name="url" placeholder="https://example.com" required autocomplete="url" data-friend-url-input>
                            </label>
                            <label class="friend-request-field">
                                <span>联系邮箱 *</span>
                                <input type="email" name="contact_email" required autocomplete="email">
                            </label>
                            <label class="friend-request-field">
                                <span>RSS 地址</span>
                                <input type="text" name="rss_url" placeholder="https://example.com/rss.xml" autocomplete="url">
                            </label>
                            <label class="friend-request-field">
                                <span>Logo 地址</span>
                                <input type="text" name="logo" placeholder="自动识别 favicon" autocomplete="url">
                            </label>
                            <label class="friend-request-field friend-request-field-full">
                                <span>站点描述</span>
                                <textarea name="description" rows="3" maxlength="255"></textarea>
                            </label>
                        </div>
                        <div class="friend-request-footer">
                            <p>输入站点地址后会自动识别域名和默认 Logo，提交后进入审核。</p>
                            <button type="submit" class="friend-request-submit">提交申请</button>
                        </div>
                    </form>
                </div>
            </section>
        </div>

        <div class="friend-request-modal" data-friend-request-dialog="modify" hidden>
            <section class="friend-request-dialog" role="dialog" aria-modal="true" aria-labelledby="friend-modify-title">
                <div class="friend-request-dialog-head">
                    <div>
                        <h3 id="friend-modify-title"><i class="fa-regular fa-pen-to-square" aria-hidden="true"></i> 修改友链</h3>
                        <p>填写旧链接和新资料，审核通过后再更新展示。</p>
                    </div>
                    <button type="button" class="friend-request-close" data-friend-request-close aria-label="关闭">
                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                    </button>
                </div>
                <div class="friend-request-dialog-body">
                    <form class="friend-request-form" method="post" action="/links/modify">
                        <input type="hidden" name="_csrf" value="{{ \App\Core\Session::csrfToken() }}">
                        <div class="friend-request-grid">
                            <label class="friend-request-field friend-request-field-full">
                                <span>当前已展示的原链接 *</span>
                                <input type="text" name="previous_url" placeholder="https://old.example.com" required autocomplete="url">
                            </label>
                            <label class="friend-request-field">
                                <span>新站点名称 *</span>
                                <input type="text" name="name" required autocomplete="organization">
                            </label>
                            <label class="friend-request-field">
                                <span>新站点地址 *</span>
                                <input type="text" name="url" placeholder="https://example.com" required autocomplete="url" data-friend-url-input>
                            </label>
                            <label class="friend-request-field">
                                <span>联系邮箱 *</span>
                                <input type="email" name="contact_email" required autocomplete="email">
                            </label>
                            <label class="friend-request-field">
                                <span>RSS 地址</span>
                                <input type="text" name="rss_url" placeholder="https://example.com/rss.xml" autocomplete="url">
                            </label>
                            <label class="friend-request-field">
                                <span>Logo 地址</span>
                                <input type="text" name="logo" placeholder="自动识别 favicon" autocomplete="url">
                            </label>
                            <label class="friend-request-field friend-request-field-full">
                                <span>站点描述</span>
                                <textarea name="description" rows="3" maxlength="255"></textarea>
                            </label>
                        </div>
                        <div class="friend-request-footer">
                            <p>修改不会直接覆盖旧信息，审核通过后再更新展示。</p>
                            <button type="submit" class="friend-request-submit">提交修改</button>
                        </div>
                    </form>
                </div>
            </section>
        </div>

        <div class="friend-tab-panel" data-friend-panel="feeds" {{ $activeFriendTab === 'links' ? 'hidden' : '' }}>
            <div class="friend-panel friend-feed-panel">
                <div class="friend-panel-head">
                    <div>
                        <h3><i class="fa-solid fa-square-rss" aria-hidden="true"></i> 订阅文章</h3>
                        <p>
                            友情链接最近更新
                            @if(!empty($lastUpdated))
                                · {!! \App\Core\Helper::timeTag(date('Y-m-d H:i:s', (int)$lastUpdated)) !!}
                            @endif
                        </p>
                    </div>
                    <span class="friend-feed-count">{{ count($rssItems) }} 篇</span>
                </div>

                @if(empty($rssItems))
                    <p class="empty friend-empty">暂时还没有友情链接的最新文章，过些时候再来看看吧。</p>
                @else
                    <div class="subscribe-feed-list">
                        @foreach($rssItems as $item)
                            <article class="subscribe-feed-card">
                                <div class="feed-card-kicker">
                                    <span>{{ $item['friend_name'] }}</span>
                                    <time>{!! \App\Core\Helper::timeTag($item['pubDate'] ?? '') !!}</time>
                                </div>
                                <h3 class="subscribe-feed-title">
                                    <a href="{{ $item['link'] }}" target="_blank" rel="nofollow noopener">{{ $item['title'] }}</a>
                                </h3>
                                @if(!empty($item['description']))
                                    <p class="subscribe-feed-excerpt">{{ \App\Core\Helper::truncate((string)$item['description'], 140) }}</p>
                                @endif
                            </article>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <script>
        (function () {
            var tabs = document.querySelectorAll('[data-friend-tab]');
            var panels = document.querySelectorAll('[data-friend-panel]');
            if (!tabs.length || !panels.length) return;

            function normalizeTab(tab) {
                return tab === 'feeds' ? 'feeds' : 'links';
            }

            function tabFromPath() {
                return normalizeTab(window.location.pathname === '/subscribe' ? 'feeds' : 'links');
            }

            function setTab(tab, updateUrl) {
                tab = normalizeTab(tab);
                tabs.forEach(function (link) {
                    var active = link.getAttribute('data-friend-tab') === tab;
                    link.classList.toggle('active', active);
                    link.setAttribute('aria-current', active ? 'page' : 'false');
                });
                panels.forEach(function (panel) {
                    panel.hidden = panel.getAttribute('data-friend-panel') !== tab;
                });
                document.querySelectorAll('.site-nav-bar a[href="/links"]').forEach(function (link) {
                    link.classList.toggle('active', tab === 'links');
                });
                document.querySelectorAll('.site-nav-bar a[href="/subscribe"]').forEach(function (link) {
                    link.classList.toggle('active', tab === 'feeds');
                });
                document.title = (tab === 'feeds' ? '订阅文章' : '友情链接') + document.title.replace(/^(友情链接|订阅文章)/, '');

                if (updateUrl && window.history && window.history.pushState) {
                    window.history.pushState({ friendTab: tab }, '', tab === 'feeds' ? '/subscribe' : '/links');
                }
            }

            tabs.forEach(function (link) {
                link.addEventListener('click', function (event) {
                    var tab = normalizeTab(link.getAttribute('data-friend-tab'));
                    if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
                    event.preventDefault();
                    setTab(tab, true);
                });
            });

            window.addEventListener('popstate', function () {
                setTab(tabFromPath(), false);
            });

            var requestDialogs = document.querySelectorAll('[data-friend-request-dialog]');
            var requestOpeners = document.querySelectorAll('[data-friend-request-open]');

            function requestMode(mode) {
                return mode === 'modify' ? 'modify' : 'apply';
            }

            function closeRequestDialogs() {
                requestDialogs.forEach(function (dialog) {
                    dialog.hidden = true;
                });
                document.body.classList.remove('friend-request-modal-open');
            }

            function openRequestDialog(mode) {
                mode = requestMode(mode);
                var activeDialog = document.querySelector('[data-friend-request-dialog="' + mode + '"]');
                if (!activeDialog) return;
                closeRequestDialogs();
                activeDialog.hidden = false;
                document.body.classList.add('friend-request-modal-open');
                window.setTimeout(function () {
                    var firstInput = activeDialog.querySelector('input, textarea, button');
                    if (firstInput) firstInput.focus();
                }, 30);
            }

            function hasOpenRequestDialog() {
                return Array.prototype.some.call(requestDialogs, function (dialog) {
                    return !dialog.hidden;
                });
            }

            function normalizeSiteUrl(value) {
                value = String(value || '').trim();
                if (!value) return null;
                if (!/^https?:\/\//i.test(value)) value = 'https://' + value;
                try {
                    var parsed = new URL(value);
                    if (!parsed.hostname || parsed.hostname.indexOf('.') === -1) return null;
                    parsed.hash = '';
                    return parsed;
                } catch (error) {
                    return null;
                }
            }

            function detectFriendSite(input, commitValue) {
                var parsed = normalizeSiteUrl(input.value);
                if (!parsed) return;
                var form = input.closest('form');
                if (!form) return;
                if (commitValue) input.value = parsed.href.replace(/\/$/, '');

                var host = parsed.hostname.replace(/^www\./i, '');
                var nameInput = form.querySelector('input[name="name"]');
                var logoInput = form.querySelector('input[name="logo"]');
                var rssInput = form.querySelector('input[name="rss_url"]');
                var autoName = host.split('.')[0] || host;
                var autoLogo = 'https://favicon.im/' + encodeURIComponent(host);

                if (nameInput && (!nameInput.value.trim() || nameInput.dataset.autoFriendName === nameInput.value.trim())) {
                    nameInput.value = autoName;
                    nameInput.dataset.autoFriendName = autoName;
                }
                if (logoInput && (!logoInput.value.trim() || logoInput.dataset.autoFriendLogo === logoInput.value.trim())) {
                    logoInput.value = autoLogo;
                    logoInput.dataset.autoFriendLogo = autoLogo;
                }
                if (rssInput && !rssInput.value.trim()) {
                    rssInput.placeholder = parsed.origin + '/rss.xml';
                }
            }

            requestOpeners.forEach(function (button) {
                button.addEventListener('click', function () {
                    openRequestDialog(button.getAttribute('data-friend-request-open'));
                });
            });

            requestDialogs.forEach(function (dialog) {
                dialog.addEventListener('click', function (event) {
                    if (event.target === dialog || event.target.closest('[data-friend-request-close]')) {
                        closeRequestDialogs();
                    }
                });
                dialog.querySelectorAll('[data-friend-url-input]').forEach(function (input) {
                    var detectTimer = null;
                    input.addEventListener('input', function () {
                        window.clearTimeout(detectTimer);
                        detectTimer = window.setTimeout(function () {
                            detectFriendSite(input, false);
                        }, 260);
                    });
                    input.addEventListener('blur', function () {
                        detectFriendSite(input, true);
                    });
                });
                dialog.querySelectorAll('.friend-request-form').forEach(function (form) {
                    form.addEventListener('submit', function () {
                        form.querySelectorAll('input[name="url"], input[name="previous_url"], input[name="rss_url"], input[name="logo"]').forEach(function (input) {
                            var parsed = normalizeSiteUrl(input.value);
                            if (parsed) input.value = parsed.href.replace(/\/$/, '');
                        });
                    });
                });
            });

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && hasOpenRequestDialog()) {
                    closeRequestDialogs();
                }
            });
        })();
        </script>
    </section>
@endsection
