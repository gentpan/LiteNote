@extends('layouts.front')

@section('content')
    <section class="x-page x-bookmarks-page">
        <header class="x-page-head x-bookmarks-head">
            <div class="x-bookmarks-head-text">
                <h2 class="section-title">Xmarks</h2>
                <p>我在 X 上收藏的内容 · 共 {{ (int)$total }} 条</p>
            </div>
            <span class="x-bookmarks-head-badge"><i class="fa-brands fa-x-twitter" aria-hidden="true"></i> {{ (int)$total }}</span>
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
