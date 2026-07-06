@extends('layouts.front')

@section('content')
    @php $commentsOpen = (int)($post->allow_comments ?? 1) === 1; @endphp
    <article class="post-detail kami-article {{ $post->cover ? 'has-cover' : 'no-cover' }}">
        @if($post->cover)
            <figure class="kami-article-cover">
                <img src="{{ $post->cover }}" alt="{{ $post->title }}" loading="lazy" decoding="async" no-view draggable="false">
            </figure>
        @endif

        <header class="post-header kami-article-header">
            <p class="kami-page-kicker">
                @if($category)
                    <a href="{{ \App\Core\Helper::categoryUrl((string)$category->slug) }}">{{ $category->name }}</a>
                    <span>·</span>
                @endif
                <span>{!! \App\Core\Helper::timeTag($post->published_at) !!}</span>
            </p>
            <h1 class="post-title">
                @if($post->is_top)<span class="badge badge-top">置顶</span>@endif
                {{ $post->title }}
            </h1>
        </header>

        <div class="post-body-card post-detail-card kami-article-paper">
            <div class="post-detail-content">
                @php
                    $__bodyMd = $post->markdown();
                    if ($__bodyMd !== '') {
                        $__bodyMd = \App\Services\PostContentStorage::bodyWithoutTitleHeading($__bodyMd, (string) $post->title);
                        $__bodyHtml = \App\Core\Markdown::parse($__bodyMd);
                    } else {
                        $__bodyHtml = (string) $post->content;
                    }
                @endphp
                <div class="post-content">{!! $__bodyHtml !!}</div>
                <footer class="post-footer-meta">
                    <p class="post-meta">
                        <span><i class="fa-regular fa-eye" aria-hidden="true"></i> {{ $post->views }} 浏览</span>
                        <span><i class="fa-regular fa-comment" aria-hidden="true"></i> {{ (int)($commentsTotal ?? count($comments)) }} 评论</span>
                    </p>
                </footer>

                <section class="comments">
                    <h3>评论 ({{ (int)($commentsTotal ?? count($comments)) }})</h3>
                    @if(\App\Core\Session::hasFlash('comment_success'))
                        <div hidden data-toast-type="success" data-toast-message="{{ \App\Core\Session::getFlash('comment_success') }}"></div>
                    @endif
                    @if(\App\Core\Session::hasFlash('comment_error'))
                        <div hidden data-toast-type="error" data-toast-message="{{ \App\Core\Session::getFlash('comment_error') }}"></div>
                    @endif
                    <ul class="comment-list">
                        @foreach(\App\Core\Helper::nestComments($comments) as $thread)
                            @php
                                $cmt = $thread['comment'];
                                $cmtLocation = $cmt->frontLocationLabel();
                            @endphp
                            <li class="comment-item" data-id="{{ $cmt->id }}">
                                <img class="comment-avatar" src="{{ $cmt->getAvatarUrl(44) }}" alt="{{ $cmt->nickname }}" loading="lazy" width="32" height="32">
                                <div class="comment-body">
                                    <div class="comment-meta">
                                        <strong>{{ $cmt->nickname }}</strong>
                                        <span>· {!! \App\Core\Helper::timeTag($cmt->created_at) !!}</span>
                                        @if($cmtLocation !== '')
                                            <span class="comment-location"><span>{{ $cmtLocation }}</span></span>
                                        @endif
                                        @if($commentsOpen)<button type="button" class="comment-reply-btn" data-parent-id="{{ $cmt->id }}" data-nickname="{{ $cmt->nickname }}">回复</button>@endif
                                    </div>
                                    <div class="comment-content">{{ $cmt->content }}</div>
                                </div>
                                @if(!empty($thread['replies']))
                                    <ul class="comment-reply-list">
                                        @foreach($thread['replies'] as $reply)
                                            @php $replyLocation = $reply->frontLocationLabel(); @endphp
                                            <li class="comment-item comment-reply" data-id="{{ $reply->id }}">
                                                <img class="comment-avatar" src="{{ $reply->getAvatarUrl(40) }}" alt="{{ $reply->nickname }}" loading="lazy" width="28" height="28">
                                                <div class="comment-body">
                                                    <div class="comment-meta">
                                                        <strong>{{ $reply->nickname }}</strong>
                                                        @if(!empty($reply->reply_to_name))<span class="reply-arrow">›</span><span class="reply-target">{{ $reply->reply_to_name }}</span>@endif
                                                        <span>· {!! \App\Core\Helper::timeTag($reply->created_at) !!}</span>
                                                        @if($replyLocation !== '')
                                                            <span class="comment-location"><span>{{ $replyLocation }}</span></span>
                                                        @endif
                                                        @if($commentsOpen)<button type="button" class="comment-reply-btn" data-parent-id="{{ $reply->id }}" data-nickname="{{ $reply->nickname }}">回复</button>@endif
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
                    @if(!empty($commentsHasMore))
                    <div class="comment-load-more-wrap">
                        <button type="button" class="btn comment-load-more" data-post-id="{{ $post->id }}" data-offset="{{ $commentsPerPage }}" data-limit="{{ $commentsPerPage }}">加载更多评论</button>
                    </div>
                    @endif
                    @php
                        $adminCommentName = !empty($currentAdmin) ? ($currentAdmin->nickname ?: $currentAdmin->username) : '';
                        $adminCommentEmail = !empty($currentAdmin) ? (string)($currentAdmin->email ?? '') : '';
                    @endphp
                    @if($commentsOpen)
                    <form class="comment-form" method="post" action="/comment/submit" data-comment-admin="{{ !empty($currentAdmin) ? '1' : '0' }}">
                        <input type="hidden" name="post_id" value="{{ $post->id }}">
                        <input type="hidden" name="parent_id" value="0">
                        <input type="hidden" name="_csrf" value="{{ \App\Core\Session::csrfToken() }}">
                        @if(!empty($currentAdmin))
                            <div class="comment-admin-bar">
                                <img class="comment-admin-avatar" src="{{ $currentAdmin->getAvatarUrl(80) }}" alt="{{ $adminCommentName }}">
                                <div class="comment-admin-info">
                                    <span class="comment-admin-name">{{ $adminCommentName }}</span>
                                    @if($adminCommentEmail !== '')<span class="comment-admin-email">{{ $adminCommentEmail }}</span>@endif
                                </div>
                                <form method="post" action="/admin/logout" class="comment-admin-logout-form">
                                    <input type="hidden" name="_csrf" value="{{ \App\Core\Session::csrfToken() }}">
                                    <button type="submit" class="comment-admin-logout">注销</button>
                                </form>
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
                            <button type="submit">提交评论</button>
                        </div>
                    </form>
                    @else
                        <p class="empty">评论已关闭。</p>
                    @endif
                </section>
            </div>
        </div>
    </article>
@endsection
