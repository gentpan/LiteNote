@extends('layouts.front')

@section('content')
    <h2 class="section-title">搜索</h2>
    <form action="/search" method="get" class="search-form">
        <input type="search" name="q" value="{{ $keyword }}" placeholder="搜索文章、页面、滔客、音乐、X">
        <button type="submit">搜索</button>
    </form>
    @if($keyword !== '')
        <p>关键字: <strong>{{ $keyword }}</strong>，共 {{ $total }} 条结果</p>
    @endif
    <div class="js-list-items">
    @foreach(($results ?? []) as $item)
        <article class="post-card">
            <h3 class="post-title">
                <a href="{{ $item['url'] ?? '#' }}">{!! \App\Core\Helper::highlight((string)($item['title'] ?? ''), $keyword) !!}</a>
            </h3>
            <p class="post-excerpt">{!! \App\Core\Helper::highlight((string)($item['excerpt'] ?? ''), $keyword) !!}</p>
        </article>
    @endforeach
    </div>
    @if($keyword !== '' && empty($results))
        <p class="empty">没有找到相关内容。</p>
    @endif
    {!! $paginator ?? '' !!}
@endsection
