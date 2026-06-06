@extends('layouts.front')

@section('content')
    <h2 class="section-title">搜索</h2>
    <form action="/search" method="get" class="search-form">
        <input type="text" name="q" value="{{ $keyword }}" placeholder="输入关键字搜索文章">
        <button type="submit">搜索</button>
    </form>
    @if($keyword !== '')
        <p>关键字: <strong>{{ $keyword }}</strong>，共 {{ $total }} 条结果</p>
    @endif
    <div class="js-list-items">
    @foreach($posts as $post)
        <article class="post-card">
            <h3 class="post-title">
                <a href="{{ $post->getUrl() }}">{!! \App\Core\Helper::highlight($post->title, $keyword) !!}</a>
            </h3>
            <p class="post-excerpt">{!! \App\Core\Helper::highlight($post->summaryOrContent(200), $keyword) !!}</p>
        </article>
    @endforeach
    </div>
    {!! $paginator ?? '' !!}
@endsection
