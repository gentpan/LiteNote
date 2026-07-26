@php
    $activeTheme = \App\Services\ThemeManager::active();
    $siteThemeKey = (string)($activeTheme['key'] ?? 'kero');
    $siteTitle = (string)($site['title'] ?? 'LiteNote');
    $siteDesc = (string)($site['description'] ?? '');
    $navItems = $navItems ?? [];
    if ($navItems === []) {
        $navItems = [
            ['title' => '动态', 'slug' => 'activity', 'url' => '/activity'],
            ['title' => '滔客', 'slug' => 'talk', 'url' => '/talk'],
            ['title' => '音乐', 'slug' => 'music', 'url' => '/music'],
            ['title' => '归档', 'slug' => 'archives', 'url' => '/archives'],
            ['title' => '友链', 'slug' => 'friends', 'url' => '/links'],
            ['title' => '订阅', 'slug' => 'feeds', 'url' => '/subscribe'],
        ];
    }
    $activeNav = (string)($activeNav ?? '');
@endphp
<!DOCTYPE html>
<html lang="zh-CN" data-site-theme="{{ $siteThemeKey }}" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="dark">
    <title>{{ \App\Core\Helper::documentTitle($pageTitle ?? null, $site ?? []) }}</title>
    <meta name="description" content="{{ $siteDesc }}">
    <meta name="keywords" content="{{ $site['keywords'] ?? '' }}">
    <meta name="csrf-token" content="{{ \App\Core\Session::csrfToken() }}">
    {!! \App\Services\FaviconService::headHtml($site ?? []) !!}
    <link rel="alternate" type="application/rss+xml" title="{{ $siteTitle }} RSS" href="/rss.xml">
    @yield('head')
    <link rel="stylesheet" href="https://static.bluecdn.com/libs/fontawesome/7.3.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource-variable/geist@5.2.5/index.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource-variable/geist-mono@5.2.5/index.min.css">
    @php
        $themeCss = \App\Services\ThemeManager::styleAsset((string)($activeTheme['stylesheet'] ?? '/themes/kero/assets/main.css'));
        $themeJs = \App\Services\ThemeManager::scriptAsset('/themes/' . rawurlencode($siteThemeKey) . '/assets/main.js');
        if (!is_file(BASE_PATH . $themeJs)) {
            $themeJs = \App\Services\ThemeManager::scriptAsset('/themes/kero/assets/main.js');
        }
    @endphp
    <link rel="stylesheet" href="{{ $themeCss }}?v={{ \App\Services\ThemeManager::assetVersion($themeCss) }}">
    @php
        $needsArticleFont = isset($post)
            || (isset($page) && is_object($page) && $page instanceof \App\Models\Page);
    @endphp
    {!! \App\Services\ArticleFontService::headHtml($needsArticleFont) !!}
    {!! $__pluginFrontHead ?? '' !!}
</head>
<body>
    <div class="kero-site">
        <header class="kero-top">
            <a class="kero-brand" href="/" aria-label="返回首页">
                <span class="kero-mark" aria-hidden="true"></span>
                <span class="kero-name">{{ $siteTitle }}</span>
            </a>
            <nav class="kero-nav" aria-label="站点导航">
                <a href="/posts" class="{{ $activeNav === 'posts' ? 'is-active' : '' }}">文章</a>
                @foreach($navItems as $navItem)
                    @php $navActive = $activeNav === (string)($navItem['slug'] ?? ''); @endphp
                    <a href="{{ $navItem['url'] ?? '#' }}" class="{{ $navActive ? 'is-active' : '' }}">{{ $navItem['title'] ?? '' }}</a>
                @endforeach
            </nav>
            <div class="kero-top-tools">
                <button type="button" class="kero-icon-btn" data-copy-url="/rss.xml" title="复制 RSS" aria-label="复制 RSS">
                    <i class="fa-solid fa-rss" aria-hidden="true"></i>
                </button>
                @if(!empty($currentAdmin))
                    <a class="kero-icon-btn" href="/admin" aria-label="进入后台" title="进入后台">
                        <i class="fa-solid fa-gauge-high" aria-hidden="true"></i>
                    </a>
                @else
                    <button type="button" class="kero-icon-btn" data-login-open aria-label="登录" title="登录">
                        <i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i>
                    </button>
                    <button type="button" class="kero-icon-btn" data-identity-open aria-label="评论身份" title="评论身份" data-side-identity>
                        <img class="kero-tool-avatar" data-side-identity-avatar alt="" hidden>
                        <i class="fa-regular fa-circle-user kero-tool-avatar-fallback" aria-hidden="true"></i>
                        <span class="sr-only" data-side-identity-name>身份</span>
                        <span class="sr-only" data-side-identity-stat></span>
                    </button>
                @endif
            </div>
        </header>

        <main class="kero-main">
