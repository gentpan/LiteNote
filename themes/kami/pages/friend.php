@extends('layouts.front')

@section('content')
    @php
        $comments = $comments ?? [];
        $friendPage = $friendPage ?? null;
        $siteCopyItems = $siteCopyItems ?? [];
        $adminCommentName = !empty($currentAdmin) ? ($currentAdmin->nickname ?: $currentAdmin->username) : '';
        $adminCommentEmail = !empty($currentAdmin) ? (string)($currentAdmin->email ?? '') : '';
        $friendIconPlaceholder = 'data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 32 32%22%3E%3Crect width=%2232%22 height=%2232%22 rx=%226%22 fill=%22%23f3f4f6%22/%3E%3Cpath d=%22M10 16h12M16 10v12%22 stroke=%22%239ca3af%22 stroke-width=%222.4%22 stroke-linecap=%22round%22/%3E%3C/svg%3E';
    @endphp

    <section class="friend-page">
        <header class="friend-header">
            <h2 class="section-title">友情链接</h2>
            <p class="section-desc">欢迎互换友链。联系方式见<a href="/about">关于页</a>。</p>
        </header>

        <div class="friend-panel">
            <div class="friend-panel-head">
                <div>
                    <h3><i class="fa-solid fa-link"></i> 站点列表</h3>
                    <p>{{ count($links) }} 个站点</p>
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
                            <div class="comment-body">
                                <div class="comment-meta">
                                    <strong>{{ $cmt->nickname }}</strong>
                                    <span>· {!! \App\Core\Helper::timeTag($cmt->created_at) !!}</span>
                                    <button type="button" class="comment-reply-btn" data-parent-id="{{ $cmt->id }}" data-nickname="{{ $cmt->nickname }}">回复</button>
                                </div>
                                <div class="comment-content">{{ $cmt->content }}</div>
                            </div>
                            @if(!empty($thread['replies']))
                                <ul class="comment-reply-list">
                                    @foreach($thread['replies'] as $reply)
                                        <li class="comment-item comment-reply" data-id="{{ $reply->id }}">
                                            <div class="comment-body">
                                                <div class="comment-meta">
                                                    <strong>{{ $reply->nickname }}</strong>
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
                        <div class="comment-admin-bar">
                            <img class="comment-admin-avatar" src="{{ $currentAdmin->getAvatarUrl(80) }}" alt="{{ $adminCommentName }}">
                            <div class="comment-admin-info">
                                <span class="comment-admin-name">{{ $adminCommentName }}</span>
                                @if($adminCommentEmail !== '')<span class="comment-admin-email">{{ $adminCommentEmail }}</span>@endif
                            </div>
                            <a class="comment-admin-logout" href="/admin/logout">注销</a>
                        </div>
                        <input type="hidden" name="nickname" value="{{ $adminCommentName }}">
                        <input type="hidden" name="email" value="{{ $adminCommentEmail }}">
                    @else
                        <div class="form-row">
                            <input type="text" name="nickname" placeholder="昵称 *" required>
                            <input type="email" name="email" placeholder="邮箱{{ \App\Services\CommentSettingsService::emailRequired() ? ' *' : '（选填）' }}" {{ \App\Services\CommentSettingsService::emailRequired() ? 'required' : '' }}>
                            <input type="text" name="website" placeholder="网站(选填)">
                        </div>
                    @endif
                    <textarea name="content" rows="5" placeholder="说点什么... *" required></textarea>
                    <div class="comment-actions">
                        @include('partials.comment-captcha')
                        <button type="submit">提交评论</button>
                    </div>
                </form>
            </section>
        @endif
    </section>
@endsection
