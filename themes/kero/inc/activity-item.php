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
@endphp
<article class="activity-item activity-type-{{ $type }}" id="activity-{{ $activity->id }}">
    <time class="activity-item-time" datetime="{{ $activity->happened_at }}">
        <strong>{{ $dayLabel }}</strong>
        <small>{{ $dateText }}</small>
    </time>
    <span class="activity-item-line" aria-hidden="true"></span>
    <span class="activity-item-icon">@if($source === 'x_bookmarks')<i class="fa-solid fa-bookmark" aria-hidden="true"></i>
    @elseif($type === 'music')<i class="fa-solid fa-music" aria-hidden="true"></i>
    @elseif($type === 'game')<i class="fa-solid fa-gamepad" aria-hidden="true"></i>
    @else <i class="fa-solid fa-chart-simple" aria-hidden="true"></i>
    @endif</span>
    <div class="activity-item-body">
        <div class="activity-item-meta">
            <span>{{ $timeText }}</span>
            <span>{{ $actionLabel }}</span>
            <span>{{ $typeLabel }}</span>
            @if($source !== '')<span>{{ $source }}</span>@endif
        </div>
        <div class="activity-item-title">
            @if($activity->url)
                <a href="{{ $activity->url }}" target="{{ str_starts_with((string)$activity->url, '/') ? '_self' : '_blank' }}" rel="nofollow noopener">{{ $activity->title }}</a>
            @else
                <span>{{ $activity->title }}</span>
            @endif
            @if($ratingText !== '')<em class="activity-rating">{{ $ratingText }}</em>@endif
        </div>
        @if(trim((string)$activity->content) !== '')
            <div class="activity-item-content">{{ $activity->content }}</div>
        @endif
    </div>
</article>
