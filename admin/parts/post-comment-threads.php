@foreach($threads as $thread)
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
                @if(!empty($commentsOpen))<button type="button" class="comment-reply-btn" data-parent-id="{{ $cmt->id }}" data-nickname="{{ $cmt->nickname }}">回复</button>@endif
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
                                @if(!empty($commentsOpen))<button type="button" class="comment-reply-btn" data-parent-id="{{ $reply->id }}" data-nickname="{{ $reply->nickname }}">回复</button>@endif
                            </div>
                            <div class="comment-content">{{ preg_replace('/^@\S+\s*/u', '', (string) $reply->content) }}</div>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </li>
@endforeach
