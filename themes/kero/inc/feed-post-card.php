@php $category = $item->getCategory(); @endphp
<article class="kero-row feed-card feed-post-card {{ $item->cover ? 'has-cover' : '' }}">
    <div class="kero-row-label">
        <span class="kero-row-kind">post</span>
        <span class="kero-row-time">{!! \App\Core\Helper::timeTag($item->published_at) !!}</span>
    </div>
    <div class="kero-row-body">
        <h2 class="kero-row-title">
            <a href="{{ $item->getUrl() }}">{{ $item->title }}</a>
        </h2>
        <p class="kero-row-desc">{{ $item->summaryOrContent(140) }}</p>
        @if($category)
            <p class="kero-row-meta">
                <a href="{{ \App\Core\Helper::categoryUrl((string)$category->slug) }}">{{ $category->name }}</a>
            </p>
        @endif
        @if($item->cover)
            <a class="kero-row-cover" href="{{ $item->getUrl() }}" aria-label="{{ $item->title }}">
                <img src="{{ $item->cover }}" alt="{{ $item->title }}" loading="lazy" decoding="async">
            </a>
        @endif
    </div>
</article>
