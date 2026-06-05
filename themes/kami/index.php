@extends('layouts.front')

@section('content')
    <section class="kami-stream feed-list" aria-label="首页动态">
        <header class="kami-page-head">
            <p class="kami-page-kicker">LiteNote · Kami</p>
            <h1>纸上近况</h1>
            <p>文章、滔客、音乐与 X 记录收束在同一张温暖纸面里。</p>
        </header>

        @include('partials.activity-summary-card')

        @if(empty($feedItems))
            <p class="empty">还没有内容</p>
        @endif

        <div class="kami-feed-stack">
            @foreach($feedItems as $feed)
                @php $item = $feed['item']; @endphp
                @if($feed['type'] === 'post')
                    @include('partials.feed-post-card')
                @else
                    @include('partials.feed-talk-card')
                @endif
            @endforeach
        </div>
    </section>
@endsection
