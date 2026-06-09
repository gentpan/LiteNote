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
    $localCommentCount = (int)($tweet->comments_count ?? 0);
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
                            <i class="fa-solid fa-circle-check x-card-verified tweet-verified" aria-label="认证账号"></i>
                        @endif
                    </strong>
                    @if($handle !== '')<em>@{{ $handle }}</em>@endif
                </span>
            </div>
            @if($tweetUrl !== '')
                <a class="x-card-logo-link tweet-x-link" href="{{ $tweetUrl }}" target="_blank" rel="noopener noreferrer" aria-label="打开 X 原帖">
                    <i class="fa-brands fa-x-twitter"></i>
                </a>
            @endif
        </div>

        @if($body !== '')
            <div class="home-card-body x-card-body tweet-body">{!! $linkifyTweetBody($body) !!}</div>
        @elseif($tweetUrl !== '')
            <a class="home-card-body x-card-body x-card-body-link tweet-body tweet-body-link" href="{{ $tweetUrl }}" target="_blank" rel="noopener noreferrer">
                <i class="fa-brands fa-x-twitter"></i>
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
                        <i class="fa-regular fa-thumbs-up"></i><span class="like-count">{{ $tweet->localLikes() }}</span>
                    </button>
                @elseif($tweetLocalActions)
                    <button type="button" class="home-action x-tweet-like-btn" data-id="{{ $tweet->id }}" aria-label="点赞">
                        <i class="fa-regular fa-thumbs-up"></i><span class="like-count">{{ $localLikeCount }}</span>
                    </button>
                    <button type="button" class="home-action talk-comment-toggle" data-target="x-tweet-comments-{{ $tweet->id }}">
                        <i class="fa-regular fa-comment"></i><span>{{ $localCommentCount }}</span>
                    </button>
                @elseif($tweetUrl !== '')
                    @if($tweetShowReplies)
                        <a href="{{ $tweetUrl }}" target="_blank" rel="noopener noreferrer" class="x-card-action tweet-action" aria-label="在 X 打开评论">
                            <i class="fa-regular fa-comment"></i><span>{{ $repliesCount }}</span>
                        </a>
                    @endif
                    <a href="{{ $tweetUrl }}" target="_blank" rel="noopener noreferrer" class="x-card-action tweet-action" aria-label="在 X 打开并转发">
                        <i class="fa-solid fa-retweet"></i><span>{{ $repostsCount }}</span>
                    </a>
                    @if($likesCount > 0)
                        <a href="{{ $tweetUrl }}" target="_blank" rel="noopener noreferrer" class="x-card-action x-card-like tweet-action tweet-like" aria-label="在 X 打开并点赞">
                            <i class="fa-solid fa-heart"></i><span>{{ $likesCount }}</span>
                        </a>
                    @endif
                    @if(!$tweetHideViews)
                        <span class="x-card-action tweet-action" aria-label="浏览数">
                            <i class="fa-regular fa-eye"></i><span>{{ $viewsCount }}</span>
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
