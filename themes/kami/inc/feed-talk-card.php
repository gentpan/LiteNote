@php
    $images = $item->getImages();
    $imageCount = count($images);
    $music = $item->getRelation('music');
    $isMusicTalk = !empty($music);
    $comments = $item->getRelation('comments') ?: [];
    $keywords = $item->getKeywords();
    $displayContent = $item->contentWithoutKeywords();
    $commentCount = count($comments);
@endphp
<article class="feed-card feed-talk-card kami-feed-item kami-talk-item" id="talk-{{ $item->id }}">
    <div class="kami-feed-marker" aria-hidden="true">
        <span>{{ $isMusicTalk ? '乐' : '记' }}</span>
    </div>
    <div class="kami-feed-body">
            <div class="talk-content kami-talk-content">{{ $displayContent }}</div>

            @if(!empty($images))
                <div class="talk-images talk-images-count-{{ min($imageCount, 10) }}">
                    @foreach($images as $img)
                        <img src="{{ trim($img) }}" alt="" loading="lazy">
                    @endforeach
                </div>
            @endif

            @if($isMusicTalk)
                @include('partials.music-share-card')
            @endif

            <div class="feed-actions">
                <div class="feed-talk-meta">
                    @if($isMusicTalk)
                        <span class="feed-talk-music-label"><i class="fa-solid fa-music"></i> 音乐</span>
                    @else
                        <span class="feed-talk-keywords">
                            @foreach($keywords as $keyword)
                                <span>#{{ $keyword }}</span>
                            @endforeach
                        </span>
                    @endif
                    <span class="feed-talk-dot">·</span>
                    <span>{!! \App\Core\Helper::timeTag($item->publishedAt()) !!}</span>
                </div>
                <div class="feed-talk-side">
                @if($isMusicTalk)
                    <button type="button" class="feed-action music-share-like-btn" data-music-id="{{ $music->id }}" aria-label="喜欢这首音乐">
                        <i class="fa-regular fa-heart"></i><span data-music-like-count>{{ (int)($music->likes_count ?? 0) }}</span>
                    </button>
                    <button type="button" class="feed-action talk-comment-toggle" data-target="talk-comments-{{ $item->id }}" data-music-id="{{ $music->id }}" aria-label="查看这首音乐的评论">
                        <i class="fa-regular fa-comment"></i><span data-music-comment-count>{{ $commentCount }}</span>
                    </button>
                @else
                    <button type="button" class="feed-action talk-like-btn" data-id="{{ $item->id }}" aria-label="点赞">
                        <i class="fa-regular fa-thumbs-up"></i><span class="like-count">{{ (int)($item->likes_count ?? 0) }}</span>
                    </button>
                    <button type="button" class="feed-action talk-comment-toggle" data-target="talk-comments-{{ $item->id }}">
                        <i class="fa-regular fa-comment"></i><span>{{ (int)($item->comments_count ?? count($comments)) }}</span>
                    </button>
                @endif
                </div>
            </div>

            @if($isMusicTalk)
                @include('partials.music-share-comments')
            @else
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
    </div>
</article>
