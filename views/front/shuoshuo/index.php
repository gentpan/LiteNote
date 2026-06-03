@extends('layouts.front')

@section('content')
    <section class="shuoshuo-list">
        <h2 class="section-title">说说</h2>
        @if(empty($list))
            <p class="empty">还没有说说</p>
        @endif
        @foreach($list as $s)
            <article class="shuoshuo-card">
                <div class="shuoshuo-content">{{ $s->content }}</div>

                @php $images = $s->getImages(); @endphp
                @if(!empty($images))
                    <div class="shuoshuo-images">
                        @foreach($images as $img)
                            <img src="{{ trim($img) }}" alt="" loading="lazy">
                        @endforeach
                    </div>
                @endif

                @php $music = $s->getMusicEmbed(); @endphp
                @if($music)
                    <div class="shuoshuo-music">
                        <div class="music-player">
                            {!! $music['html'] !!}
                        </div>
                    </div>
                @endif

                <div class="shuoshuo-meta">
                    @if($s->mood)
                        <span class="mood">{!! $s->mood !!}</span>
                    @endif
                    <span class="time">{!! \App\Core\Helper::timeTag($s->created_at) !!}</span>
                </div>
            </article>
        @endforeach
        {!! $paginator ?? '' !!}
    </section>
@endsection
