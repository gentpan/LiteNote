@extends('layouts.front')

@section('content')
    <section class="post-list">
        <h2 class="section-title">文章</h2>
        @if(empty($posts))
            <p class="empty">还没有文章，<a href="/admin">去后台发布</a></p>
        @endif
        @foreach($posts as $index => $post)
            @php
                $postNumber = (int)($post->article_number ?? 0);
                if ($postNumber <= 0) {
                    $postNumber = (int)($total ?? count($posts)) - (((int)($page ?? 1) - 1) * (int)($perPage ?? count($posts))) - (int)$index;
                }
                $postNumberText = str_pad((string) max(1, $postNumber), 2, '0', STR_PAD_LEFT);
                $category = $post->getCategory();
            @endphp
            <article class="post-card home-post-card">
                <div class="post-card-main">
                    <p class="post-meta">
                        <span class="post-number">{{ $postNumberText }}</span>
                        <span><i class="fa-regular fa-calendar"></i> {!! \App\Core\Helper::timeTag($post->published_at) !!}</span>
                        <span><i class="fa-regular fa-eye"></i> {{ $post->views }}</span>
                        <span><i class="fa-regular fa-comments"></i> {{ $post->comments_count }}</span>
                    </p>
                    <h3 class="post-title">
                        @if($post->is_top)<span class="badge badge-top">置顶</span>@endif
                        <a href="/post/{{ $post->slug }}.html">{{ $post->title }}</a>
                    </h3>
                    <p class="post-excerpt">{{ $post->summaryOrContent(120) }}</p>
                </div>
                @if($category)
                    <a class="post-card-icon" href="/category/{{ $category->slug }}" aria-label="{{ $category->name }}">
                        <i class="fa-solid fa-folder"></i>
                    </a>
                @else
                    <span class="post-card-icon" aria-hidden="true">
                        <i class="fa-solid fa-folder"></i>
                    </span>
                @endif
            </article>
        @endforeach
        {!! $paginator ?? '' !!}
    </section>
@endsection
