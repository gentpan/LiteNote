@extends('layouts.front')

@section('content')
    <h2 class="section-title">订阅</h2>
    <p class="section-desc">这里聚合友情链接的 RSS 文章更新；本站自己的文章 RSS 请使用 `/feed`。</p>

    <div class="subscribe-actions">
        <a class="subscribe-card" href="/friends/feed">
            <i class="fa-solid fa-users"></i>
            <span>
                <strong>友链聚合 RSS</strong>
                <em>订阅友情链接的最新文章</em>
            </span>
        </a>
        <a class="subscribe-card" href="/feed">
            <i class="fa-solid fa-square-rss"></i>
            <span>
                <strong>本站 RSS</strong>
                <em>只输出 LiteNote 本站文章</em>
            </span>
        </a>
    </div>

    <h3 class="section-subtitle"><i class="fa-solid fa-rss"></i> 友情链接最新文章</h3>
    @if(empty($rssItems))
        <p class="empty">还没有抓取到友链 RSS 更新。</p>
    @else
        <ul class="friend-rss-list subscribe-feed-list">
            @foreach($rssItems as $item)
                <li>
                    <a href="{{ $item['link'] }}" target="_blank" rel="nofollow noopener">{{ $item['title'] }}</a>
                    <span class="friend-source">- {{ $item['friend_name'] }}</span>
                    <span class="friend-date">· {!! \App\Core\Helper::timeTag($item['pubDate'] ?? '') !!}</span>
                    @if(!empty($item['description']))
                        <p>{{ \App\Core\Helper::truncate((string)$item['description'], 140) }}</p>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
@endsection
