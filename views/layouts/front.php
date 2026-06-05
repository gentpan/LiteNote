<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $pageTitle ?? $site['title'] ?? 'LiteNote' }} - {{ $site['title'] ?? 'LiteNote' }}</title>
    <meta name="description" content="{{ $site['description'] ?? '' }}">
    <meta name="keywords" content="{{ $site['keywords'] ?? '' }}">
    <link rel="alternate" type="application/rss+xml" title="{{ $site['title'] ?? 'LiteNote' }} RSS" href="/rss.xml">
    <link rel="stylesheet" href="https://static.bluecdn.com/fonts/SourceHanSerifCN/result.css">
    <link rel="stylesheet" href="https://static.bluecdn.com/fonts/kuaikanshijieti/result.css">
    @yield('head')
    <link rel="stylesheet" href="https://static.bluecdn.com/libs/fontawesome/7.2.0/css/all.min.css">
    @php
        $mainCss = '/assets/css/main.css';
        $mainJs = '/assets/js/main.js';
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
    <link rel="stylesheet" href="{{ $mainCss }}?v={{ @filemtime(__DIR__ . '/../../public' . $mainCss) ?: time() }}">
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
                ['title' => '滔客', 'slug' => 'talk', 'url' => '/talk', 'icon' => 'fa-regular fa-comments'],
                ['title' => '音乐', 'slug' => 'music', 'url' => '/music', 'icon' => 'fa-solid fa-music'],
                ['title' => '归档', 'slug' => 'archives', 'url' => '/archives', 'icon' => 'fa-solid fa-box-archive'],
                ['title' => '友链', 'slug' => 'friends', 'url' => '/friends', 'icon' => 'fa-solid fa-link'],
                ['title' => '订阅', 'slug' => 'feeds', 'url' => '/feeds', 'icon' => 'fa-solid fa-square-rss'],
            ];
        }
        $navMainItems = [];
        $navCtaItem = null;
        foreach ($navItems as $navItem) {
            if (($navItem['slug'] ?? '') === 'feeds') {
                $navCtaItem = $navItem;
                continue;
            }
            $navMainItems[] = $navItem;
        }
    @endphp

    {{-- 顶部导航栏（桌面端） / 底部导航栏（移动端） --}}
    <nav class="site-nav-bar">
        <div class="nav-pill" id="nav-shell">
            <div class="nav-row">
                <span class="nav-indicator" aria-hidden="true"></span>
                <a href="/" class="nav-avatar {{ ($activeNav ?? '') === 'home' ? 'active' : '' }}" title="{{ !empty($author) ? ($author->nickname ?: $author->username) : '首页' }}" aria-label="返回首页">
                    @if(!empty($author))
                        <img class="nav-avatar-img" src="{{ $author->getAvatarUrl(40) }}" alt="{{ $author->nickname }}" width="32" height="32" loading="lazy">
                    @else
                        <span class="nav-avatar-fallback"><i class="fa-solid fa-house"></i></span>
                    @endif
                    <span class="nav-avatar-home" aria-hidden="true"><i class="fa-solid fa-house"></i></span>
                </a>
                <div class="nav-main-links">
                    <a href="/posts" class="nav-link nav-dd-trigger {{ ($activeNav ?? '') === 'posts' ? 'active' : '' }}" aria-haspopup="true">
                        <i class="fa-regular fa-file-lines"></i>
                        <span>文章</span>
                        <i class="fa-solid fa-chevron-down nav-dd-caret"></i>
                    </a>
                    @foreach($navMainItems as $navItem)
                        @php $navItemActive = ($activeNav ?? '') === ($navItem['slug'] ?? ''); @endphp
                        <a href="{{ $navItem['url'] ?? '#' }}" class="nav-link {{ $navItemActive ? 'active' : '' }}">
                            <i class="{{ $navItem['icon'] ?? 'fa-regular fa-bookmark' }}"></i><span>{{ $navItem['title'] ?? '' }}</span>
                        </a>
                    @endforeach
                </div>
                <div class="nav-actions">
                    @if($navCtaItem)
                        @php
                            $navCtaSlug = $navCtaItem['slug'] ?? '';
                            $navCtaActive = ($activeNav ?? '') === $navCtaSlug || ($navCtaSlug === 'feeds' && ($pageTitle ?? '') === '订阅');
                        @endphp
                        <a href="{{ $navCtaItem['url'] ?? '#' }}" class="nav-cta {{ $navCtaActive ? 'active' : '' }}">
                            <i class="{{ $navCtaItem['icon'] ?? 'fa-solid fa-square-rss' }}"></i><span>{{ $navCtaItem['title'] ?? '订阅' }}</span>
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
            @yield('content')
        </main>

        <footer class="site-footer">
            <div class="footer-copy">
                &copy; {{ date('Y') }} {{ $site['title'] ?? 'LiteNote' }}.
                @if(!empty($site['beian']))<a href="https://beian.miit.gov.cn/" target="_blank" rel="nofollow noopener">{{ $site['beian'] }}</a>@endif
            </div>
            <div class="footer-socials">
                <button type="button" class="footer-social footer-rss-copy" data-copy-url="/rss.xml" title="复制本站 RSS 地址" aria-label="复制本站 RSS 地址">
                    <i class="fa-solid fa-square-rss"></i>
                </button>
                @if(!empty($socials))
                    @foreach($socials as $s)
                        @if(($s['key'] ?? '') !== 'email' && strpos((string)($s['url'] ?? ''), 'mailto:') !== 0)
                            <a href="{{ $s['url'] }}" class="footer-social" title="{{ $s['label'] }}" aria-label="{{ $s['label'] }}" target="_blank" rel="nofollow noopener">{!! $s['icon'] !!}</a>
                        @endif
                    @endforeach
                @endif
            </div>
        </footer>
    </div>

    <script src="{{ $mainJs }}?v={{ @filemtime(__DIR__ . '/../../public' . $mainJs) ?: time() }}"></script>
</body>
</html>
