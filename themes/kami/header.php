@php
    $activeTheme = \App\Services\ThemeManager::active();
    $siteThemeKey = (string)($activeTheme['key'] ?? 'kami');
@endphp
<!DOCTYPE html>
<html lang="zh-CN" data-site-theme="{{ $siteThemeKey }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $pageTitle ?? $site['title'] ?? 'LiteNote' }} - {{ $site['title'] ?? 'LiteNote' }}</title>
    <meta name="description" content="{{ $site['description'] ?? '' }}">
    <meta name="keywords" content="{{ $site['keywords'] ?? '' }}">
    <link rel="alternate" type="application/rss+xml" title="{{ $site['title'] ?? 'LiteNote' }} RSS" href="/rss.xml">
    @yield('head')
    <link rel="stylesheet" href="https://static.bluecdn.com/libs/fontawesome/7.2.0/css/all.min.css">
    @php
        $themeCss = (string)($activeTheme['stylesheet'] ?? '/themes/kami/assets/main.css');
        $themeJs = '/themes/' . rawurlencode($siteThemeKey) . '/assets/main.js';
        if (!is_file(BASE_PATH . $themeJs)) {
            $themeJs = '/themes/kami/assets/main.js';
        }
    @endphp
    <script>
        (function() {
            try {
                var saved = localStorage.getItem('litenote-theme');
                var isMode = function(value) { return value === 'light' || value === 'dark'; };
                var mode = isMode(saved)
                    ? saved
                    : (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
                document.documentElement.setAttribute('data-theme', mode);
            } catch (e) {}
        })();
    </script>
    <link rel="stylesheet" href="{{ $themeCss }}?v={{ \App\Services\ThemeManager::assetVersion($themeCss) }}">
    @php $umami = \App\Services\UmamiService::trackingScript(); @endphp
    @if($umami)
        <script defer src="{{ $umami['src'] }}" data-website-id="{{ $umami['websiteId'] }}"></script>
    @endif
</head>
<body>
    @php
        $navItems = $navItems ?? [];
        if (empty($navItems)) {
            $navItems = [
                ['title' => '动态', 'slug' => 'activity', 'url' => '/activity'],
                ['title' => '滔客', 'slug' => 'talk', 'url' => '/talk'],
                ['title' => '音乐', 'slug' => 'music', 'url' => '/music'],
                ['title' => '归档', 'slug' => 'archives', 'url' => '/archives'],
                ['title' => '友链', 'slug' => 'friends', 'url' => '/friends'],
                ['title' => '订阅', 'slug' => 'feeds', 'url' => '/feeds'],
            ];
        }
    @endphp

    <div class="kami-page">
        <header class="kami-site-head">
            <div class="kami-eyebrow">
                <a class="kami-brand" href="/" aria-label="返回首页">
                    <span>{{ $site['title'] ?? 'LiteNote' }}</span>
                    <em>紙</em>
                </a>
                <nav class="kami-links" aria-label="站点导航">
                    <a href="/posts" class="{{ ($activeNav ?? '') === 'posts' ? 'active' : '' }}">文章</a>
                    @foreach($navItems as $navItem)
                        @php $navItemActive = ($activeNav ?? '') === ($navItem['slug'] ?? ''); @endphp
                        <a href="{{ $navItem['url'] ?? '#' }}" class="{{ $navItemActive ? 'active' : '' }}">{{ $navItem['title'] ?? '' }}</a>
                    @endforeach
                </nav>
            </div>
        </header>

        <main class="kami-main">
