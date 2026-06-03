@extends('layouts.front')

@section('content')
    <h2 class="section-title">分类: {{ $category->name }}</h2>
    <p class="section-desc">{{ $category->description }}</p>
    @if(empty($posts))
        <p class="empty">该分类下还没有文章</p>
    @endif
    @foreach($posts as $post)
        <article class="post-card">
            <h3 class="post-title">
                <a href="/post/{{ $post->slug }}.html">{{ $post->title }}</a>
            </h3>
            <p class="post-meta">
                <span><i class="fa-regular fa-calendar"></i> {!! \App\Core\Helper::timeTag($post->published_at) !!}</span>
                <span><i class="fa-regular fa-eye"></i> {{ $post->views }} 浏览</span>
            </p>
            <p class="post-excerpt">{{ $post->summaryOrContent(200) }}</p>
        </article>
    @endforeach
    {!! $paginator ?? '' !!}
@endsection
