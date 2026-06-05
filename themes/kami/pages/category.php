@extends('layouts.front')

@section('content')
    <header class="category-hero cat-color-{{ $category->colorIndex() }}">
        <div class="category-hero-ico"><i class="{{ $category->iconClass() }}"></i></div>
        <div class="category-hero-body">
            <h1 class="category-hero-name">{{ $category->name }}</h1>
            @if($category->description)
                <p class="category-hero-desc">{{ $category->description }}</p>
            @endif
            <div class="category-hero-meta"><i class="fa-regular fa-file-lines"></i> 共 {{ $total }} 篇文章</div>
        </div>
    </header>
    @if(empty($posts))
        <p class="empty">该分类下还没有文章</p>
    @endif
    <div class="js-list-items">
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
    </div>
    {!! $paginator ?? '' !!}
@endsection
