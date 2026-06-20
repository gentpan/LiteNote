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
    @yield('head')
    @php
        $activeNavKey = (string)($activeNav ?? '');
        $needsHomeCss = in_array($activeNavKey, ['home', 'posts'], true) || isset($category);
        $needsArticleFont = isset($post)
            || (isset($page) && is_object($page) && $page instanceof \App\Models\Page);
        $needsLiteZoom = in_array($activeNavKey, ['home', 'talk'], true) || $needsArticleFont;
        $themeCssFiles = array_filter([
            '/themes/ember/assets/main.css',
            '/themes/ember/assets/pages.css',
            $needsHomeCss ? '/themes/ember/assets/home.css' : null,
        ]);
        $mainJs = '/themes/ember/assets/main.js';
    @endphp
    <link rel="preconnect" href="https://static.bluecdn.com" crossorigin>
    <link rel="preconnect" href="https://gravatar.bluecdn.com" crossorigin>
    <link rel="stylesheet" href="https://static.bluecdn.com/libs/fontawesome/7.2.0/css/all.min.css">
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
    @php
        $articleFontMap = [
            'source-han-serif' => '"Source Han Serif CN VF", "Source Han Serif CN", "Source Han Serif SC", "Noto Serif CJK SC", "Songti SC", "SimSun", serif',
            'noto-sans-sc' => '"Noto Sans SC", "PingFang SC", "Hiragino Sans GB", "Microsoft YaHei", sans-serif',
            'lxgw-wenkai' => '"LXGW WenKai", "LXGW WenKai Screen", "Noto Sans SC", "PingFang SC", sans-serif',
            'kuaikan' => '"快看世界体", "Source Han Serif CN VF", "Source Han Serif SC", "Songti SC", serif',
            'luo' => '"Luo", "LXGW WenKai", "Noto Sans SC", "PingFang SC", sans-serif',
        ];
        $articleFontCssMap = [
            'source-han-serif' => 'https://static.bluecdn.com/fonts/cn/source-han-serif-cn/result.css',
            'noto-sans-sc' => 'https://static.bluecdn.com/fonts/cn/noto-sans-sc/result.css',
            'lxgw-wenkai' => 'https://static.bluecdn.com/fonts/cn/lxgw-wenkai/result.css',
            'kuaikan' => 'https://static.bluecdn.com/fonts/cn/kuaikanshijieti/result.css',
            'luo' => 'https://static.bluecdn.com/fonts/cn/luo/result.css',
        ];
        $articleFontKey = (string)\App\Models\Setting::get('post_article_font', 'source-han-serif');
        $articleFontFamily = $articleFontMap[$articleFontKey] ?? $articleFontMap['source-han-serif'];
        $articleFontCss = $articleFontCssMap[$articleFontKey] ?? $articleFontCssMap['source-han-serif'];
        $heroTitleFontCss = $articleFontCssMap['kuaikan'];
        $heroTitleFontFamily = '"快看世界体", "Source Han Serif CN VF", "Source Han Serif SC", "Songti SC", serif';
    @endphp
    @if($needsArticleFont)
        <link rel="stylesheet" href="{{ $articleFontCss }}">
        @if($articleFontCss !== $heroTitleFontCss)
            <link rel="stylesheet" href="{{ $heroTitleFontCss }}">
        @endif
    @endif
    <style>
        :root {
            --article-font-family: {!! $articleFontFamily !!};
            --post-hero-title-font-family: {!! $heroTitleFontFamily !!};
        }
    </style>
    {!! $__pluginFrontHead ?? '' !!}
</head>
<body>

    @php
        $navItems = $navItems ?? [];
        if (empty($navItems)) {
            $navItems = [
                ['title' => '动态', 'slug' => 'activity', 'url' => '/activity', 'icon' => 'fa-solid fa-chart-simple'],
                ['title' => '滔客', 'slug' => 'talk', 'url' => '/talk', 'icon' => 'fa-solid fa-head-side-speak'],
                ['title' => '音乐', 'slug' => 'music', 'url' => '/music', 'icon' => 'fa-solid fa-music'],
                ['title' => '归档', 'slug' => 'archives', 'url' => '/archives', 'icon' => 'fa-solid fa-box-archive'],
                ['title' => '友链', 'slug' => 'friends', 'url' => '/links', 'icon' => 'fa-solid fa-user-group'],
                ['title' => '订阅', 'slug' => 'feeds', 'url' => '/subscribe', 'icon' => 'fa-solid fa-square-rss'],
            ];
        }
        $navMainItems = [];
        $navMoreItems = [];      // 次级页面(关于/归档/订阅)
        $navMoreDemoted = [];    // 移动端从底栏挪到「更多」的项(音乐/友链)
        $navCtaItem = ['title' => '动态', 'slug' => 'activity', 'url' => '/activity', 'icon' => 'fa-solid fa-chart-simple'];
        foreach ($navItems as $navItem) {
            $navItemSlug = $navItem['slug'] ?? '';
            if ($navItemSlug === 'activity') {
                $navCtaItem = $navItem;
                continue;
            }
            if (in_array($navItemSlug, ['about', 'archives', 'feeds'], true)) {
                // 移动端底部「更多」上浮菜单收纳的次级页面
                $navMoreItems[] = $navItem;
                continue;
            }
            $navMainItems[] = $navItem;
            // 移动端底栏默认只显示 文章/滔客/书签/动态;音乐、友链 收进「更多」(桌面端仍在导航条)
            if (in_array($navItemSlug, ['music', 'friends'], true)) {
                $navMoreDemoted[] = $navItem;
            }
        }
        // 「更多」浮层顺序:被挪下来的(音乐/友链) 在前,次级页面在后
        $navMoreItems = array_merge($navMoreDemoted, $navMoreItems);
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
                    <a href="/" class="nav-avatar {{ ($activeNav ?? '') === 'home' ? 'active' : '' }}" title="{{ !empty($author) ? ($author->nickname ?: $author->username) : '首页' }}" aria-label="返回首页">
                        @if(!empty($author))
                            <img class="nav-avatar-img" src="{{ $author->getAvatarUrl(40) }}" alt="{{ $author->nickname }}" width="32" height="32" loading="lazy" data-blogger-avatar="{{ $author->getAvatarUrl(40) }}" data-blogger-name="{{ $author->nickname ?: $author->username }}">
                        @else
                            <span class="nav-avatar-fallback"><i class="fa-solid fa-house" aria-hidden="true"></i></span>
                        @endif
                        <span class="nav-avatar-home" aria-hidden="true"><i class="fa-solid fa-house" aria-hidden="true"></i></span>
                    </a>
                    <div class="nav-identity-actions" aria-label="头像快捷菜单">
                        @if(!empty($currentAdmin))
                            <a class="nav-identity-action nav-identity-profile" href="/admin/logout" aria-label="登出" title="登出"><i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i></a>
                        @else
                            <button type="button" class="nav-identity-action nav-identity-profile" data-login-open aria-label="登录" title="登录"><i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i></button>
                        @endif
                        <a class="nav-identity-action nav-identity-about" href="/about" aria-label="关于我" title="关于我"><i class="fa-regular fa-circle-user" aria-hidden="true"></i></a>
                        <a class="nav-identity-action nav-identity-archives" href="/archives" aria-label="归档" title="归档"><i class="fa-solid fa-box-archive" aria-hidden="true"></i></a>
                    </div>
                </div>
                <div class="nav-main-links">
                    <a href="/posts" class="nav-link nav-dd-trigger {{ ($activeNav ?? '') === 'posts' ? 'active' : '' }}" aria-haspopup="true">
                        <i class="fa-regular fa-file-lines nav-link-icon" aria-hidden="true"></i>
                        <span>文章</span>
                        <i class="fa-solid fa-chevron-down nav-dd-caret" aria-hidden="true"></i>
                    </a>
                    @foreach($navMainItems as $navItem)
                        @php $navItemActive = ($activeNav ?? '') === ($navItem['slug'] ?? ''); @endphp
                        <a href="{{ $navItem['url'] ?? '#' }}" class="nav-link {{ $navItemActive ? 'active' : '' }}">
                            <i class="{{ $navItem['icon'] ?? 'fa-regular fa-file-lines' }} nav-link-icon" aria-hidden="true"></i>
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
                                <i class="fa-solid fa-chart-simple nav-activity-bars" aria-hidden="true"></i>
                                <i class="fa-solid fa-gamepad nav-cta-mobile-icon" aria-hidden="true"></i>
                            @else
                                <i class="fa-solid fa-chart-simple" aria-hidden="true"></i>
                            @endif
                            <span>{{ $navCtaItem['title'] ?? '动态' }}</span>
                        </a>
                    @endif
                    @if(!empty($navMoreItems))
                        <button type="button" class="nav-more-toggle" data-nav-more aria-label="更多" aria-expanded="false" aria-haspopup="true">
                            <i class="fa-solid fa-bars nav-more-bars" aria-hidden="true"></i>
                            <i class="fa-solid fa-circle-xmark nav-more-close" aria-hidden="true"></i>
                            <span>更多</span>
                        </button>
                        <div class="nav-more-menu" data-nav-more-menu role="menu" aria-label="更多页面">
                            @foreach($navMoreItems as $moreItem)
                                <a href="{{ $moreItem['url'] ?? '#' }}" class="nav-more-item" role="menuitem">
                                    <i class="{{ $moreItem['icon'] ?? 'fa-regular fa-file-lines' }} nav-link-icon" aria-hidden="true"></i>
                                    <span>{{ $moreItem['title'] ?? '' }}</span>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
            <div class="nav-drawer">
                <div class="nav-cat-list">
                    @if(!empty($navCategories))
                        @foreach($navCategories as $cat)
                            <a href="{{ \App\Core\Helper::categoryUrl((string)($cat['slug'] ?? '')) }}" class="nav-cat-item cat-color-{{ $cat['color'] ?? 0 }}">
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

    <div class="side-quick-actions" aria-label="快捷操作">
    @if(!empty($currentAdmin))
    <a class="side-admin-entry" href="/admin" aria-label="进入后台" title="进入后台">
        <i class="fa-solid fa-gauge-high" aria-hidden="true"></i>
    </a>
    @endif
    @if(empty($currentAdmin))
    <div class="side-identity" data-side-identity>
        <button type="button" class="side-identity-trigger" data-nav-identity-edit aria-label="评论身份" title="评论身份">
            <img class="side-identity-avatar" data-side-identity-avatar alt="" hidden>
            <span class="side-identity-fallback" aria-hidden="true"><i class="fa-regular fa-circle-user" aria-hidden="true"></i></span>
        </button>
        <div class="side-identity-card" aria-hidden="true">
            <span class="side-identity-name" data-side-identity-name></span>
            <span class="side-identity-stat" data-side-identity-stat>设置评论身份，留下你的足迹</span>
        </div>
    </div>
    @endif
    <button type="button" class="side-search-toggle" data-search-toggle aria-label="搜索" title="搜索">
        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
    </button>
    <button type="button" class="side-theme-toggle" data-theme-toggle aria-label="切换深色模式" title="切换深色模式">
        <span class="side-theme-icon" aria-hidden="true">
            <i class="fa-solid fa-moon theme-icon-moon" aria-hidden="true"></i>
            <i class="fa-solid fa-sun theme-icon-sun" aria-hidden="true"></i>
        </span>
        <span class="side-theme-label" data-theme-label>深色模式</span>
    </button>
    </div>

    <div class="site-search-overlay" data-search-overlay hidden>
        <div class="site-search-panel" role="search" aria-label="站内搜索">
            <form action="/search" method="get" class="site-search-pop-form">
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                <input type="search" name="q" value="{{ $keyword ?? '' }}" placeholder="搜索文章、页面、滔客、音乐、X" autocomplete="off" data-search-input>
                <button type="submit">搜索</button>
                <button type="button" class="site-search-close" data-search-close aria-label="关闭搜索">
                    <i class="fa-solid fa-circle-xmark" aria-hidden="true"></i>
                </button>
            </form>
        </div>
    </div>

    @if(empty($currentAdmin))
    @php
        $loginPasskeyAvailable = false;
        try {
            $loginPasskeyAvailable = (new \App\Services\PasskeyService())->hasAnyCredential();
        } catch (\Throwable $e) {
            $loginPasskeyAvailable = false;
        }
    @endphp
    <div class="login-overlay" data-login-overlay hidden>
        <div class="login-modal" role="dialog" aria-modal="true" aria-label="登录后台">
            <button type="button" class="login-modal-close" data-login-close aria-label="关闭"><i class="fa-solid fa-circle-xmark" aria-hidden="true"></i></button>
            <div class="login-modal-head">
                <span class="login-modal-icon"><i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i></span>
                <div>
                    <p class="login-modal-title">登录后台</p>
                    <p class="login-modal-subtitle">{{ $site['title'] ?? 'LiteNote' }} 管理入口</p>
                </div>
            </div>
            <form class="login-modal-form" data-login-form>
                <input type="hidden" name="_csrf" value="{{ \App\Core\Session::csrfToken() }}">
                <label class="login-modal-field"><i class="fa-regular fa-circle-user" aria-hidden="true"></i><input name="username" placeholder="用户名" autocomplete="username" required></label>
                <label class="login-modal-field"><i class="fa-solid fa-lock" aria-hidden="true"></i><input name="password" type="password" placeholder="密码" autocomplete="current-password" required></label>
                <p class="login-modal-error" data-login-error hidden></p>
                <button type="submit" class="login-modal-submit"><i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i> 登录</button>
                @if($loginPasskeyAvailable)
                <button type="button" class="login-modal-passkey" data-login-passkey><i class="fa-solid fa-key" aria-hidden="true"></i> 使用 Passkey 登录</button>
                @endif
                <a class="login-modal-forgot" href="/admin/forgot">忘记密码？</a>
            </form>
        </div>
    </div>
    @endif

    <div class="container {{ ($activeNav ?? '') === 'archives' ? 'container-archive' : '' }}">
        <main class="site-main">
