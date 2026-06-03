@extends('layouts.front')

@section('content')
    <h2 class="section-title">友情链接</h2>
    <p class="section-desc">欢迎互换友链。联系方式见<a href="/page/about.html">关于页</a>。</p>

    <div class="friend-list">
        @foreach($links as $l)
            <a class="friend-card" href="{{ $l->url }}" target="_blank" rel="nofollow noopener">
                @if($l->logo)
                    <img src="{{ $l->logo }}" alt="{{ $l->name }}" class="friend-logo" loading="lazy">
                @else
                    <div class="friend-logo friend-default">{{ mb_substr($l->name, 0, 1) }}</div>
                @endif
                <div class="friend-info">
                    <h4>{{ $l->name }}</h4>
                    <p>{{ $l->description }}</p>
                </div>
            </a>
        @endforeach
    </div>

    @if(!empty($rssItems))
        <h3 class="section-subtitle"><i class="fa-solid fa-rss"></i> 友情链接最近更新（来自 RSS 聚合）</h3>
        <ul class="friend-rss-list">
            @foreach($rssItems as $item)
                <li>
                    <a href="{{ $item['link'] }}" target="_blank" rel="nofollow noopener">{{ $item['title'] }}</a>
                    <span class="friend-source">- {{ $item['friend_name'] }}</span>
                    <span class="friend-date">· {!! \App\Core\Helper::timeTag($item['pubDate'] ?? '') !!}</span>
                </li>
            @endforeach
        </ul>
        <p class="rss-link">
            <i class="fa-solid fa-users"></i> <a href="/friends/feed">订阅友链聚合 RSS</a>
        </p>
    @endif
@endsection
