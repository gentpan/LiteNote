@php
    $images = $item->getImages();
    $imageCount = count($images);
    $music = $item->getRelation('music');
    $isMusicTalk = !empty($music);
    $comments = $item->getRelation('comments') ?: [];
    $mood = trim((string)($item->mood ?? ''));
    // 关键词(mood)显示在说说开头,与正文内联 #标签 合并去重
    $keywords = $mood !== ''
        ? array_values(array_unique(array_merge([$mood], $item->getKeywords())))
        : $item->getKeywords();
    $displayContent = $item->contentWithoutKeywords();
    $commentCount = count($comments);
    $locationName = method_exists($item, 'locationDisplayName') ? $item->locationDisplayName() : trim((string)($item->location_name ?: $item->location_city ?: ''));
    $locationTitle = method_exists($item, 'locationFullName') ? $item->locationFullName() : trim((string)($item->location_name ?: $item->location_city ?: ''));
    $weatherText = method_exists($item, 'weatherDisplayText') ? $item->weatherDisplayText() : '';
@endphp
@if($isMusicTalk)
    @include('partials.home-music-card')
@else
<article class="home-card home-card--talk home-talk-card" id="talk-{{ $item->id }}">
        @if(trim((string)$displayContent) !== '' || !empty($keywords))
            <div class="home-card-body talk-content">@if(!empty($keywords))<span class="talk-inline-keywords">@foreach($keywords as $keyword)<span>#{{ $keyword }}</span>@endforeach</span>@endif{{ $displayContent }}</div>
        @endif

        @if(!empty($images))
            <div class="home-card-media talk-images talk-images-count-{{ min($imageCount, 10) }}">
                @foreach($images as $img)
                    <img src="{{ trim($img) }}" alt="" loading="lazy" decoding="async">
                @endforeach
            </div>
        @endif

        <footer class="home-actions home-card-footer home-card-meta-bar">
            <div class="home-card-meta home-talk-meta">
                <span>{!! \App\Core\Helper::timeTag($item->publishedAt()) !!}</span>
                @if($locationName !== '')
                    <span class="home-talk-location" title="{{ $locationTitle }}">@include('partials.ln-icon', ['name' => 'map-pin']){{ $locationName }}</span>
                @endif
                @if($weatherText !== '')
                    <span class="home-talk-weather"><i class="fa-solid fa-cloud-sun" aria-hidden="true"></i>{{ $weatherText }}</span>
                @endif
            </div>
            <div class="home-card-actions home-talk-side">
                <button type="button" class="home-action talk-like-btn" data-id="{{ $item->id }}" aria-label="点赞">
                    @include('partials.ln-icon', ['name' => 'heart', 'trigger' => 'both'])<span class="like-count">{{ (int)($item->likes_count ?? 0) }}</span>
                </button>
                <button type="button" class="home-action talk-comment-toggle" data-target="talk-comments-{{ $item->id }}">
                    @include('partials.ln-icon', ['name' => 'message-circle'])<span>{{ $commentCount }}</span>
                </button>
            </div>
        </footer>

        <div class="talk-comments" id="talk-comments-{{ $item->id }}">
            @if(!empty($comments))
                <ul class="comment-list talk-comment-list">
                    @foreach(\App\Core\Helper::nestComments($comments) as $thread)
                        @php $cmt = $thread['comment']; @endphp
                        <li class="comment-item" data-id="{{ $cmt->id }}">
                            <img class="comment-avatar" src="{{ $cmt->getAvatarUrl(40) }}" alt="{{ $cmt->nickname }}" loading="lazy" width="32" height="32">
                            <div class="comment-body">
                                <div class="comment-meta">
                                    @php $commentAuthor = $cmt; @endphp
                                            @include('partials.comment-author-link')
                                    <span class="comment-time">· {!! \App\Core\Helper::timeTag($cmt->created_at) !!}</span>
                                    <button type="button" class="comment-reply-btn" data-parent-id="{{ $cmt->id }}" data-nickname="{{ $cmt->nickname }}">回复</button>
                                </div>
                                <div class="comment-content talk-comment-content">{{ $cmt->content }}</div>
                            </div>
                            @if(!empty($thread['replies']))
                                <ul class="comment-reply-list talk-reply-list">
                                    @foreach($thread['replies'] as $reply)
                                        <li class="comment-item comment-reply" data-id="{{ $reply->id }}">
                                            <img class="comment-avatar" src="{{ $reply->getAvatarUrl(40) }}" alt="{{ $reply->nickname }}" loading="lazy" width="28" height="28">
                                            <div class="comment-body">
                                                <div class="comment-meta">
                                                    @php $commentAuthor = $reply; @endphp
                                                            @include('partials.comment-author-link')
                                                    @if(!empty($reply->reply_to_name))<span class="reply-arrow">›</span><span class="reply-target">{{ $reply->reply_to_name }}</span>@endif
                                                    <span class="comment-time">· {!! \App\Core\Helper::timeTag($reply->created_at) !!}</span>
                                                    <button type="button" class="comment-reply-btn" data-parent-id="{{ $reply->id }}" data-nickname="{{ $reply->nickname }}">回复</button>
                                                </div>
                                                <div class="comment-content talk-comment-content">{{ preg_replace('/^@\S+\s*/u', '', (string) $reply->content) }}</div>
                                            </div>
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
                    <input type="hidden" name="nickname" value="{{ $adminCommentName }}">
                    <input type="hidden" name="email" value="{{ $adminCommentEmail }}">
                @else
                    <div class="form-row comment-profile-fields">
                        <input type="text" name="nickname" placeholder="昵称 *" autocomplete="nickname" required>
                        <input type="email" name="email" placeholder="邮箱 *" autocomplete="email" required>
                        <input type="text" name="website" placeholder="网站(选填)" autocomplete="url">
                    </div>
                @endif
                <textarea name="content" rows="3" placeholder="写评论..." autocomplete="off" required></textarea>
                <div class="comment-actions">
                    <button type="submit" aria-label="提交评论">@include('partials.ln-icon', ['name' => 'send', 'trigger' => 'both'])</button>
                </div>
            </form>
        </div>
</article>
@endif
