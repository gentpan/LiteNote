@php
    $comments = $s->getRelation('comments') ?: [];
    $commentCount = count($comments);
    $mood = trim((string)($s->mood ?? ''));
    // 关键词(mood)显示在说说开头,与正文内联 #标签 合并去重
    $keywords = $mood !== ''
        ? array_values(array_unique(array_merge([$mood], $s->getKeywords())))
        : $s->getKeywords();
    $displayContent = $s->contentWithoutKeywords();
    $images = $s->getImages();
    $locationName = method_exists($s, 'locationDisplayName') ? $s->locationDisplayName() : trim((string)($s->location_name ?: $s->location_city));
    $locationTitle = method_exists($s, 'locationFullName') ? $s->locationFullName() : trim((string)($s->location_name ?: $s->location_city));
    $weatherText = method_exists($s, 'weatherDisplayText') ? $s->weatherDisplayText() : '';
    $weatherIcon = method_exists($s, 'weatherIconClass') ? $s->weatherIconClass() : 'fa-solid fa-cloud';
@endphp
<article class="talk-card" id="talk-{{ $s->id }}">
    @if(trim((string)$displayContent) !== '' || !empty($keywords))
        <div class="talk-content">@if(!empty($keywords))<span class="talk-inline-keywords">@foreach($keywords as $keyword)<span>#{{ $keyword }}</span>@endforeach</span>@endif{{ $displayContent }}</div>
    @endif

    @if(!empty($images))
        <div class="talk-images">
            @foreach($images as $img)
                <img src="{{ trim($img) }}" alt="" loading="lazy">
            @endforeach
        </div>
    @endif

    <div class="talk-meta">
        <div class="talk-meta-main">
            <span class="time">{!! \App\Core\Helper::timeTag($s->publishedAt()) !!}</span>
            @if($locationName !== '')
                <span class="talk-location" title="{{ $locationTitle }}"><i class="fa-solid fa-location-dot"></i>{{ $locationName }}</span>
            @endif
            @if($weatherText !== '')
                <span class="talk-weather"><i class="{{ $weatherIcon }}"></i>{{ $weatherText }}</span>
            @endif
        </div>
        <div class="talk-meta-actions">
            <button type="button" class="feed-action talk-like-btn" data-id="{{ $s->id }}">
                <i class="fa-regular fa-thumbs-up"></i><span class="like-count">{{ (int)($s->likes_count ?? 0) }}</span>
            </button>
            <button type="button" class="feed-action talk-comment-toggle" data-target="talk-comments-{{ $s->id }}">
                <i class="fa-regular fa-comment"></i><span>{{ $commentCount }}</span>
            </button>
        </div>
    </div>

    <div class="talk-comments" id="talk-comments-{{ $s->id }}">
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
                        <img class="comment-admin-avatar" src="{{ \App\Services\Gravatar::url('', 80, 'mp') }}" alt="" data-comment-profile-avatar data-comment-avatar-default="{{ \App\Services\Gravatar::url('', 80, 'mp') }}">
                    </button>
                @endif
                <button type="submit">提交评论</button>
            </div>
        </form>
    </div>
</article>
