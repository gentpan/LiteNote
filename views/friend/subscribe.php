@extends('layouts.front')

@section('content')
    <h2 class="section-title">订阅</h2>
    <p class="section-desc">这里展示友情链接的文章订阅更新，数据会缓存为 JSON 并保留最近 30 天；本站自己的文章 RSS 请使用 `/feed`。</p>

    <div class="subscribe-actions">
        <a class="subscribe-card" href="/feed">
            <i class="fa-solid fa-square-rss"></i>
            <span>
                <strong>本站 RSS</strong>
                <em>只输出 LiteNote 本站文章</em>
            </span>
        </a>
    </div>

    <h3 class="section-subtitle"><i class="fa-solid fa-rss"></i> 友情链接订阅更新</h3>
    @if(!empty($lastUpdated))
        <p class="section-desc">后台每天自动抓取 4 次，页面只读取本地 JSON 缓存；上次更新：{!! \App\Core\Helper::timeTag(date('Y-m-d H:i:s', (int)$lastUpdated)) !!}</p>
    @endif
    @if(empty($rssItems))
        <p class="empty">还没有抓取到友链订阅更新，后台任务会自动刷新。</p>
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
