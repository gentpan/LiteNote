@php
    /** @var \App\Models\Talk $item */
    /** @var \App\Models\Music $music */
    $comments = $comments ?? [];
    $adminCommentName = !empty($currentAdmin) ? ($currentAdmin->nickname ?: $currentAdmin->username) : '';
    $adminCommentEmail = !empty($currentAdmin) ? (string)($currentAdmin->email ?? '') : '';
@endphp
<div class="talk-comments comments music-share-comments"
     id="talk-comments-{{ $item->id }}"
     data-music-comment-thread="{{ $music->id }}"
     data-comment-count="{{ count($comments) }}">
    <div class="music-share-comments-head">
        <span>{{ $music->title }}</span>
        <em><span data-music-comment-count>{{ count($comments) }}</span> 条评论</em>
    </div>
    @if(!empty($comments))
        <ul class="comment-list">
            @foreach(\App\Core\Helper::nestComments($comments) as $thread)
                @php $cmt = $thread['comment']; @endphp
                <li class="comment-item" data-id="{{ $cmt->id }}">
                    <div class="comment-body">
                        <div class="comment-meta">
                            <strong>{{ $cmt->nickname }}</strong>
                            <span class="ct">· {!! \App\Core\Helper::timeTag($cmt->created_at) !!}</span>
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
                                            <span class="ct">· {!! \App\Core\Helper::timeTag($reply->created_at) !!}</span>
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
    @else
        <p class="empty music-comment-empty">还没有评论，听完这一首再写一句吧。</p>
    @endif

    <form class="comment-form talk-comment-form music-share-comment-form" method="post" action="/comment/submit" data-comment-admin="{{ !empty($currentAdmin) ? '1' : '0' }}">
        <input type="hidden" name="music_id" value="{{ $music->id }}">
        <input type="hidden" name="parent_id" value="0">
        <input type="hidden" name="_csrf" value="{{ \App\Core\Session::csrfToken() }}">
        @if(!empty($currentAdmin))
            <input type="hidden" name="nickname" value="{{ $adminCommentName }}">
            <input type="hidden" name="email" value="{{ $adminCommentEmail }}">
        @else
            <div class="form-row comment-profile-fields">
                <input type="text" name="nickname" placeholder="昵称 *" required>
                <input type="email" name="email" placeholder="邮箱 *" required>
                <input type="text" name="website" placeholder="网站(选填)">
            </div>
        @endif
        <textarea name="content" rows="3" placeholder="写这首歌的评论..." required></textarea>
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
