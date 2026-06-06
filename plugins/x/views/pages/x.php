@extends('layouts.front')

@section('content')
    <section class="x-page x-bookmarks-page">
        <header class="x-page-head x-bookmarks-head">
            <div>
                <h2 class="section-title">Xmarks</h2>
                <p>{{ (int)$total }} 条 X 书签</p>
            </div>
        </header>

        @if(empty($list))
            <p class="empty">还没有同步 X 书签</p>
        @else
            <div class="x-masonry js-list-items">
                @foreach($list as $tweet)
                    @php
                        $tweetShowReplies = true;
                        $tweetHideViews = true;
                    @endphp
                    <article class="x-masonry-item" id="xmark-{{ $tweet->id }}">
                        @include('partials.tweet-card')
                    </article>
                @endforeach
            </div>
        @endif

        {!! $paginator ?? '' !!}
    </section>
@endsection
