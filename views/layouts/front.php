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
    @yield('head')
    <link rel="stylesheet" href="https://static.bluecdn.com/libs/fontawesome/7.2.0/css/all.min.css">
    @php $theme = $site['theme'] ?? 'default'; @endphp
    <link rel="stylesheet" href="/assets/css/themes/{{ $theme === 'default' ? 'default' : $theme }}.css?v={{ @filemtime(__DIR__ . '/../../public/assets/css/themes/' . $theme . '.css') ?: time() }}">
</head>
<body data-theme="{{ $theme }}">

    {{-- 顶部导航栏（桌面端） / 底部导航栏（移动端） --}}
    <nav class="site-nav-bar">
        <div class="nav-pill" id="nav-shell">
            <div class="nav-row">
                <a href="/" class="nav-avatar {{ ($activeNav ?? '') === 'home' ? 'active' : '' }}" title="{{ !empty($author) ? ($author->nickname ?: $author->username) : '首页' }}" aria-label="返回首页">
                    @if(!empty($author))
                        <img class="nav-avatar-img" src="{{ $author->getAvatarUrl(40) }}" alt="{{ $author->nickname }}" width="32" height="32" loading="lazy">
                    @else
                        <span class="nav-avatar-fallback"><i class="fa-solid fa-house"></i></span>
                    @endif
                </a>
                <a href="/posts" class="nav-link nav-dd-trigger {{ ($activeNav ?? '') === 'posts' ? 'active' : '' }}" aria-haspopup="true">
                    <i class="fa-regular fa-file-lines"></i>
                    <span>文章</span>
                    <i class="fa-solid fa-chevron-down nav-dd-caret"></i>
                </a>
                <a href="/talk" class="nav-link {{ ($activeNav ?? '') === 'talk' ? 'active' : '' }}">
                    <i class="fa-regular fa-comments"></i><span>滔客</span>
                </a>
                <a href="/archives" class="nav-link {{ ($activeNav ?? '') === 'archives' ? 'active' : '' }}">
                    <i class="fa-solid fa-box-archive"></i><span>归档</span>
                </a>
                <a href="/friends" class="nav-link {{ ($activeNav ?? '') === 'friends' ? 'active' : '' }}">
                    <i class="fa-solid fa-link"></i><span>友链</span>
                </a>
                <a href="/feeds" class="nav-cta {{ ($pageTitle ?? '') === '订阅' ? 'active' : '' }}">
                    <i class="fa-solid fa-square-rss"></i><span>订阅</span>
                </a>
            </div>
            <div class="nav-drawer">
                <div class="nav-drawer-inner">
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
        </div>
    </nav>

    <div class="container">
        <main class="site-main">
            @yield('content')
        </main>

        <footer class="site-footer">
            <div class="footer-copy">
                &copy; {{ date('Y') }} {{ $site['title'] ?? 'LiteNote' }}.
                @if(!empty($site['beian']))<a href="https://beian.miit.gov.cn/" target="_blank" rel="nofollow noopener">{{ $site['beian'] }}</a>@endif
            </div>
            @if(!empty($socials))
                <div class="footer-socials">
                    @foreach($socials as $s)
                        @if(($s['key'] ?? '') !== 'email' && strpos((string)($s['url'] ?? ''), 'mailto:') !== 0)
                            <a href="{{ $s['url'] }}" class="footer-social" title="{{ $s['label'] }}" aria-label="{{ $s['label'] }}" target="_blank" rel="nofollow noopener">{!! $s['icon'] !!}</a>
                        @endif
                    @endforeach
                </div>
            @endif
        </footer>
    </div>

    <script src="/assets/js/front.js?v={{ @filemtime(__DIR__ . '/../../public/assets/js/front.js') ?: time() }}"></script>
</body>
</html>
