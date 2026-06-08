@php
    $likesCount = (int)($item->likes_count ?? 0);
    $commentsCount = (int)($item->comments_count ?? 0);
    $viewCount = (int)($item->views ?? 0);
    $publishedText = \App\Core\Helper::formatDate($item->published_at, 'Y-m-d H:i');
    $articleNumber = (int)($item->article_number ?? 0);
    $displayNumber = $articleNumber > 0 ? $articleNumber : (($index ?? 0) + 1);
@endphp
<article class="home-card home-card--post post-card category-post-card">
    <div class="home-card-body home-post-main">
        <div class="home-post-title-row category-post-title-row">
            <h2 class="home-post-title">
                <a href="{{ $item->getUrl() }}">{{ $item->title }}</a>
            </h2>
        </div>
        <p class="category-post-excerpt">{{ $item->summaryOrContent(150) }}</p>
    </div>
    <footer class="category-post-meta-row" aria-label="文章信息">
        <time class="category-post-time" datetime="{{ date('c', strtotime((string)$item->published_at)) }}">
            {{ $publishedText }}
        </time>
        <div class="category-post-meta-stats">
            <span class="home-post-stats"><i class="fa-regular fa-eye"></i>{{ number_format($viewCount) }}</span>
            <span class="home-post-stats"><i class="fa-regular fa-comment"></i>{{ $commentsCount }}</span>
            <button type="button" class="home-action post-like-btn" data-id="{{ $item->id }}" aria-label="点赞">
                <i class="fa-regular fa-thumbs-up"></i><span class="like-count">{{ $likesCount }}</span>
            </button>
        </div>
    </footer>
</article>
