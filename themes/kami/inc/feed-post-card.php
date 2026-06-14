@php $category = $item->getCategory(); @endphp
<article class="feed-card feed-post-card kami-feed-item kami-post-item {{ $item->cover ? 'has-cover' : '' }}">
    <div class="kami-feed-marker" aria-hidden="true">
        <span>文</span>
    </div>
    <div class="kami-feed-body">
        <div class="feed-post-meta kami-feed-meta">
            <span>文章</span>
            @if($category)
                <span>·</span>
                <a href="{{ \App\Core\Helper::categoryUrl((string)$category->slug) }}">{{ $category->name }}</a>
            @endif
            <span>·</span>
            <span>{!! \App\Core\Helper::timeTag($item->published_at) !!}</span>
        </div>
        <h2 class="feed-post-title kami-feed-title">
            <a href="{{ $item->getUrl() }}">{{ $item->title }}</a>
        </h2>
        <p class="feed-post-excerpt kami-feed-excerpt">{{ $item->summaryOrContent(180) }}</p>
        @if($item->cover)
            <a class="feed-post-cover kami-feed-cover" href="{{ $item->getUrl() }}" aria-label="{{ $item->title }}">
                <img src="{{ $item->cover }}" alt="{{ $item->title }}" loading="lazy" decoding="async">
            </a>
        @endif
    </div>
</article>
