@php
    /** @var \App\Models\Talk $item */
    /** @var \App\Models\Music $music */
    $comments = $comments ?? [];
    $shareText = trim((string)($item->contentWithoutKeywords() ?? ''));
    $ownerShareText = $shareText !== '' ? $shareText : '分享了这首音乐。';
    $musicRootComments = array_values(array_filter($comments, static function($comment) {
        return empty($comment->parent_id);
    }));
    $musicCommentCount = count($musicRootComments);
    $bloggerName = !empty($author) ? ($author->nickname ?: $author->username) : ($site['title'] ?? '博主');
    $bloggerAvatar = !empty($author) ? $author->getAvatarUrl(96) : '';
    $adminCommentName = !empty($currentAdmin) ? ($currentAdmin->nickname ?: $currentAdmin->username) : '';
    $adminCommentEmail = !empty($currentAdmin) ? (string)($currentAdmin->email ?? '') : '';
@endphp
<div class="talk-comments comments music-share-comments"
     id="talk-comments-{{ $item->id }}"
     data-music-comment-thread="{{ $music->id }}"
     data-comment-count="{{ $musicCommentCount }}">
    <div class="music-share-comments-head">
        <span>歌曲评论</span>
        <em><span data-music-comment-count>{{ $musicCommentCount }}</span> 条评论</em>
        <button type="button" class="music-share-comments-close" data-music-comments-close aria-label="关闭音乐评论">
            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
        </button>
    </div>
    <div class="music-share-owner-card">
        <div class="music-share-owner-avatar" aria-hidden="true">
            @if($bloggerAvatar !== '')
                <img src="{{ $bloggerAvatar }}" alt="{{ $bloggerName }}" loading="lazy" width="48" height="48">
            @else
                <span>{{ mb_substr((string)$bloggerName, 0, 1) }}</span>
            @endif
        </div>
        <div class="music-share-owner-body">
            <p class="music-share-comment-note">{{ $ownerShareText }}</p>
        </div>
    </div>
    @if(!empty($musicRootComments))
        <ul class="comment-list">
            @foreach($musicRootComments as $cmt)
                @php
                    $commentText = (string)$cmt->content;
                @endphp
                <li class="comment-item" data-id="{{ $cmt->id }}" data-full-content="{{ $commentText }}" tabindex="0" role="button">
                    <img class="music-share-comment-avatar" src="{{ $cmt->getAvatarUrl(64) }}" alt="{{ $cmt->nickname }}" loading="lazy" width="40" height="40">
                    <div class="comment-body">
                        <div class="comment-meta">
                            @php $commentAuthor = $cmt; @endphp
                                    @include('partials.comment-author-link')
                            <span class="ct">{!! \App\Core\Helper::timeTag($cmt->created_at) !!}</span>
                        </div>
                        <div class="comment-content">{{ $commentText }}</div>
                    </div>
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
                    <img class="comment-admin-avatar" src="{{ $currentAdmin->getAvatarUrl(80) }}" alt="{{ $adminCommentName }}">
                </button>
            @else
                <button type="button" class="comment-profile-toggle" data-comment-profile-toggle aria-label="修改评论资料" hidden>
                    <img class="comment-admin-avatar" src="{{ \App\Services\Gravatar::url('', 80, 'mp') }}" alt="" data-comment-profile-avatar data-comment-avatar-default="{{ \App\Services\Gravatar::url('', 80, 'mp') }}">
                </button>
            @endif
            @include('partials.comment-captcha')
            <button type="submit">提交乐评</button>
        </div>
    </form>
</div>
