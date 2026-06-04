@extends('layouts.front')

@section('content')
    <section class="subscribe-page">
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
            <div class="subscribe-feed-list">
                @foreach($rssItems as $item)
                    <article class="subscribe-feed-card">
                        <div class="feed-card-kicker">
                            <span>{{ $item['friend_name'] }}</span>
                            <time>{!! \App\Core\Helper::timeTag($item['pubDate'] ?? '') !!}</time>
                        </div>
                        <h3 class="subscribe-feed-title">
                            <a href="{{ $item['link'] }}" target="_blank" rel="nofollow noopener">{{ $item['title'] }}</a>
                        </h3>
                        @if(!empty($item['description']))
                            <p class="subscribe-feed-excerpt">{{ \App\Core\Helper::truncate((string)$item['description'], 140) }}</p>
                        @endif
                    </article>
                @endforeach
            </div>
        @endif
    </section>
@endsection
