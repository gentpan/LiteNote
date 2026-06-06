@php $category = $item->getCategory(); @endphp
<article class="home-card home-card--post home-post-card {{ $item->cover ? 'has-cover' : '' }}">
    @if($item->cover)
        <a class="home-card-media home-post-cover" href="{{ $item->getUrl() }}" aria-label="{{ $item->title }}">
            <img src="{{ $item->cover }}" alt="{{ $item->title }}" loading="lazy" decoding="async">
        </a>
    @endif
    <div class="home-card-body home-post-main">
        <div class="home-card-header home-post-meta">
            <div class="home-post-type">
                <span>文章</span>
                @if($category)
                    <span class="home-post-dot">·</span>
                    <a href="/category/{{ $category->slug }}">{{ $category->name }}</a>
                @endif
                <span class="home-post-dot">·</span>
                <span class="home-post-time">{!! \App\Core\Helper::timeTag($item->published_at) !!}</span>
            </div>
        </div>
        <h2 class="home-post-title">
            <a href="{{ $item->getUrl() }}">{{ $item->title }}</a>
        </h2>
        <p class="home-post-excerpt">{{ $item->summaryOrContent(180) }}</p>
    </div>
</article>
