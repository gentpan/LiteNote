@extends('layouts.front')

@section('content')
    <section class="post-list kero-panel">
        <header class="kero-panel-head">
            <p class="kero-kicker">session · posts</p>
            <h1 class="section-title">文章</h1>
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
                    @endphp
                    <article class="post-feature-card">
                        <a class="post-feature-cover" href="{{ $post->getUrl() }}" aria-label="{{ $post->title }}">
                            @if($post->cover)
                                <img src="{{ $post->cover }}" alt="{{ $post->title }}" loading="lazy" decoding="async">
                            @else
                                <span class="post-feature-placeholder">
                                    <span>{{ $postNumberText }}</span>
                                    @if($category)<small>{{ $category->name }}</small>@endif
                                </span>
                            @endif
                        </a>
                        <div class="post-feature-body">
                            <p class="post-feature-meta">
                                <span class="post-number">{{ $postNumberText }}</span>
                                @if($category)<a href="{{ \App\Core\Helper::categoryUrl((string)$category->slug) }}">{{ $category->name }}</a>@endif
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
