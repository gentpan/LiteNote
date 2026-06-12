@extends('layouts.front')

@section('content')
    <section class="search-page">
        <h2 class="section-title">搜索</h2>
        <form action="/search" method="get" class="search-form">
            <input type="search" name="q" value="{{ $keyword }}" placeholder="搜索文章、页面、滔客、音乐、X" autofocus>
            <button type="submit"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i><span>搜索</span></button>
        </form>
        @if($keyword !== '')
            <p class="search-summary">关键字: <strong>{{ $keyword }}</strong>，共 {{ $total }} 条结果</p>
        @endif
        <div class="search-results js-list-items">
        @foreach(($results ?? []) as $item)
            <article class="search-result-card">
                <span class="search-result-type"><i class="fa-regular fa-file-lines" aria-hidden="true"></i>{{ $item['label'] ?? '' }}</span>
                <h3 class="post-title">
                    <a href="{{ $item['url'] ?? '#' }}">{!! \App\Core\Helper::highlight((string)($item['title'] ?? ''), $keyword) !!}</a>
                </h3>
                @if(!empty($item['excerpt']))
                    <p class="post-excerpt">{!! \App\Core\Helper::highlight((string)$item['excerpt'], $keyword) !!}</p>
                @endif
                @if(!empty($item['date']))
                    <div class="search-result-meta">{!! \App\Core\Helper::timeTag((string)$item['date']) !!}</div>
                @endif
            </article>
        @endforeach
        </div>
        @if($keyword !== '' && empty($results))
            <p class="empty">没有找到相关内容。</p>
        @endif
    </section>
    {!! $paginator ?? '' !!}
@endsection
