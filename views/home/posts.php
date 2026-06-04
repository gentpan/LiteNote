@extends('layouts.front')

@section('content')
    <section class="post-list">
        <h2 class="section-title">文章</h2>
        @if(empty($posts))
            <p class="empty">还没有文章，<a href="/admin">去后台发布</a></p>
        @endif
        @php
            $featuredPosts = array_slice($posts, 0, 4);
            $compactPosts = array_slice($posts, 4);
        @endphp
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
                        <a class="post-feature-cover" href="/post/{{ $post->slug }}.html" aria-label="{{ $post->title }}">
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
                                @if($category)<a href="/category/{{ $category->slug }}">{{ $category->name }}</a>@endif
                                <span>{!! \App\Core\Helper::timeTag($post->published_at) !!}</span>
                            </p>
                            <h3 class="post-feature-title">
                                @if($post->is_top)<span class="badge badge-top">置顶</span>@endif
                                <a href="/post/{{ $post->slug }}.html">{{ $post->title }}</a>
                            </h3>
                            <p class="post-feature-excerpt">{{ $post->summaryOrContent(96) }}</p>
                            <p class="post-feature-stats">
                                <span><i class="fa-regular fa-eye"></i> {{ (int)$post->views }}</span>
                                <span><i class="fa-regular fa-comments"></i> {{ (int)$post->comments_count }}</span>
                            </p>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
        @foreach($compactPosts as $compactIndex => $post)
            @php
                $index = (int)$compactIndex + 4;
                $postNumber = (int)($post->article_number ?? 0);
                if ($postNumber <= 0) {
                    $postNumber = (int)($total ?? count($posts)) - (((int)($page ?? 1) - 1) * (int)($perPage ?? count($posts))) - (int)$index;
                }
                $postNumberText = str_pad((string) max(1, $postNumber), 2, '0', STR_PAD_LEFT);
                $category = $post->getCategory();
            @endphp
            <article class="post-compact-row">
                <a class="post-compact-link" href="/post/{{ $post->slug }}.html">
                    <span class="post-compact-number">{{ $postNumberText }}</span>
                    <span class="post-compact-title">
                        @if($post->is_top)<span class="badge badge-top">置顶</span>@endif
                        {{ $post->title }}
                    </span>
                    <span class="post-compact-side" aria-hidden="true">
                        <span class="post-compact-date">{!! \App\Core\Helper::timeTag($post->published_at) !!}</span>
                        <span class="post-compact-stats">
                            <span><i class="fa-regular fa-eye"></i> {{ (int)$post->views }}</span>
                            <span><i class="fa-regular fa-comments"></i> {{ (int)$post->comments_count }}</span>
                        </span>
                    </span>
                </a>
            </article>
        @endforeach
        {!! $paginator ?? '' !!}
    </section>
@endsection
