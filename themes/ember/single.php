@extends('layouts.front')

@section('content')
    @php
        $postCover = $post->displayCover();
        $commentsOpen = (int)($post->allow_comments ?? 1) === 1;
        $postPublishedTs = strtotime((string)$post->published_at) ?: time();
        $postPublishedFull = date('Y-m-d H:i', $postPublishedTs);
        $postMonthLabels = ['', '一月', '二月', '三月', '四月', '五月', '六月', '七月', '八月', '九月', '十月', '十一月', '十二月'];
        $postDayLabels = [
            1 => '初一', 2 => '初二', 3 => '初三', 4 => '初四', 5 => '初五', 6 => '初六', 7 => '初七', 8 => '初八', 9 => '初九',
            10 => '初十', 11 => '十一', 12 => '十二', 13 => '十三', 14 => '十四', 15 => '十五', 16 => '十六', 17 => '十七',
            18 => '十八', 19 => '十九', 20 => '二十', 21 => '二十一', 22 => '二十二', 23 => '二十三', 24 => '二十四', 25 => '二十五',
            26 => '二十六', 27 => '二十七', 28 => '二十八', 29 => '二十九', 30 => '三十', 31 => '三十一',
        ];
        $postPublishedMonth = $postMonthLabels[(int)date('n', $postPublishedTs)] ?? date('m月', $postPublishedTs);
        $postPublishedDay = $postDayLabels[(int)date('j', $postPublishedTs)] ?? date('d日', $postPublishedTs);
        $postAuthorName = !empty($author) ? ($author->nickname ?: $author->username) : ($site['title'] ?? '作者');
        $postAuthorAvatar = !empty($author) ? $author->getAvatarUrl(40) : '';
    @endphp
    <article class="post-detail has-cover">
        <div class="post-body-card post-detail-card">
            <header class="post-hero-card">
                <div class="post-cover">
                    <img src="{{ $postCover }}" alt="{{ $post->title }}" loading="lazy" decoding="async" draggable="false">
                </div>
                <div class="post-hero-title">
                    <h1 class="post-title">
                        @if($post->is_top)<span class="badge badge-top">置顶</span>@endif
                        {{ $post->title }}
                    </h1>
                </div>
            </header>

            <div class="post-detail-content">
                @php
                    // 标题已在特色图/页头显示,正文里去掉开头与标题重复的标题行
                    $__bodyMd = $post->markdown();
                    if ($__bodyMd !== '') {
                        $__bodyMd = \App\Services\PostContentStorage::bodyWithoutTitleHeading($__bodyMd, (string) $post->title);
                        $__bodyHtml = \App\Core\Markdown::parse($__bodyMd);
                    } else {
                        $__bodyHtml = (string) $post->content;
                    }
                @endphp
                <aside class="post-side-stats" aria-label="文章数据">
                    <time class="post-side-stat post-side-stat--date" datetime="{{ date('c', $postPublishedTs) }}" title="{{ $postPublishedFull }}">
                        <span class="post-side-date">
                            <span>{{ $postPublishedMonth }}</span>
                            <span>{{ $postPublishedDay }}</span>
                        </span>
                    </time>
                    @if($category)
                        <a class="post-side-stat post-side-category" href="{{ \App\Core\Helper::categoryUrl((string)$category->slug) }}" title="分类：{{ $category->name }}">
                            <i class="{{ $category->iconClass() }}" aria-hidden="true"></i>
                            <span>{{ $category->name }}</span>
                        </a>
                    @endif
                    <span class="post-side-stat" title="{{ (int)$post->views }} 浏览">
                        <i class="fa-regular fa-eye" aria-hidden="true"></i>
                        <span>{{ \App\Core\Helper::compactNumber((int)$post->views) }}</span>
                    </span>
                    <button type="button" class="post-side-stat" title="{{ count($comments) }} 评论" data-post-comments-scroll>
                        <i class="fa-regular fa-comment" aria-hidden="true"></i>
                        <span>{{ \App\Core\Helper::compactNumber(count($comments)) }}</span>
                    </button>
                </aside>
                <div class="post-content">{!! $__bodyHtml !!}</div>
                <div class="post-end-like-wrap">
                    <button type="button" class="post-end-like post-like-btn" data-id="{{ $post->id }}" aria-label="点赞">
                        <i class="fa-regular fa-thumbs-up" aria-hidden="true"></i>
                        <span class="like-count">{{ (int)($post->likes_count ?? 0) }}</span>
                    </button>
                </div>
                <div class="post-license-card">
                    <div class="post-license-info">
                        @if($postAuthorAvatar !== '')
                            <img class="post-license-avatar" src="{{ $postAuthorAvatar }}" alt="{{ $postAuthorName }}" loading="lazy" width="28" height="28">
                        @endif
                        <strong class="post-license-author">{{ $postAuthorName }}</strong>
                        @if($category)
                            <span class="post-license-category">发布在&nbsp;<a class="post-license-inline-link post-license-category-link" href="{{ \App\Core\Helper::categoryUrl((string)$category->slug) }}"><i class="{{ $category->iconClass() }}" aria-hidden="true"></i>{{ $category->name }}</a></span>
                        @endif
                        <span class="post-license-terms">本文采用 <i class="fa-brands fa-creative-commons" aria-hidden="true"></i> <a class="post-license-inline-link" href="https://creativecommons.org/licenses/by-nc-sa/4.0/" target="_blank" rel="noopener noreferrer">CC BY-NC-SA 4.0</a>&nbsp;许可协议，转载请注明来源。</span>
                    </div>
                    <span class="post-end-tag" aria-hidden="true">THE END</span>
                </div>
                {{-- 标签功能已下线,UI 隐藏(数据 + 代码保留) --}}
                {{-- 文章底部 author block 已删除(2026-06) --}}

                <section class="comments" id="comments">
                    @php
                        $commentTotal = (int)($commentsTotal ?? count($comments));
                        $commentParticipants = [];
                        foreach ($comments as $commentItem) {
                            $participantKey = trim((string)($commentItem->email ?? ''));
                            if ($participantKey !== '') {
                                $participantKey = 'email:' . strtolower($participantKey);
                            } else {
                                $participantKey = trim((string)($commentItem->ip ?? ''));
                                if ($participantKey !== '') {
                                    $participantKey = 'ip:' . $participantKey;
                                } else {
                                    $participantKey = 'name:' . trim((string)($commentItem->nickname ?? ''));
                                }
                            }
                            if ($participantKey !== 'name:') {
                                $commentParticipants[$participantKey] = true;
                            }
                        }
                        $commentParticipantTotal = count($commentParticipants);
                    @endphp
                    <h3>
                        <span class="comments-title-label">
                            <i class="fa-regular fa-comment" aria-hidden="true"></i>
                            <span>{{ $post->title }}</span>
                        </span>
                        <span class="comments-title-stats">
                            <span><i class="fa-solid fa-people-group" aria-hidden="true"></i>{{ $commentParticipantTotal }} 人参与</span>
                            <span><i class="fa-regular fa-comment" aria-hidden="true"></i>{{ $commentTotal }} 条评论</span>
                        </span>
                    </h3>
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
                                        @php
                                            $commentCity = trim((string)($cmt->geo_city ?? ''));
                                            $commentLocationTitle = method_exists($cmt, 'locationLabel') ? $cmt->locationLabel() : $commentCity;
                                        @endphp
                                        @if($commentCity !== '')
                                            <span class="comment-location" title="{{ $commentLocationTitle }}">· {{ $commentCity }}</span>
                                        @endif
                                        @if($commentsOpen)<button type="button" class="comment-reply-btn" data-parent-id="{{ $cmt->id }}" data-nickname="{{ $cmt->nickname }}">回复</button>@endif
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
                                                        @php
                                                            $replyCity = trim((string)($reply->geo_city ?? ''));
                                                            $replyLocationTitle = method_exists($reply, 'locationLabel') ? $reply->locationLabel() : $replyCity;
                                                        @endphp
                                                        @if($replyCity !== '')
                                                            <span class="comment-location" title="{{ $replyLocationTitle }}">· {{ $replyCity }}</span>
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
                    @else
                        <p class="empty">评论已关闭。</p>
                    @endif
                </section>
            </div>
        </div>
    </article>
@endsection
