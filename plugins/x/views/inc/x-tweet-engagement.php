@php
    $talkItem = $talkItem ?? $item ?? $s ?? null;
    $comments = $comments ?? ($talkItem ? ($talkItem->getRelation('comments') ?: []) : []);
    $keywords = $keywords ?? ($talkItem ? $talkItem->getKeywords() : []);
    $commentTotal = count($comments);
    $hideLocalFeedActions = !empty($tweetLocalActions);
@endphp
@if($talkItem)
    @if(!$hideLocalFeedActions)
    <div class="feed-actions">
        <div class="feed-talk-meta">
            <span class="feed-talk-keywords">
                @foreach($keywords as $keyword)
                    <span>#{{ $keyword }}</span>
                @endforeach
            </span>
            <span class="feed-talk-dot">·</span>
            <span>{!! \App\Core\Helper::timeTag($talkItem->publishedAt()) !!}</span>
        </div>
        <div class="feed-talk-side">
            <button type="button" class="feed-action talk-like-btn" data-id="{{ $talkItem->id }}" aria-label="点赞">
                <i class="fa-regular fa-thumbs-up"></i><span class="like-count">{{ (int)($talkItem->likes_count ?? 0) }}</span>
            </button>
            <button type="button" class="feed-action talk-comment-toggle" data-target="talk-comments-{{ $talkItem->id }}">
                <i class="fa-regular fa-comment"></i><span>{{ $commentTotal }}</span>
            </button>
        </div>
    </div>
    @endif

    <div class="talk-comments" id="x-tweet-comments-{{ $talkItem->id }}">
        @if(!empty($comments))
            <ul class="talk-comment-list">
                @foreach(\App\Core\Helper::nestComments($comments) as $thread)
                    @php $cmt = $thread['comment']; @endphp
                    <li data-id="{{ $cmt->id }}">
                        @php $commentAuthor = $cmt; @endphp
                                    @include('partials.comment-author-link')
                        <span class="comment-time">· {!! \App\Core\Helper::timeTag($cmt->created_at) !!}</span>
                        <button type="button" class="comment-reply-btn" data-parent-id="{{ $cmt->id }}" data-nickname="{{ $cmt->nickname }}">回复</button>
                        <span class="talk-comment-content">{{ $cmt->content }}</span>
                        @if(!empty($thread['replies']))
                            <ul class="talk-reply-list">
                                @foreach($thread['replies'] as $reply)
                                    <li data-id="{{ $reply->id }}">
                                        @php $commentAuthor = $reply; @endphp
                                                    @include('partials.comment-author-link')
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
            <input type="hidden" name="x_tweet_id" value="{{ $talkItem->id }}">
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
                @include('partials.comment-captcha')
                <button type="submit" aria-label="提交评论"><i class="fa-solid fa-paper-plane" aria-hidden="true"></i></button>
            </div>
        </form>
    </div>
@endif
