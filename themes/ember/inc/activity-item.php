@php
    /** @var \App\Models\Activity $activity */
    $meta = $activity->metadata();
    $type = (string)($activity->type ?? 'manual');
    $icon = trim((string)($activity->icon ?? '')) ?: \App\Services\ActivityService::typeIcon($type);
    $ts = strtotime((string)$activity->happened_at) ?: time();
    $days = (int)floor((strtotime(date('Y-m-d')) - strtotime(date('Y-m-d', $ts))) / 86400);
    $dayLabel = $days <= 0 ? '今天' : ($days === 1 ? '昨天' : $days . '天前');
    $dateText = \App\Core\Helper::formatDate((string)$activity->happened_at, 'm.d');
    $timeText = \App\Core\Helper::formatDate((string)$activity->happened_at, 'H:i');
    $typeLabel = \App\Services\ActivityService::typeLabel($type);
    $actionLabel = \App\Services\ActivityService::actionLabel((string)($activity->action ?? 'manual'));
    $source = trim((string)($activity->source ?? ''));
    $rating = isset($meta['rating']) ? (float)$meta['rating'] : null;
    $ratingText = '';
    if ($rating !== null) {
        $full = max(0, min(5, (int)round($rating)));
        $ratingText = str_repeat('★', $full) . str_repeat('☆', 5 - $full);
    }
    // X 书签:精简为「收藏了 谁 的帖子」+ 去链接开头摘要,跳 /xmarks(不外链 x.com、不显示图片链接)
    $isXmark = $source === 'x_bookmarks';
    $xmarkTitle = $xmarkExcerpt = $xmarkUrl = '';
    if ($isXmark) {
        $tw = is_array($meta['tweet'] ?? null) ? $meta['tweet'] : [];
        $handle = ltrim(trim((string)($tw['author_handle'] ?? '')), '@');
        $authorName = trim((string)($tw['author_name'] ?? ''));
        $who = $authorName !== '' ? $authorName : ($handle !== '' ? '@' . $handle : 'X 用户');
        $xmarkTitle = '收藏了 ' . $who . ' 的帖子';
        $raw = trim((string)($tw['text'] ?? $activity->content ?? ''));
        $raw = preg_replace('~https?://\S+~u', '', $raw) ?? $raw;
        $raw = trim(preg_replace('~\s+~u', ' ', $raw) ?? $raw);
        $xmarkExcerpt = mb_strimwidth($raw, 0, 46, '…', 'UTF-8');
        $xmarkUrl = '/xmarks#xmark-activity-' . (int)$activity->id;
    }
@endphp
<article class="activity-item activity-type-{{ $type }}" id="activity-{{ $activity->id }}">
    <time class="activity-item-time" datetime="{{ $activity->happened_at }}">
        <strong>{{ $dayLabel }}</strong>
        <small>{{ $dateText }}</small>
    </time>
    <span class="activity-item-line" aria-hidden="true"></span>
    <span class="activity-item-icon"><i class="{{ $icon }}"></i></span>
    <div class="activity-item-body">
        <div class="activity-item-meta">
            <span>{{ $timeText }}</span>
            <span>{{ $actionLabel }}</span>
            <span>{{ $typeLabel }}</span>
            @if($source !== '')<span>{{ $source }}</span>@endif
        </div>
        <div class="activity-item-title">
            @if($isXmark)
                <a href="{{ $xmarkUrl }}">{{ $xmarkTitle }}</a>
            @elseif($activity->url)
                <a href="{{ $activity->url }}" target="{{ str_starts_with((string)$activity->url, '/') ? '_self' : '_blank' }}" rel="nofollow noopener">{{ $activity->title }}</a>
            @else
                <span>{{ $activity->title }}</span>
            @endif
            @if($ratingText !== '')<em class="activity-rating">{{ $ratingText }}</em>@endif
        </div>
        @if($isXmark)
            @if($xmarkExcerpt !== '')<div class="activity-item-content activity-item-excerpt">{{ $xmarkExcerpt }}</div>@endif
        @elseif(trim((string)$activity->content) !== '')
            <div class="activity-item-content">{{ $activity->content }}</div>
        @endif
    </div>
</article>
