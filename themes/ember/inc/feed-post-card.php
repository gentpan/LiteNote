@php $category = $item->getCategory(); @endphp
<article class="feed-card feed-post-card {{ $item->cover ? 'has-cover' : '' }}">
    @if($item->cover)
        <a class="feed-post-cover" href="{{ $item->getUrl() }}" aria-label="{{ $item->title }}">
            <img src="{{ $item->cover }}" alt="{{ $item->title }}" loading="lazy" decoding="async">
        </a>
    @endif
    <div class="feed-post-main">
        <div class="feed-post-meta">
            <div class="feed-post-type">
                <span>文章</span>
                @if($category)
                    <span class="feed-post-dot">·</span>
                    <a href="/category/{{ $category->slug }}">{{ $category->name }}</a>
                @endif
                <span class="feed-post-dot">·</span>
                <span class="feed-post-time">{!! \App\Core\Helper::timeTag($item->published_at) !!}</span>
            </div>
        </div>
        <h2 class="feed-post-title">
            <a href="{{ $item->getUrl() }}">{{ $item->title }}</a>
        </h2>
        <p class="feed-post-excerpt">{{ $item->summaryOrContent(180) }}</p>
    </div>
</article>
