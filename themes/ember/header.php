<!DOCTYPE html>
<html lang="zh-CN" data-site-theme="ember">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $pageTitle ?? $site['title'] ?? 'LiteNote' }} - {{ $site['title'] ?? 'LiteNote' }}</title>
    <meta name="description" content="{{ $site['description'] ?? '' }}">
    <meta name="keywords" content="{{ $site['keywords'] ?? '' }}">
    {!! \App\Services\FaviconService::headHtml($site ?? []) !!}
    <link rel="alternate" type="application/rss+xml" title="{{ $site['title'] ?? 'LiteNote' }} RSS" href="/rss.xml">
    <link rel="stylesheet" href="https://static.bluecdn.com/fonts/SourceHanSerifCN/result.css">
    <link rel="stylesheet" href="https://static.bluecdn.com/fonts/kuaikanshijieti/result.css">
    @yield('head')
    <link rel="stylesheet" href="https://static.bluecdn.com/libs/fontawesome/7.2.0/css/all.min.css">
    @php
        $themeCssFiles = [
            '/themes/ember/assets/main.css',
            '/themes/ember/assets/pages.css',
            '/themes/ember/assets/home.css',
        ];
        $mainJs = '/themes/ember/assets/main.js';
    @endphp
    <script>
        (function() {
            try {
                var saved = localStorage.getItem('litenote-theme');
                var theme = (saved === 'dark' || saved === 'light')
                    ? saved
                    : (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
                document.documentElement.setAttribute('data-theme', theme);
            } catch (e) {}
        })();
    </script>
    @foreach($themeCssFiles as $themeCss)
        <link rel="stylesheet" href="{{ $themeCss }}?v={{ \App\Services\ThemeManager::assetVersion($themeCss) }}">
    @endforeach
</head>
<body>

    @php
        $navItems = $navItems ?? [];
        if (empty($navItems)) {
            $navItems = [
                ['title' => '动态', 'slug' => 'activity', 'url' => '/activity', 'icon' => 'fa-solid fa-chart-simple'],
                ['title' => '滔客', 'slug' => 'talk', 'url' => '/talk', 'icon' => 'fa-regular fa-comments'],
                ['title' => '音乐', 'slug' => 'music', 'url' => '/music', 'icon' => 'fa-solid fa-music'],
                ['title' => '归档', 'slug' => 'archives', 'url' => '/archives', 'icon' => 'fa-solid fa-box-archive'],
                ['title' => '友链', 'slug' => 'friends', 'url' => '/links', 'icon' => 'fa-solid fa-link'],
                ['title' => '订阅', 'slug' => 'feeds', 'url' => '/subscribe', 'icon' => 'fa-solid fa-square-rss'],
            ];
        }
        $navMainItems = [];
        $navCtaItem = ['title' => '动态', 'slug' => 'activity', 'url' => '/activity', 'icon' => 'fa-solid fa-chart-simple'];
        foreach ($navItems as $navItem) {
            if (($navItem['slug'] ?? '') === 'activity') {
                $navCtaItem = $navItem;
                continue;
            }
            if (in_array(($navItem['slug'] ?? ''), ['about', 'archives', 'feeds'], true)) {
                continue;
            }
            $navMainItems[] = $navItem;
        }
        $hasRecentActivity = false;
        try {
            $recentActivitySince = date('Y-m-d H:i:s', time() - 6 * 3600);
            $recentActivityNow = date('Y-m-d H:i:s');
            $hasRecentActivity = (int)\App\Models\Activity::db()->fetchColumn(
                "SELECT COUNT(*) FROM activities WHERE visibility = 'public' AND happened_at >= ? AND happened_at <= ?",
                [$recentActivitySince, $recentActivityNow]
            ) > 0;
        } catch (\Throwable $e) {
            $hasRecentActivity = false;
        }
    @endphp

    {{-- 顶部导航栏（桌面端） / 底部导航栏（移动端） --}}
    <nav class="site-nav-bar">
        <div class="nav-pill" id="nav-shell">
            <div class="nav-row">
                <div class="nav-identity-orb" data-nav-identity data-nav-admin="{{ !empty($currentAdmin) ? '1' : '0' }}">
                    <a href="{{ ($activeNav ?? '') === 'home' ? '/?__refresh=1' : '/' }}" class="nav-avatar {{ ($activeNav ?? '') === 'home' ? 'active' : '' }}" title="{{ !empty($author) ? ($author->nickname ?: $author->username) : '首页' }}" aria-label="返回首页">
                        @if(!empty($author))
                            <img class="nav-avatar-img" src="{{ $author->getAvatarUrl(40) }}" alt="{{ $author->nickname }}" width="32" height="32" loading="lazy" data-blogger-avatar="{{ $author->getAvatarUrl(40) }}" data-blogger-name="{{ $author->nickname ?: $author->username }}">
                        @else
                            <span class="nav-avatar-fallback"><i class="fa-solid fa-house"></i></span>
                        @endif
                        <span class="nav-avatar-home" aria-hidden="true"><i class="fa-solid fa-house"></i></span>
                    </a>
                    <div class="nav-identity-actions" aria-label="头像快捷菜单">
                        @if(!empty($currentAdmin))
                            <a class="nav-identity-action nav-identity-profile" href="/admin/logout" aria-label="登出" title="登出"><i class="fa-solid fa-right-from-bracket"></i></a>
                        @else
                            <button type="button" class="nav-identity-action nav-identity-profile" data-nav-identity-edit aria-label="更换资料" title="更换资料"><i class="fa-solid fa-user-pen"></i></button>
                        @endif
                        <a class="nav-identity-action nav-identity-about" href="/about" aria-label="关于我" title="关于我"><i class="fa-regular fa-circle-user"></i></a>
                        <a class="nav-identity-action nav-identity-archives" href="/archives" aria-label="归档" title="归档"><i class="fa-solid fa-box-archive"></i></a>
                    </div>
                </div>
                <div class="nav-main-links">
                    <a href="/posts" class="nav-link nav-dd-trigger {{ ($activeNav ?? '') === 'posts' ? 'active' : '' }}" aria-haspopup="true">
                        <span>文章</span>
                        <i class="fa-solid fa-chevron-down nav-dd-caret"></i>
                    </a>
                    @foreach($navMainItems as $navItem)
                        @php $navItemActive = ($activeNav ?? '') === ($navItem['slug'] ?? ''); @endphp
                        <a href="{{ $navItem['url'] ?? '#' }}" class="nav-link {{ $navItemActive ? 'active' : '' }}">
                            <span>{{ $navItem['title'] ?? '' }}</span>
                        </a>
                    @endforeach
                </div>
                <div class="nav-actions">
                    @if($navCtaItem)
                        @php
                            $navCtaSlug = $navCtaItem['slug'] ?? '';
                            $navCtaActive = ($activeNav ?? '') === $navCtaSlug || ($navCtaSlug === 'activity' && ($pageTitle ?? '') === '动态');
                        @endphp
                        <a href="{{ $navCtaItem['url'] ?? '#' }}" class="nav-cta {{ $navCtaActive ? 'active' : '' }} {{ $hasRecentActivity ? 'has-recent-activity' : '' }}">
                            @if($navCtaSlug === 'activity')
                                <span class="nav-activity-bars" aria-hidden="true"><i></i><i></i><i></i></span>
                            @else
                                <i class="{{ $navCtaItem['icon'] ?? 'fa-solid fa-chart-simple' }}"></i>
                            @endif
                            <span>{{ $navCtaItem['title'] ?? '动态' }}</span>
                        </a>
                    @endif
                </div>
            </div>
            <div class="nav-drawer">
                <div class="nav-cat-list">
                    @if(!empty($navCategories))
                        @foreach($navCategories as $cat)
                            <a href="/category/{{ $cat['slug'] }}" class="nav-cat-item cat-color-{{ $cat['color'] ?? 0 }}">
                                <span class="nav-cat-ico"><i class="{{ $cat['icon'] ?? 'fa-regular fa-folder' }}"></i></span>
                                <span class="nav-cat-name">{{ $cat['name'] }}</span>
                                @if(!empty($cat['desc']))<span class="nav-cat-desc">{{ $cat['desc'] }}</span>@endif
                            </a>
                        @endforeach
                    @endif
                </div>
            </div>
        </div>
    </nav>

    <button type="button" class="side-theme-toggle" data-theme-toggle aria-label="切换深色模式" title="切换深色模式">
        <span class="side-theme-icon" aria-hidden="true">
            <i class="fa-solid fa-moon theme-icon-moon"></i>
            <i class="fa-solid fa-sun theme-icon-sun"></i>
        </span>
        <span class="side-theme-label" data-theme-label>深色模式</span>
    </button>

    <div class="container {{ ($activeNav ?? '') === 'archives' ? 'container-archive' : '' }}">
        <main class="site-main">
