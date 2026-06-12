@extends('layouts.front')

@section('content')
    @php
        $comments = $comments ?? [];
        $friendPage = $friendPage ?? null;
        $siteCopyItems = $siteCopyItems ?? [];
        $rssItems = $rssItems ?? [];
        $rssFreshByLink = $rssFreshByLink ?? [];
        $activeFriendTab = ($activeFriendTab ?? 'links') === 'feeds' ? 'feeds' : 'links';
        $adminCommentName = !empty($currentAdmin) ? ($currentAdmin->nickname ?: $currentAdmin->username) : '';
        $adminCommentEmail = !empty($currentAdmin) ? (string)($currentAdmin->email ?? '') : '';
        $friendIconPlaceholder = 'data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 32 32%22%3E%3Crect width=%2232%22 height=%2232%22 rx=%226%22 fill=%22%23f3f4f6%22/%3E%3Cpath d=%22M10 16h12M16 10v12%22 stroke=%22%239ca3af%22 stroke-width=%222.4%22 stroke-linecap=%22round%22/%3E%3C/svg%3E';
    @endphp

    <section class="friend-page">
        <header class="friend-header">
            <nav class="friend-tabs friend-title-tabs" aria-label="友情链接页面切换" data-friend-tabs>
                <a href="/links"
                   class="{{ $activeFriendTab === 'links' ? 'active' : '' }}"
                   data-friend-tab="links"
                   aria-current="{{ $activeFriendTab === 'links' ? 'page' : 'false' }}">
                    <i class="fa-solid fa-user-group"></i>
                    <span>友情链接</span>
                </a>
                <a href="/subscribe"
                   class="{{ $activeFriendTab === 'feeds' ? 'active' : '' }}"
                   data-friend-tab="feeds"
                   aria-current="{{ $activeFriendTab === 'feeds' ? 'page' : 'false' }}">
                    <i class="fa-solid fa-rss"></i>
                    <span>订阅文章</span>
                </a>
            </nav>
        </header>

        <div class="friend-tab-panel" data-friend-panel="links" {{ $activeFriendTab === 'feeds' ? 'hidden' : '' }}>
            <div class="friend-panel">
                <div class="friend-panel-head">
                    <div>
                        <h3><i class="fa-solid fa-user-group"></i> 站点列表</h3>
                        <p>{{ count($links) }} 个站点</p>
                    </div>
                </div>

                @if(\App\Core\Session::hasFlash('friend_link_success'))
                    <div hidden data-toast-type="success" data-toast-message="{{ \App\Core\Session::getFlash('friend_link_success') }}"></div>
                @endif
                @if(\App\Core\Session::hasFlash('friend_link_error'))
                    <div hidden data-toast-type="error" data-toast-message="{{ \App\Core\Session::getFlash('friend_link_error') }}"></div>
                @endif

                <div class="friend-request-box" id="friend-link-request">
                    <div class="friend-request-actions" role="tablist" aria-label="友链申请方式">
                        <button type="button" class="friend-request-btn is-active" data-friend-request-tab="apply" aria-selected="true">
                            <i class="fa-solid fa-user-plus"></i>
                            <span>申请链接</span>
                        </button>
                        <button type="button" class="friend-request-btn" data-friend-request-tab="modify" aria-selected="false">
                            <i class="fa-regular fa-pen-to-square"></i>
                            <span>修改链接</span>
                        </button>
                    </div>
                    <div class="friend-request-panels">
                        <form class="friend-request-form" method="post" action="/links/apply" data-friend-request-panel="apply">
                            <input type="hidden" name="_csrf" value="{{ \App\Core\Session::csrfToken() }}">
                            <div class="friend-request-grid">
                                <label class="friend-request-field">
                                    <span>站点名称 *</span>
                                    <input type="text" name="name" required>
                                </label>
                                <label class="friend-request-field">
                                    <span>站点地址 *</span>
                                    <input type="text" name="url" placeholder="https://example.com" required>
                                </label>
                                <label class="friend-request-field">
                                    <span>联系邮箱 *</span>
                                    <input type="email" name="contact_email" required>
                                </label>
                                <label class="friend-request-field">
                                    <span>RSS 地址</span>
                                    <input type="text" name="rss_url" placeholder="https://example.com/rss.xml">
                                </label>
                                <label class="friend-request-field">
                                    <span>Logo 地址</span>
                                    <input type="text" name="logo" placeholder="https://example.com/avatar.png">
                                </label>
                                <label class="friend-request-field friend-request-field-full">
                                    <span>站点描述</span>
                                    <textarea name="description" rows="3" maxlength="255"></textarea>
                                </label>
                            </div>
                            <div class="friend-request-footer">
                                <p>提交后会进入后台待审核，审核通过后展示在友链列表。</p>
                                <button type="submit" class="friend-request-submit">提交申请</button>
                            </div>
                        </form>
                        <form class="friend-request-form" method="post" action="/links/modify" data-friend-request-panel="modify" hidden>
                            <input type="hidden" name="_csrf" value="{{ \App\Core\Session::csrfToken() }}">
                            <div class="friend-request-grid">
                                <label class="friend-request-field friend-request-field-full">
                                    <span>当前已展示的原链接 *</span>
                                    <input type="text" name="previous_url" placeholder="https://old.example.com" required>
                                </label>
                                <label class="friend-request-field">
                                    <span>新站点名称 *</span>
                                    <input type="text" name="name" required>
                                </label>
                                <label class="friend-request-field">
                                    <span>新站点地址 *</span>
                                    <input type="text" name="url" placeholder="https://example.com" required>
                                </label>
                                <label class="friend-request-field">
                                    <span>联系邮箱 *</span>
                                    <input type="email" name="contact_email" required>
                                </label>
                                <label class="friend-request-field">
                                    <span>RSS 地址</span>
                                    <input type="text" name="rss_url" placeholder="https://example.com/rss.xml">
                                </label>
                                <label class="friend-request-field">
                                    <span>Logo 地址</span>
                                    <input type="text" name="logo" placeholder="https://example.com/avatar.png">
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
                </div>

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
                                        <i class="fa-solid fa-rss"></i>
                                    </span>
                                @endif
                                <span class="friend-host">{{ $friendHost }}</span>
                            </a>
                        @endforeach
                    @endif
                </div>

                <div class="friend-site-info">
                    <div class="friend-site-head">
                        <h3><i class="fa-regular fa-address-card"></i> 本站信息</h3>
                        <p>复制后可直接发给对方站长。</p>
                    </div>
                    <div class="friend-site-grid">
                        @foreach($siteCopyItems as $item)
                            @php $copyValue = trim((string)($item['value'] ?? '')); @endphp
                            <div class="friend-site-field">
                                <span class="friend-site-label"><i class="{{ $item['icon'] ?? 'fa-regular fa-copy' }}"></i> {{ $item['label'] ?? '' }}</span>
                                <code>{{ $copyValue !== '' ? $copyValue : '未设置' }}</code>
                                <button type="button"
                                        class="friend-copy-btn"
                                        data-copy-text="{{ $copyValue }}"
                                        data-copy-label="{{ $item['label'] ?? '内容' }}"
                                        data-copy-message="{{ ($item['label'] ?? '内容') . '已复制' }}"
                                        title="复制{{ $item['label'] ?? '内容' }}"
                                        aria-label="复制{{ $item['label'] ?? '内容' }}"
                                        {{ $copyValue === '' ? 'disabled' : '' }}>
                                    <i class="fa-regular fa-copy"></i>
                                </button>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            @if($friendPage)
                <section class="comments friend-comments" id="friend-comments" data-page-comments>
                    <h3>友链留言 ({{ count($comments) }})</h3>
                    @if(\App\Core\Session::hasFlash('comment_success'))
                        <div hidden data-toast-type="success" data-toast-message="{{ \App\Core\Session::getFlash('comment_success') }}"></div>
                    @endif
                    @if(\App\Core\Session::hasFlash('comment_error'))
                        <div hidden data-toast-type="error" data-toast-message="{{ \App\Core\Session::getFlash('comment_error') }}"></div>
                    @endif

                    <ul class="comment-list">
                        @foreach(\App\Core\Helper::nestComments($comments) as $thread)
                            @php $cmt = $thread['comment']; @endphp
                            <li class="comment-item" data-id="{{ $cmt->id }}">
                                <img class="comment-avatar" src="{{ $cmt->getAvatarUrl(40) }}" alt="{{ $cmt->nickname }}" loading="lazy" width="32" height="32">
                                <div class="comment-body">
                                    <div class="comment-meta">
                                        @php $commentAuthor = $cmt; @endphp
                                    @include('partials.comment-author-link')
                                        <span>· {!! \App\Core\Helper::timeTag($cmt->created_at) !!}</span>
                                        <button type="button" class="comment-reply-btn" data-parent-id="{{ $cmt->id }}" data-nickname="{{ $cmt->nickname }}">回复</button>
                                    </div>
                                    <div class="comment-content">{{ $cmt->content }}</div>
                                </div>
                                @if(!empty($thread['replies']))
                                    <ul class="comment-reply-list">
                                        @foreach($thread['replies'] as $reply)
                                            <li class="comment-item comment-reply" data-id="{{ $reply->id }}">
                                                <img class="comment-avatar" src="{{ $reply->getAvatarUrl(40) }}" alt="{{ $reply->nickname }}" loading="lazy" width="28" height="28">
                                                <div class="comment-body">
                                                    <div class="comment-meta">
                                                        @php $commentAuthor = $reply; @endphp
                                                    @include('partials.comment-author-link')
                                                        @if(!empty($reply->reply_to_name))<span class="reply-arrow">›</span><span class="reply-target">{{ $reply->reply_to_name }}</span>@endif
                                                        <span>· {!! \App\Core\Helper::timeTag($reply->created_at) !!}</span>
                                                        <button type="button" class="comment-reply-btn" data-parent-id="{{ $reply->id }}" data-nickname="{{ $reply->nickname }}">回复</button>
                                                    </div>
                                                    <div class="comment-content">{{ preg_replace('/^@\S+\s*/u', '', (string) $reply->content) }}</div>
                                                </div>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </li>
                        @endforeach
                    </ul>

                    <form class="comment-form friend-comment-form" method="post" action="/comment/submit" data-comment-admin="{{ !empty($currentAdmin) ? '1' : '0' }}">
                        <input type="hidden" name="page_id" value="{{ $friendPage->id }}">
                        <input type="hidden" name="parent_id" value="0">
                        <input type="hidden" name="_csrf" value="{{ \App\Core\Session::csrfToken() }}">
                        @if(!empty($currentAdmin))
                            <input type="hidden" name="nickname" value="{{ $adminCommentName }}">
                            <input type="hidden" name="email" value="{{ $adminCommentEmail }}">
                        @else
                            <div class="form-row comment-profile-fields">
                                <input type="text" name="nickname" placeholder="昵称 *" required>
                                <input type="email" name="email" placeholder="邮箱{{ \App\Services\CommentSettingsService::emailRequired() ? ' *' : '（选填）' }}" {{ \App\Services\CommentSettingsService::emailRequired() ? 'required' : '' }}>
                                <input type="text" name="website" placeholder="网站(选填)">
                            </div>
                        @endif
                        <textarea name="content" rows="5" placeholder="说点什么... *" required></textarea>
                        <div class="comment-actions">
                            <button type="submit" aria-label="提交评论"><i class="fa-solid fa-paper-plane" aria-hidden="true"></i></button>
                        </div>
                    </form>
                </section>
            @endif
        </div>

        <div class="friend-tab-panel" data-friend-panel="feeds" {{ $activeFriendTab === 'links' ? 'hidden' : '' }}>
            <div class="friend-panel friend-feed-panel">
                <div class="friend-panel-head">
                    <div>
                        <h3><i class="fa-solid fa-rss"></i> 订阅文章</h3>
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

            var requestTabs = document.querySelectorAll('[data-friend-request-tab]');
            var requestPanels = document.querySelectorAll('[data-friend-request-panel]');
            if (requestTabs.length && requestPanels.length) {
                requestTabs.forEach(function (button) {
                    button.addEventListener('click', function () {
                        var mode = button.getAttribute('data-friend-request-tab') === 'modify' ? 'modify' : 'apply';
                        requestTabs.forEach(function (item) {
                            var active = item.getAttribute('data-friend-request-tab') === mode;
                            item.classList.toggle('is-active', active);
                            item.setAttribute('aria-selected', active ? 'true' : 'false');
                        });
                        requestPanels.forEach(function (panel) {
                            panel.hidden = panel.getAttribute('data-friend-request-panel') !== mode;
                        });
                    });
                });
            }
        })();
        </script>
    </section>
@endsection
