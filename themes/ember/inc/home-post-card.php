@php
    $category = $item->getCategory();
    $likesCount = (int)($item->likes_count ?? 0);
    $commentsCount = (int)($item->comments_count ?? 0);
    $cover = $item->displayCover();
@endphp
<article class="home-card home-card--post post-card has-cover">
    <a class="home-card-media home-post-cover" href="{{ $item->getUrl() }}" aria-label="{{ $item->title }}">
        <img src="{{ $cover }}" alt="{{ $item->title }}" loading="lazy" decoding="async">
    </a>
    @if($category)
        <a href="{{ \App\Core\Helper::categoryUrl((string)$category->slug) }}" class="home-post-category">
            <i class="{{ $category->iconClass() }}" aria-hidden="true"></i>
            <span>{{ $category->name }}</span>
        </a>
    @endif
    <div class="home-card-body home-post-main">
        <div class="home-post-title-row">
            <h2 class="home-post-title">
                <a href="{{ $item->getUrl() }}">{{ $item->title }}</a>
            </h2>
        </div>
        <div class="home-post-content-row">
            <p class="home-post-excerpt">{{ $item->summaryOrContent(180) }}</p>
        </div>
    </div>
    <footer class="home-card-footer home-card-meta-bar home-post-footer">
        <div class="home-card-meta home-post-meta">
            <span class="home-post-time">{!! \App\Core\Helper::timeTag($item->published_at) !!}</span>
        </div>
        <div class="home-card-actions home-post-actions">
            <button type="button" class="home-action post-like-btn" data-id="{{ $item->id }}" aria-label="点赞">
                <i class="fa-regular fa-thumbs-up" aria-hidden="true"></i><span class="like-count">{{ $likesCount }}</span>
            </button>
            <span class="home-action post-comment-count" aria-label="评论数">
                <i class="fa-regular fa-comment" aria-hidden="true"></i><span>{{ $commentsCount }}</span>
            </span>
        </div>
    </footer>
</article>
