@php
    /** @var \App\Models\Talk $tweet */
    $tweet = $tweet ?? $s ?? null;
    $tweetData = $tweet ? $tweet->tweetData() : [];
    $tweetUrl = $tweet ? $tweet->tweetUrl() : '';
    $handle = $tweet ? $tweet->tweetHandle() : '';
    $authorName = trim((string)($tweetData['author_name'] ?? $tweet->tweet_author_name ?? ''));
    $avatar = trim((string)($tweetData['author_avatar'] ?? $tweet->tweet_author_avatar ?? ''));
    $postedAt = trim((string)($tweetData['posted_at'] ?? $tweet->tweet_posted_at ?? ''));
    $body = trim((string)($tweetData['text'] ?? $tweet->content ?? ''));
    if ($body === $tweetUrl || str_starts_with($body, 'X 原帖 ')) {
        $body = '';
    }
    $body = preg_replace("~[ \t]*\R[ \t]*\R+~u", "\n", $body) ?? $body;
    $images = !empty($tweetData['images']) && is_array($tweetData['images']) ? $tweetData['images'] : ($tweet ? $tweet->getImages() : []);
    $isVerified = !empty($tweetData['author_verified']) || (int)($tweet->tweet_author_verified ?? 0) === 1;
    $likesCount = (int)($tweetData['likes_count'] ?? $tweet->tweet_likes_count ?? 0);
    $repostsCount = (int)($tweetData['reposts_count'] ?? $tweet->tweet_reposts_count ?? 0);
    $repliesCount = (int)($tweetData['replies_count'] ?? 0);
    $viewsCount = (int)($tweetData['views_count'] ?? 0);
    $tweetShowReplies = !empty($tweetShowReplies);
    $tweetHideViews = !empty($tweetHideViews);
    $formatTweetTime = static function (string $value): string {
        try {
            $timezone = new \DateTimeZone((string)\App\Core\Config::get('app.timezone', 'Asia/Shanghai'));
            return (new \DateTimeImmutable($value))->setTimezone($timezone)->format('H:i · Y-m-d');
        } catch (\Throwable) {
            return \App\Core\Helper::formatDate($value, 'H:i · Y-m-d');
        }
    };
    $linkifyTweetLine = static function (string $text): string {
        $parts = preg_split("{(https?://[A-Za-z0-9._~:/?#\\[\\]@!$&'()*+,;=%-]+)}u", $text, -1, PREG_SPLIT_DELIM_CAPTURE);
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
        $html = '';
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $html .= '<span class="tweet-line">' . $linkifyTweetLine($line) . '</span>';
        }
        return $html;
    };
@endphp
@if($tweet)
    <div class="tweet-card ember-tweet-card">
        <div class="tweet-card-head">
            <div class="tweet-author">
                @if($avatar !== '')
                    <img class="tweet-avatar" src="{{ $avatar }}" alt="{{ $authorName ?: $handle ?: 'X' }}" loading="lazy" width="38" height="38">
                @else
                    <span class="tweet-avatar tweet-avatar-fallback">{{ mb_substr($authorName ?: $handle ?: 'X', 0, 1) }}</span>
                @endif
                <span class="tweet-author-text">
                    <strong>
                        {{ $authorName ?: ($handle !== '' ? '@'.$handle : 'X 用户') }}
                        @if($isVerified)
                            <i class="fa-solid fa-circle-check tweet-verified" aria-label="认证账号"></i>
                        @endif
                    </strong>
                    @if($handle !== '')<em>@{{ $handle }}</em>@endif
                </span>
            </div>
            @if($tweetUrl !== '')
                <a class="tweet-x-link" href="{{ $tweetUrl }}" target="_blank" rel="noopener noreferrer" aria-label="打开 X 原帖">
                    <i class="fa-brands fa-x-twitter"></i>
                </a>
            @endif
        </div>

        @if($body !== '')
            <div class="tweet-body">{!! $linkifyTweetBody($body) !!}</div>
        @elseif($tweetUrl !== '')
            <a class="tweet-body tweet-body-link" href="{{ $tweetUrl }}" target="_blank" rel="noopener noreferrer">
                <i class="fa-brands fa-x-twitter"></i>
                <span>查看 X 原帖</span>
            </a>
        @endif

        @if(!empty($images))
            <div class="tweet-media tweet-media-count-{{ min(count($images), 4) }}">
                @foreach(array_slice($images, 0, 4) as $idx => $img)
                    <img src="{{ trim($img) }}" alt="" loading="lazy">
                @endforeach
            </div>
        @endif

        <div class="tweet-footer">
            @if($postedAt !== '')
                <time datetime="{{ $postedAt }}">{{ $formatTweetTime($postedAt) }}</time>
            @else
                <time datetime="{{ $tweet->publishedAt() }}">{{ $formatTweetTime($tweet->publishedAt()) }}</time>
            @endif
            <span class="tweet-footer-spacer"></span>
            @if($tweetUrl !== '')
                @if($tweetShowReplies)
                    <a href="{{ $tweetUrl }}" target="_blank" rel="noopener noreferrer" class="tweet-action" aria-label="在 X 打开评论">
                        <i class="fa-regular fa-comment"></i><span>{{ $repliesCount }}</span>
                    </a>
                @endif
                <a href="{{ $tweetUrl }}" target="_blank" rel="noopener noreferrer" class="tweet-action" aria-label="在 X 打开并转发">
                    <i class="fa-solid fa-retweet"></i><span>{{ $repostsCount }}</span>
                </a>
                @if($likesCount > 0)
                    <a href="{{ $tweetUrl }}" target="_blank" rel="noopener noreferrer" class="tweet-action tweet-like" aria-label="在 X 打开并点赞">
                        <i class="fa-solid fa-heart"></i><span>{{ $likesCount }}</span>
                    </a>
                @endif
                @if(!$tweetHideViews)
                    <span class="tweet-action" aria-label="浏览数">
                        <i class="fa-regular fa-eye"></i><span>{{ $viewsCount }}</span>
                    </span>
                @endif
            @endif
        </div>
    </div>
@endif
