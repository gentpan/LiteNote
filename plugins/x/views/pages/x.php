@extends('layouts.front')

@section('content')
    @php
        $xmarksLastUpdatedText = !empty($lastUpdatedAt) ? \App\Core\Helper::formatDate((string)$lastUpdatedAt, 'm/d H:i') : '';
        $xmarksLastUpdatedFull = !empty($lastUpdatedAt) ? \App\Core\Helper::formatDate((string)$lastUpdatedAt, 'Y-m-d H:i') : '';
        $xmarksProfileUrl = 'https://x.com';
        foreach (($socials ?? []) as $social) {
            $socialKey = strtolower((string)($social['key'] ?? ''));
            $socialUrl = trim((string)($social['url'] ?? ''));
            $socialLabel = strtolower((string)($social['label'] ?? ''));
            $socialIcon = strtolower((string)($social['icon'] ?? ''));
            if ($socialUrl !== '' && (
                in_array($socialKey, ['x', 'twitter'], true)
                || str_contains($socialUrl, 'x.com')
                || str_contains($socialUrl, 'twitter.com')
                || str_contains($socialLabel, 'twitter')
                || str_contains($socialIcon, 'fa-x-twitter')
            )) {
                $xmarksProfileUrl = $socialUrl;
                break;
            }
        }
    @endphp
    <section class="x-page x-bookmarks-page">
        <header class="x-page-head x-bookmarks-head">
            <div class="x-bookmarks-hero-head">
                <div class="x-bookmarks-hero-kicker-row">
                    <a class="x-bookmarks-hero-kicker" href="{{ $xmarksProfileUrl }}" target="_blank" rel="nofollow noopener" aria-label="查看我的 X">
                        <span class="x-bookmarks-hero-icon">
                            <i class="fa-brands fa-x-twitter" aria-hidden="true"></i>
                        </span>
                        <span class="x-bookmarks-dot" aria-hidden="true">·</span>
                        <span>书签</span>
                    </a>
                    <p class="x-bookmarks-hero-sub">
                        @if($xmarksLastUpdatedText !== '')
                            <span title="{{ $xmarksLastUpdatedFull }}">{{ $xmarksLastUpdatedText }} 更新</span>
                        @else
                            尚未同步
                        @endif
                    </p>
                </div>
            </div>
        </header>

        @if(empty($list))
            <p class="empty">还没有同步 X 书签</p>
        @else
            <div class="x-masonry js-list-items">
                @foreach($list as $tweet)
                    @php
                        $tweetBookmark = true;
                    @endphp
                    <article class="x-masonry-item" id="xmark-{{ $tweet->id }}">
                        @include('partials.x-card')
                    </article>
                @endforeach
            </div>
        @endif

        {!! $paginator ?? '' !!}
    </section>
@endsection
