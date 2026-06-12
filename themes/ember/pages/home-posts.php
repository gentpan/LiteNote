@extends('layouts.front')

@section('content')
    <section class="post-list">
        <header class="posts-hero">
            <div class="posts-hero-head">
                <div class="posts-hero-kicker-row">
                    <span class="posts-hero-kicker">POSTS<span class="posts-hero-mobile-label"> · 文章</span></span>
                    <p class="posts-hero-sub">
                        共 {{ number_format((int)($heatmap['articles'] ?? $total ?? 0)) }} 篇 · 记录 {{ number_format((int)($heatmap['spanDays'] ?? 0)) }} 天
                    </p>
                </div>
                <h2 class="posts-hero-title">文章</h2>
            </div>
            @if(!empty($heatmap['days']))
                <div class="posts-heatmap site-heatmap">
                    <div class="posts-heatmap-scroll site-heatmap-scroll">
                        <div class="posts-heatmap-inner site-heatmap-inner" style="--weeks: {{ $heatmap['weeks'] ?? 53 }}">
                            <div class="posts-heatmap-months site-heatmap-months">
                                @foreach(($heatmap['months'] ?? []) as $month)
                                    <span style="grid-column: {{ $month['week'] }}">{{ $month['label'] }}</span>
                                @endforeach
                            </div>
                            <div class="posts-heatmap-cells site-heatmap-cells" aria-label="近一年文章写作热力图">
                                @foreach(($heatmap['days'] ?? []) as $day)
                                    @php
                                        $articleCount = (int)($day['articles'] ?? 0);
                                        $heatTitle = $articleCount > 0 ? $articleCount . ' 篇文章' : '没有文章';
                                    @endphp
                                    <span class="posts-heatmap-cell site-heatmap-cell level-{{ $day['level'] }} {{ !empty($day['muted']) ? 'is-muted' : '' }}"
                                          title="{{ $day['date'] }}：{{ $heatTitle }}"></span>
                                @endforeach
                            </div>
                            <div class="posts-heatmap-legend site-heatmap-legend">
                                <span>少</span>
                                <i class="level-0"></i>
                                <i class="level-1"></i>
                                <i class="level-2"></i>
                                <i class="level-3"></i>
                                <i class="level-4"></i>
                                <span>多</span>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </header>
        @if(empty($posts))
            <p class="empty">还没有文章，<a href="/admin">去后台发布</a></p>
        @endif
        @php
            $isFirstPage = (int)($page ?? 1) === 1;
            $featuredPosts = $isFirstPage ? array_slice($posts, 0, 3) : [];
            $compactPosts = $isFirstPage ? array_slice($posts, 3) : $posts;
        @endphp
        <div class="js-list-items">
        @if(!empty($featuredPosts))
            <div class="post-feature-grid">
                @foreach($featuredPosts as $index => $post)
                    @php
                        $postNumber = (int)($post->article_number ?? 0);
                        if ($postNumber <= 0) {
                            $postNumber = (int)($total ?? count($posts)) - (((int)($page ?? 1) - 1) * (int)($perPage ?? count($posts))) - (int)$index;
                        }
                        $postNumberText = str_pad((string) max(1, $postNumber), 2, '0', STR_PAD_LEFT);
                        $category = $post->getCategory();
                        $cover = $post->displayCover();
                    @endphp
                    <article class="post-feature-card">
                        <a class="post-feature-cover" href="{{ $post->getUrl() }}" aria-label="{{ $post->title }}">
                            <img src="{{ $cover }}" alt="{{ $post->title }}" loading="lazy" decoding="async">
                        </a>
                        <div class="post-feature-body">
                            <p class="post-feature-meta">
                                <span class="post-number">{{ $postNumberText }}</span>
                                @if($category)<a href="/category/{{ $category->slug }}">{{ $category->name }}</a>@endif
                                <span class="post-feature-time">{!! \App\Core\Helper::timeTag($post->published_at) !!}</span>
                            </p>
                            <h3 class="post-feature-title">
                                @if($post->is_top)<span class="badge badge-top">置顶</span>@endif
                                <a href="{{ $post->getUrl() }}">{{ $post->title }}</a>
                            </h3>
                            <p class="post-feature-excerpt">{{ $post->summaryOrContent(96) }}</p>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
        @foreach($compactPosts as $compactIndex => $post)
            @php
                $index = (int)$compactIndex + count($featuredPosts);
                $postNumber = (int)($post->article_number ?? 0);
                if ($postNumber <= 0) {
                    $postNumber = (int)($total ?? count($posts)) - (((int)($page ?? 1) - 1) * (int)($perPage ?? count($posts))) - (int)$index;
                }
                $postNumberText = str_pad((string) max(1, $postNumber), 2, '0', STR_PAD_LEFT);
                $category = $post->getCategory();
            @endphp
            <article class="post-compact-row">
                <a class="post-compact-link" href="{{ $post->getUrl() }}">
                    <span class="post-compact-number">{{ $postNumberText }}</span>
                    <span class="post-compact-title">
                        @if($post->is_top)<span class="badge badge-top">置顶</span>@endif
                        {{ $post->title }}
                    </span>
                    <span class="post-compact-side" aria-hidden="true">
                        <span class="post-compact-date">{!! \App\Core\Helper::timeTag($post->published_at) !!}</span>
                    </span>
                </a>
            </article>
        @endforeach
        </div>
        {!! $paginator ?? '' !!}
    </section>
@endsection
