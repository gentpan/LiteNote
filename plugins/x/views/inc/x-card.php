@php
    /** @var \App\Models\Talk $tweet */
    $tweet = $tweet ?? $s ?? null;
    $tweetData = $tweet ? $tweet->tweetData() : [];
    $tweetUrl = $tweet ? $tweet->tweetUrl() : '';
    $tweetId = $tweet ? $tweet->tweetId() : '';
    $handle = $tweet ? $tweet->tweetHandle() : '';
    $authorName = trim((string)($tweetData['author_name'] ?? $tweet->tweet_author_name ?? ''));
    $avatar = trim((string)($tweetData['author_avatar'] ?? $tweet->tweet_author_avatar ?? ''));
    $postedAt = trim((string)($tweetData['posted_at'] ?? $tweet->tweet_posted_at ?? ''));
    $body = trim((string)($tweetData['text'] ?? $tweet->content ?? ''));
    if ($body === $tweetUrl || str_starts_with($body, 'X 原帖 ')) {
        $body = '';
    }
    $images = !empty($tweetData['images']) && is_array($tweetData['images']) ? $tweetData['images'] : ($tweet ? $tweet->getImages() : []);
    $stripTweetMediaLinks = static function (string $text): string {
        $text = preg_replace('~https?://(?:x\.com|twitter\.com)/[^\s<]+/status(?:es)?/[0-9]+/(?:photo|video)/[0-9]+~iu', '', $text) ?? $text;
        $text = preg_replace('~https?://pic\.x\.com/[A-Za-z0-9_]+~iu', '', $text) ?? $text;
        $text = preg_replace('~https?://pbs\.twimg\.com/[^\s<]+~iu', '', $text) ?? $text;
        $text = preg_replace("~[ \t]+\R~u", "\n", $text) ?? $text;
        $text = preg_replace("~\R{3,}~u", "\n\n", $text) ?? $text;
        return trim($text);
    };
    if (!empty($images) && $body !== '') {
        $body = $stripTweetMediaLinks($body);
    }
    if ($body !== '') {
        $body = preg_replace("~[ \t]*\R[ \t]*\R+~u", "\n", $body) ?? $body;
    }
    $isVerified = !empty($tweetData['author_verified']) || (int)($tweet->tweet_author_verified ?? 0) === 1;
    $likesCount = (int)($tweetData['likes_count'] ?? $tweet->tweet_likes_count ?? 0);
    $repostsCount = (int)($tweetData['reposts_count'] ?? $tweet->tweet_reposts_count ?? 0);
    $repliesCount = (int)($tweetData['replies_count'] ?? 0);
    $viewsCount = (int)($tweetData['views_count'] ?? 0);
    $tweetShowReplies = !empty($tweetShowReplies);
    $tweetHideViews = !empty($tweetHideViews);
    $tweetLocalActions = !empty($tweetLocalActions);
    $tweetBookmark = !empty($tweetBookmark);
    $localLikeCount = (int)($tweet->likes_count ?? 0);
    $loadedComments = method_exists($tweet, 'getRelation') ? ($tweet->getRelation('comments') ?: null) : null;
    $localCommentCount = is_array($loadedComments) ? count($loadedComments) : (int)($tweet->comments_count ?? 0);
    $linkifyTweetLine = static function (string $text): string {
        $parts = preg_split('~(https?://[^\s<]+)~u', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        if (!is_array($parts)) {
            return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        }

        $html = '';
        foreach ($parts as $part) {
            if (preg_match('~^https?://~i', $part)) {
                $url = rtrim($part, ".,，。!?！？)]）");
                $tail = substr($part, strlen($url));
                $safeUrl = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
                $html .= '<a href="' . $safeUrl . '" target="_blank" rel="noopener noreferrer">' . $safeUrl . '</a>'
                    . htmlspecialchars($tail, ENT_QUOTES, 'UTF-8');
                continue;
            }
            $html .= htmlspecialchars($part, ENT_QUOTES, 'UTF-8');
        }

        return $html;
    };
    $linkifyTweetBody = static function (string $text) use ($linkifyTweetLine): string {
        $lines = preg_split('~\R~u', trim($text));
        if (!is_array($lines)) {
            $lines = [$text];
        }
        $html = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $html[] = $linkifyTweetLine($line);
        }
        return implode('<br>', $html);
    };
@endphp
@if($tweet)
    <div class="home-card home-card--x home-card--x-inner home-x-card x-card tweet-card kami-tweet-card">
        <div class="home-card-header x-card-head tweet-card-head">
            <div class="x-card-author tweet-author">
                @if($avatar !== '')
                    <img class="x-card-avatar tweet-avatar" src="{{ $avatar }}" alt="{{ $authorName ?: $handle ?: 'X' }}" loading="lazy" width="38" height="38">
                @else
                    <span class="x-card-avatar x-card-avatar-fallback tweet-avatar tweet-avatar-fallback">{{ mb_substr($authorName ?: $handle ?: 'X', 0, 1) }}</span>
                @endif
                <span class="x-card-author-text tweet-author-text">
                    <strong>
                        {{ $authorName ?: ($handle !== '' ? '@'.$handle : 'X 用户') }}
                        @if($isVerified)
                            <span class="x-card-verified tweet-verified" aria-label="已认证">
                                <svg viewBox="0 0 22 22" aria-hidden="true" focusable="false">
                                    <path d="M20.396 11c-.018-.646-.215-1.275-.57-1.816-.354-.54-.852-.972-1.438-1.246.223-.607.27-1.264.14-1.897-.131-.634-.437-1.218-.882-1.687-.47-.445-1.053-.75-1.687-.882-.633-.13-1.29-.083-1.897.14-.273-.587-.704-1.086-1.245-1.44S11.647 1.62 11 1.604c-.646.017-1.273.213-1.813.568s-.969.854-1.24 1.44c-.608-.223-1.267-.272-1.902-.14-.635.13-1.22.436-1.69.882-.445.47-.749 1.055-.878 1.688-.13.633-.08 1.29.144 1.896-.587.274-1.087.705-1.443 1.245-.356.54-.555 1.17-.574 1.817.02.647.218 1.276.574 1.817.356.54.856.972 1.443 1.245-.224.606-.274 1.263-.144 1.896.13.634.433 1.218.877 1.688.47.443 1.054.747 1.687.878.633.132 1.29.084 1.897-.136.274.586.705 1.084 1.246 1.439.54.354 1.17.551 1.816.569.647-.016 1.276-.213 1.817-.567s.972-.854 1.245-1.44c.604.239 1.266.296 1.903.164.636-.132 1.22-.447 1.68-.907.46-.46.776-1.044.908-1.681s.075-1.299-.165-1.903c.586-.274 1.084-.705 1.439-1.246.354-.54.551-1.17.569-1.816zM9.662 14.85l-3.429-3.428 1.293-1.302 2.072 2.072 4.4-4.794 1.347 1.246z"></path>
                                </svg>
                            </span>
                        @endif
                    </strong>
                    @if($handle !== '')<em>@{{ $handle }}</em>@endif
                </span>
            </div>
            @if($tweetUrl !== '')
                <a class="x-card-logo-link tweet-x-link" href="{{ $tweetUrl }}" target="_blank" rel="noopener noreferrer" aria-label="打开 X 原帖">
                    <i class="fa-brands fa-x-twitter" aria-hidden="true"></i>
                </a>
            @endif
        </div>

        @if($body !== '')
            <div class="home-card-body x-card-body tweet-body">{!! $linkifyTweetBody($body) !!}</div>
        @elseif($tweetUrl !== '')
            <a class="home-card-body x-card-body x-card-body-link tweet-body tweet-body-link" href="{{ $tweetUrl }}" target="_blank" rel="noopener noreferrer">
                <i class="fa-brands fa-x-twitter" aria-hidden="true"></i>
                <span>查看 X 原帖</span>
            </a>
        @endif

        @if(!empty($images))
            <div class="home-card-media {{ $tweetLocalActions ? 'talk-images' : 'x-card-media tweet-media' }} {{ $tweetLocalActions ? 'talk-images-count-' . min(count($images), 10) : 'x-card-media-count-' . min(count($images), 4) . ' tweet-media-count-' . min(count($images), 4) }}">
                @foreach(array_slice($images, 0, 4) as $idx => $img)
                    <img src="{{ trim($img) }}" alt="" loading="lazy" decoding="async">
                @endforeach
            </div>
        @endif

        <footer class="x-card-footer tweet-footer home-card-footer home-card-meta-bar">
            <div class="home-card-meta x-card-footer-meta tweet-footer-meta">
                @if($postedAt !== '')
                    {!! \App\Core\Helper::dateTimeTag($postedAt) !!}
                @else
                    {!! \App\Core\Helper::dateTimeTag($tweet->publishedAt()) !!}
                @endif
            </div>
            <div class="home-card-actions x-card-footer-actions tweet-footer-actions">
                @if($tweetBookmark)
                    <button type="button" class="home-action x-bookmark-like-btn" data-id="{{ $tweet->activityId() }}" aria-label="点赞">
                        <i class="fa-regular fa-thumbs-up" aria-hidden="true"></i><span class="like-count">{{ $tweet->localLikes() }}</span>
                    </button>
                @elseif($tweetLocalActions)
                    <button type="button" class="home-action x-tweet-like-btn" data-id="{{ $tweet->id }}" aria-label="点赞">
                        <i class="fa-regular fa-thumbs-up" aria-hidden="true"></i><span class="like-count">{{ $localLikeCount }}</span>
                    </button>
                    <button type="button" class="home-action talk-comment-toggle" data-target="x-tweet-comments-{{ $tweet->id }}">
                        <i class="fa-regular fa-comment" aria-hidden="true"></i><span>{{ $localCommentCount }}</span>
                    </button>
                @elseif($tweetUrl !== '')
                    @if($tweetShowReplies)
                        <a href="{{ $tweetUrl }}" target="_blank" rel="noopener noreferrer" class="x-card-action tweet-action" aria-label="在 X 打开评论">
                            <i class="fa-regular fa-comment" aria-hidden="true"></i><span>{{ $repliesCount }}</span>
                        </a>
                    @endif
                    <a href="{{ $tweetUrl }}" target="_blank" rel="noopener noreferrer" class="x-card-action tweet-action" aria-label="在 X 打开并转发">
                        <i class="fa-solid fa-retweet" aria-hidden="true"></i><span>{{ $repostsCount }}</span>
                    </a>
                    @if($likesCount > 0)
                        <a href="{{ $tweetUrl }}" target="_blank" rel="noopener noreferrer" class="x-card-action x-card-like tweet-action tweet-like" aria-label="在 X 打开并点赞">
                            <i class="fa-solid fa-heart" aria-hidden="true"></i><span>{{ $likesCount }}</span>
                        </a>
                    @endif
                    @if(!$tweetHideViews)
                        <span class="x-card-action tweet-action" aria-label="浏览数">
                            <i class="fa-regular fa-eye" aria-hidden="true"></i><span>{{ $viewsCount }}</span>
                        </span>
                    @endif
                @endif
            </div>
        </footer>
        @if($tweetLocalActions)
            @php $talkItem = $tweet; @endphp
            @include('partials.x-tweet-engagement')
        @endif
    </div>
@endif
