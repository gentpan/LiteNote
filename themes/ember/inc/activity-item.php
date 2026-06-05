@php
    /** @var \App\Models\Activity $activity */
    $meta = $activity->metadata();
    $type = (string)($activity->type ?? 'manual');
    $icon = trim((string)($activity->icon ?? '')) ?: \App\Services\ActivityService::typeIcon($type);
    $ts = strtotime((string)$activity->happened_at) ?: time();
    $days = (int)floor((strtotime(date('Y-m-d')) - strtotime(date('Y-m-d', $ts))) / 86400);
    $dayLabel = $days <= 0 ? '今天' : ($days === 1 ? '昨天' : $days . '天前');
    $rating = isset($meta['rating']) ? (float)$meta['rating'] : null;
    $ratingText = '';
    if ($rating !== null) {
        $full = max(0, min(5, (int)round($rating)));
        $ratingText = str_repeat('★', $full) . str_repeat('☆', 5 - $full);
    }
@endphp
<article class="activity-item activity-type-{{ $type }}" id="activity-{{ $activity->id }}">
    <time class="activity-item-time" datetime="{{ $activity->happened_at }}">{{ $dayLabel }}</time>
    <span class="activity-item-line" aria-hidden="true"></span>
    <span class="activity-item-icon"><i class="{{ $icon }}"></i></span>
    <div class="activity-item-body">
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
        <div class="activity-item-meta">
            <span>{{ \App\Core\Helper::formatDate((string)$activity->happened_at, 'H:i') }}</span>
            <span>{{ \App\Services\ActivityService::typeLabel($type) }}</span>
            <span>{{ $activity->source }}</span>
        </div>
    </div>
</article>
