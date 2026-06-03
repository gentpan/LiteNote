@extends('layouts.front')

@section('content')
    <section class="post-list">
        <h2 class="section-title">最新文章</h2>
        @if(empty($posts))
            <p class="empty">还没有文章，<a href="/admin">去后台发布</a></p>
        @endif
        @foreach($posts as $post)
            <article class="post-card">
                <h3 class="post-title">
                    @if($post->is_top)<span class="badge badge-top">置顶</span>@endif
                    <a href="/post/{{ $post->slug }}.html">{{ $post->title }}</a>
                </h3>
                <p class="post-meta">
                    <span><i class="fa-regular fa-calendar"></i> {!! \App\Core\Helper::timeTag($post->published_at) !!}</span>
                    @if($post->getCategory())
                        <span><i class="fa-solid fa-folder"></i> <a href="/category/{{ $post->getCategory()->slug }}">{{ $post->getCategory()->name }}</a></span>
                    @endif
                    <span><i class="fa-regular fa-eye"></i> {{ $post->views }} 浏览</span>
                    <span><i class="fa-regular fa-comments"></i> {{ $post->comments_count }} 评论</span>
                </p>
                <p class="post-excerpt">{{ $post->summaryOrContent(200) }}</p>
            </article>
        @endforeach
        {!! $paginator ?? '' !!}
    </section>
@endsection
