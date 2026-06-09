@extends('layouts.front')

@section('content')
    <section class="category-page">
        <div class="category-shell">
            <header class="category-hero cat-color-{{ $category->colorIndex() }}">
                <div class="category-hero-top">
                    <span class="category-hero-kicker">Category</span>
                    <div class="category-hero-meta">
                        <span class="category-hero-stat"><i class="fa-regular fa-file-lines"></i> {{ (int)$articleStats['article_count'] }} 篇文章</span>
                        <span class="category-hero-stat"><i class="fa-regular fa-eye"></i> {{ number_format((int)($articleStats['views'] ?? 0)) }} 阅读</span>
                        <span class="category-hero-stat"><i class="fa-solid fa-keyboard"></i> {{ number_format((int)($articleStats['words'] ?? 0)) }} 字</span>
                        <span class="category-hero-stat"><i class="fa-regular fa-comments"></i> {{ number_format((int)($articleStats['comments_count'] ?? 0)) }} 评论</span>
                        <span class="category-hero-stat"><i class="fa-regular fa-heart"></i> {{ number_format((int)($articleStats['likes_count'] ?? 0)) }} 点赞</span>
                    </div>
                </div>
                <div class="category-hero-headline">
                    <div class="category-hero-ico"><i class="{{ $category->iconClass() }}"></i></div>
                    <h1 class="category-hero-name">{{ $category->name }}</h1>
                    @if($category->description)
                        <p class="category-hero-desc">{{ $category->description }}</p>
                    @endif
                </div>
            </header>

            <div class="js-list-items home-list home-post-list category-post-list" data-category-feed-list>
                @if(empty($posts))
                    <p class="empty category-empty">该分类下还没有文章</p>
                @else
                    @php $offset = 0; @endphp
                    @include('partials.category-post-items')
                @endif
            </div>
            @if(!empty($categoryHasMore))
                <div class="home-feed-more category-feed-more"
                     data-home-feed-more
                     data-list-selector="[data-category-feed-list]"
                     data-offset="{{ count($posts ?? []) }}"
                     data-limit="10"
                     data-url="/category/{{ $category->slug }}/feed">
                    <button type="button" class="home-feed-more-btn">
                        <span>加载更多</span>
                    </button>
                    <div class="home-feed-more-loading" hidden>
                        <span class="load-more-spinner"></span>
                    </div>
                    <div class="home-feed-more-end" hidden>
                        <i class="fa-regular fa-circle-check"></i>
                        <span>没有更多内容</span>
                    </div>
                </div>
            @endif
        </div>
    </section>
@endsection
