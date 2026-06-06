@extends('layouts.front')

@section('content')
    <section class="home-list">
        @if(empty($feedItems))
            <p class="empty">还没有内容</p>
        @endif

        @foreach($feedItems as $feed)
            @php $item = $feed['item']; @endphp
            @if($feed['type'] === 'post')
                @include('partials.home-post-card')
            @elseif($feed['type'] === 'x_tweet')
                @php $tweet = $item; $tweetLocalActions = true; $tweetShowReplies = false; $tweetHideViews = true; @endphp
                @include('partials.tweet-card')
            @else
                @include('partials.home-talk-card')
            @endif
        @endforeach
    </section>
@endsection
