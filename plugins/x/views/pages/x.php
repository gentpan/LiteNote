@extends('layouts.front')

@section('content')
    @php
        $xmarksLastUpdatedText = !empty($lastUpdatedAt) ? \App\Core\Helper::formatDate((string)$lastUpdatedAt, 'm/d H:i') : '';
        $xmarksLastUpdatedFull = !empty($lastUpdatedAt) ? \App\Core\Helper::formatDate((string)$lastUpdatedAt, 'Y-m-d H:i') : '';
    @endphp
    <section class="x-page x-bookmarks-page">
        <header class="x-page-head x-bookmarks-head">
            <div class="x-bookmarks-head-text">
                <h2 class="section-title">Xmarks</h2>
                <p>我在 X 上收藏的内容</p>
            </div>
            <div class="x-bookmarks-head-stats">
                <span class="x-bookmarks-head-stat">
                    <i class="fa-brands fa-x-twitter" aria-hidden="true"></i>
                    <strong>{{ (int)$total }}</strong>
                    <small>收藏</small>
                </span>
                @if($xmarksLastUpdatedText !== '')
                    <span class="x-bookmarks-head-stat" title="{{ $xmarksLastUpdatedFull }}">
                        <i class="fa-regular fa-clock" aria-hidden="true"></i>
                        <strong>{{ $xmarksLastUpdatedText }}</strong>
                        <small>更新</small>
                    </span>
                @endif
            </div>
        </header>

        @if(empty($list))
            <p class="empty">还没有同步 X 书签</p>
        @else
            <div class="x-masonry js-list-items">
                @foreach($list as $tweet)
                    @php
                        $tweetBookmark = true;
                    @endphp
                    <article class="x-masonry-item" id="xmark-{{ $tweet->id }}">
                        @include('partials.x-card')
                    </article>
                @endforeach
            </div>
        @endif

        {!! $paginator ?? '' !!}
    </section>
@endsection
