@extends('layouts.front')

@section('content')
    @php
        $siteTitle = (string)($site['title'] ?? 'LiteNote');
        $siteDesc = trim((string)($site['description'] ?? ''));
    @endphp

    <section class="kero-hero">
        <h1 class="kero-hero-title">
            <span class="kero-hero-line">写点东西，</span>
            <span class="kero-hero-line">把近况放在一处。</span>
        </h1>
        @if($siteDesc !== '')
            <p class="kero-hero-desc">{{ $siteDesc }}</p>
        @else
            <p class="kero-hero-desc">文章、滔客与链接日志 — 冷静、清楚、不打扰。</p>
        @endif
        <div class="kero-hero-actions">
            <a class="kero-btn kero-btn-primary" href="/posts">阅读文章</a>
            <a class="kero-btn" href="/archives">归档</a>
        </div>
        <div class="kero-chips">
            <span>Kero</span>
            <span>Geist</span>
            <span>no noise</span>
        </div>
    </section>

    @include('partials.activity-summary-card')

    <section class="kero-section" aria-label="首页动态">
        <h2 class="kero-section-label">近期</h2>

        @if(empty($feedItems))
            <p class="kero-empty">还没有内容</p>
        @endif

        <div class="kero-rows feed-list">
            @foreach($feedItems as $feed)
                @php $item = $feed['item']; @endphp
                @if($feed['type'] === 'post')
                    @include('partials.feed-post-card')
                @elseif($feed['type'] === 'x_tweet')
                    @php $tweet = $item; $tweetLocalActions = true; $tweetShowReplies = false; $tweetHideViews = true; @endphp
                    @include('partials.x-card')
                @else
                    @include('partials.feed-talk-card')
                @endif
            @endforeach
        </div>
    </section>
@endsection
