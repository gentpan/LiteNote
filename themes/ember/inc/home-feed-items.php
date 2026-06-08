@foreach($feedItems as $feed)
    @php $item = $feed['item']; @endphp
    @if($feed['type'] === 'post')
        @include('partials.home-post-card')
    @elseif($feed['type'] === 'x_tweet')
        @php $tweet = $item; $tweetLocalActions = true; $tweetShowReplies = false; $tweetHideViews = true; @endphp
        @include('partials.x-card')
    @else
        @include('partials.home-talk-card')
    @endif
@endforeach
