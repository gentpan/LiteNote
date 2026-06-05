@extends('layouts.front')

@section('content')
    <section class="subscribe-page">
        <h2 class="section-title">订阅</h2>
        <p class="section-desc">订阅本站文章，或在这里追看友情链接博主的最新更新。</p>

        <div class="subscribe-actions">
            <a class="subscribe-card" href="/rss.xml">
                <i class="fa-solid fa-square-rss"></i>
                <span>
                    <strong>本站 RSS</strong>
                    <em>订阅本站最新文章更新</em>
                </span>
            </a>
        </div>

        <h3 class="section-subtitle"><i class="fa-solid fa-rss"></i> 友情链接最近更新（来自 RSS 聚合）</h3>
        @if(!empty($lastUpdated))
            <p class="section-desc">汇集友情链接博主的最新文章，最近更新：{!! \App\Core\Helper::timeTag(date('Y-m-d H:i:s', (int)$lastUpdated)) !!}</p>
        @endif
        @if(empty($rssItems))
            <p class="empty">暂时还没有友情链接的最新文章，过些时候再来看看吧。</p>
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
