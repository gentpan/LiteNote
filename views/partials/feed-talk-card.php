@php
    $images = $item->getImages();
    $imageCount = count($images);
    $music = $item->getMusicEmbed();
    $comments = $item->getRelation('comments') ?: [];
    $keywords = $item->getKeywords();
    $displayContent = $item->contentWithoutKeywords();
@endphp
<article class="feed-card feed-talk-card" id="talk-{{ $item->id }}">
    <div class="talk-content">{{ $displayContent }}</div>

    @if(!empty($images))
        <div class="talk-images talk-images-count-{{ min($imageCount, 10) }}">
            @foreach($images as $img)
                <img src="{{ trim($img) }}" alt="" loading="lazy">
            @endforeach
        </div>
    @endif

    @if($music)
        <div class="talk-music">
            <div class="music-player">
                {!! $music['html'] !!}
            </div>
        </div>
    @endif

    <div class="feed-actions">
        <div class="feed-talk-meta">
            <span class="feed-talk-keywords">
                @foreach($keywords as $keyword)
                    <span>#{{ $keyword }}</span>
                @endforeach
            </span>
            <span class="feed-talk-dot">·</span>
            <span>{!! \App\Core\Helper::timeTag($item->created_at) !!}</span>
        </div>
        <div class="feed-talk-side">
            <button type="button" class="feed-action talk-like-btn" data-id="{{ $item->id }}" aria-label="点赞">
                <i class="fa-regular fa-thumbs-up"></i><span class="like-count">{{ (int)($item->likes_count ?? 0) }}</span>
            </button>
            <button type="button" class="feed-action talk-comment-toggle" data-target="talk-comments-{{ $item->id }}">
                <i class="fa-regular fa-comment"></i><span>{{ (int)($item->comments_count ?? count($comments)) }}</span>
            </button>
        </div>
    </div>

    <div class="talk-comments" id="talk-comments-{{ $item->id }}">
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
            <input type="hidden" name="talk_id" value="{{ $item->id }}">
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
                    <input type="email" name="email" placeholder="邮箱 *" required>
                    <input type="text" name="website" placeholder="网站(选填)">
                </div>
            @endif
            <textarea name="content" rows="3" placeholder="写评论..." required></textarea>
            <div class="comment-actions">
                @include('partials.comment-captcha')
                <button type="submit">提交评论</button>
            </div>
        </form>
    </div>
</article>
