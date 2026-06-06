@extends('layouts.front')

@section('content')
    <section class="talk-list">
        <h2 class="section-title">滔客</h2>
        @include('partials.talk-publish-form')
        @if(empty($list))
            <p class="empty">还没有滔客</p>
        @endif
        <div class="js-list-items">
        @foreach($list as $s)
            @php
                $music = $s->getRelation('music');
                $isMusicTalk = !empty($music);
                $isTweetTalk = $s->isTweet();
                $comments = $s->getRelation('comments') ?: [];
                $keywords = $s->getKeywords();
                $displayContent = $s->contentWithoutKeywords();
                $commentCount = count($comments);
            @endphp
            <article class="talk-card {{ $isTweetTalk ? 'tweet-talk-card' : '' }}" id="talk-{{ $s->id }}">
                @if($isTweetTalk)
                    @php $tweet = $s; @endphp
                    @include('partials.tweet-card')
                @else
                    <div class="talk-content">{{ $displayContent }}</div>

                    @php $images = $s->getImages(); @endphp
                    @if(!empty($images))
                        <div class="talk-images">
                            @foreach($images as $img)
                                <img src="{{ trim($img) }}" alt="" loading="lazy">
                            @endforeach
                        </div>
                    @endif

                    @if($isMusicTalk)
                        @include('partials.music-share-card')
                    @endif

                    <div class="talk-meta">
                        <div class="talk-meta-main">
                            <span class="talk-keywords">
                                @foreach($keywords as $keyword)
                                    <span>#{{ $keyword }}</span>
                                @endforeach
                            </span>
                            <span class="feed-talk-dot">·</span>
                            <span class="time">{!! \App\Core\Helper::timeTag($s->publishedAt()) !!}</span>
                        </div>
                        <div class="talk-meta-actions">
                            @if($isMusicTalk)
                                <button type="button" class="feed-action music-share-like-btn" data-music-id="{{ $music->id }}" aria-label="喜欢这首音乐">
                                    <i class="fa-regular fa-heart"></i><span data-music-like-count>{{ (int)($music->likes_count ?? 0) }}</span>
                                </button>
                                <button type="button" class="feed-action talk-comment-toggle" data-target="talk-comments-{{ $s->id }}" data-music-id="{{ $music->id }}" aria-label="查看这首音乐的评论">
                                    <i class="fa-regular fa-comment"></i><span data-music-comment-count>{{ $commentCount }}</span>
                                </button>
                            @else
                                <button type="button" class="feed-action talk-like-btn" data-id="{{ $s->id }}">
                                    <i class="fa-regular fa-thumbs-up"></i><span class="like-count">{{ (int)($s->likes_count ?? 0) }}</span>
                                </button>
                                <button type="button" class="feed-action talk-comment-toggle" data-target="talk-comments-{{ $s->id }}">
                                    <i class="fa-regular fa-comment"></i><span>{{ (int)($s->comments_count ?? count($comments)) }}</span>
                                </button>
                            @endif
                        </div>
                    </div>

                    @if($isMusicTalk)
                        @php $item = $s; @endphp
                        @include('partials.music-share-comments')
                    @else
                    <div class="talk-comments" id="talk-comments-{{ $s->id }}">
                        @if(!empty($comments))
                            <ul class="talk-comment-list">
                                @foreach(\App\Core\Helper::nestComments($comments) as $thread)
                                    @php $cmt = $thread['comment']; @endphp
                                    <li data-id="{{ $cmt->id }}">
                                        <strong>{{ $cmt->nickname }}</strong>
                                        <span class="comment-time">· {!! \App\Core\Helper::timeTag($cmt->created_at) !!}</span>
                                        <button type="button" class="comment-reply-btn" data-parent-id="{{ $cmt->id }}" data-nickname="{{ $cmt->nickname }}">回复</button>
                                        <span class="talk-comment-content">{{ $cmt->content }}</span>
                                        @if(!empty($thread['replies']))
                                            <ul class="talk-reply-list">
                                                @foreach($thread['replies'] as $reply)
                                                    <li data-id="{{ $reply->id }}">
                                                        <strong>{{ $reply->nickname }}</strong>
                                                        @if(!empty($reply->reply_to_name))<span class="reply-arrow">›</span><span class="reply-target">{{ $reply->reply_to_name }}</span>@endif
                                                        <span class="comment-time">· {!! \App\Core\Helper::timeTag($reply->created_at) !!}</span>
                                                        <button type="button" class="comment-reply-btn" data-parent-id="{{ $reply->id }}" data-nickname="{{ $reply->nickname }}">回复</button>
                                                        <span class="talk-comment-content">{{ preg_replace('/^@\S+\s*/u', '', (string) $reply->content) }}</span>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                        @php
                            $adminCommentName = !empty($currentAdmin) ? ($currentAdmin->nickname ?: $currentAdmin->username) : '';
                            $adminCommentEmail = !empty($currentAdmin) ? (string)($currentAdmin->email ?? '') : '';
                        @endphp
                        <form class="comment-form talk-comment-form" method="post" action="/comment/submit" data-comment-admin="{{ !empty($currentAdmin) ? '1' : '0' }}">
                            <input type="hidden" name="talk_id" value="{{ $s->id }}">
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
                            <textarea name="content" rows="3" placeholder="写评论..." required></textarea>
                            <div class="comment-actions">
                                @if(!empty($currentAdmin))
                                    <button type="button" class="comment-profile-toggle comment-profile-toggle-admin" aria-label="当前登录头像">
                                        <img class="comment-admin-avatar" src="{{ $currentAdmin->getAvatarUrl(80) }}" alt="">
                                    </button>
                                @else
                                    <button type="button" class="comment-profile-toggle" data-comment-profile-toggle aria-label="切换评论资料" hidden>
                                        <img class="comment-admin-avatar" src="{{ \App\Services\Gravatar::url('', 80) }}" alt="" data-comment-profile-avatar data-comment-avatar-default="{{ \App\Services\Gravatar::url('', 80) }}">
                                    </button>
                                @endif
                                @include('partials.comment-captcha')
                                <button type="submit">提交评论</button>
                            </div>
                        </form>
                    </div>
                    @endif
                @endif
            </article>
        @endforeach
        </div>
        {!! $paginator ?? '' !!}
    </section>
@endsection
