@extends('layouts.front')

@section('content')
    <section class="home-list js-home-feed-list" data-home-feed-list>
        @if(empty($feedItems))
            <p class="empty">还没有内容</p>
        @endif

        @include('partials.home-feed-items')
    </section>

    @if(!empty($homeFeedHasMore))
        <div class="home-feed-more" data-home-feed-more data-offset="{{ count($feedItems ?? []) }}" data-limit="10" data-url="/home/feed">
            <button type="button" class="home-feed-more-btn">
                <span>加载更多</span>
            </button>
            <div class="home-feed-more-loading" hidden>
                <span class="load-more-spinner"></span>
            </div>
            <div class="home-feed-more-end" hidden>
                <i class="fa-regular fa-circle-check"></i>
                <span>没有更多内容</span>
            </div>
        </div>
    @endif
@endsection
