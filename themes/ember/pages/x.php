@extends('layouts.front')

@section('content')
    <section class="x-page">
        <header class="x-page-head">
            <h2 class="section-title">𝕏</h2>
            <p>{{ (int)$total }} 条记录</p>
        </header>

        @if(empty($list))
            <p class="empty">还没有 X 记录</p>
        @else
            <div class="x-masonry js-list-items">
                @foreach($list as $tweet)
                    @php
                        $tweetShowReplies = true;
                        $tweetHideViews = true;
                    @endphp
                    <article class="x-masonry-item" id="x-{{ $tweet->id }}">
                        @include('partials.tweet-card')
                    </article>
                @endforeach
            </div>
        @endif

        {!! $paginator ?? '' !!}
    </section>
@endsection
