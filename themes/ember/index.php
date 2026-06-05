@extends('layouts.front')

@section('content')
    <section class="feed-list">
        @if(empty($feedItems))
            <p class="empty">还没有内容</p>
        @endif

        @foreach($feedItems as $feed)
            @php $item = $feed['item']; @endphp
            @if($feed['type'] === 'post')
                @include('partials.feed-post-card')
            @else
                @include('partials.feed-talk-card')
            @endif
        @endforeach
    </section>
@endsection
