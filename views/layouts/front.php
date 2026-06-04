<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $pageTitle ?? $site['title'] ?? 'LiteNote' }} - {{ $site['title'] ?? 'LiteNote' }}</title>
    <meta name="description" content="{{ $site['description'] ?? '' }}">
    <meta name="keywords" content="{{ $site['keywords'] ?? '' }}">
    <link rel="alternate" type="application/rss+xml" title="{{ $site['title'] ?? 'LiteNote' }} RSS" href="/feed">
    <link rel="stylesheet" href="https://static.giantaccel.com/fonts/SourceHanSerifCN/result.css">
    <link rel="stylesheet" href="/assets/fontawesome/css/all.min.css">
    @php $theme = $site['theme'] ?? 'default'; @endphp
    <link rel="stylesheet" href="/assets/css/themes/{{ $theme === 'default' ? 'default' : $theme }}.css?v={{ @filemtime(__DIR__ . '/../../public/assets/css/themes/' . $theme . '.css') ?: time() }}">
</head>
<body data-theme="{{ $theme }}">

    {{-- 顶部导航栏（桌面端） / 底部导航栏（移动端） --}}
    <nav class="site-nav-bar">
        <div class="nav-pill">
            @if(!empty($author))
                <a href="/" class="nav-item nav-avatar" title="{{ $author->nickname ?: $author->username }}" aria-label="博主头像 · 跳到首页">
                    <img class="nav-avatar-img" src="{{ $author->getAvatarUrl(40) }}" alt="{{ $author->nickname }}" width="32" height="32" loading="lazy">
                </a>
            @endif
            <a href="/" class="nav-item {{ ($pageTitle ?? null) === null ? 'active' : '' }}">
                <i class="fa-regular fa-file-lines"></i>
                <span>文章</span>
            </a>
            <a href="/shuoshuo" class="nav-item {{ ($pageTitle ?? '') === '说说' ? 'active' : '' }}">
                <i class="fa-regular fa-comments"></i>
                <span>说说</span>
            </a>
            <a href="/subscribe" class="nav-item {{ ($pageTitle ?? '') === '订阅' ? 'active' : '' }}">
                <i class="fa-solid fa-square-rss"></i>
                <span>订阅</span>
            </a>
        </div>
    </nav>

    <div class="container">
        <main class="site-main">
            @yield('content')
        </main>

        <footer class="site-footer">
            <p>
                &copy; {{ date('Y') }} {{ $site['title'] ?? 'LiteNote' }}.
                @if(!empty($site['beian']))<a href="https://beian.miit.gov.cn/" target="_blank" rel="nofollow noopener">{{ $site['beian'] }}</a>@endif
            </p>
        </footer>
    </div>

    <script src="/assets/js/view-image.min.js?v={{ @filemtime(__DIR__ . '/../../public/assets/js/view-image.min.js') ?: time() }}"></script>
    <script src="/assets/js/front.js?v={{ @filemtime(__DIR__ . '/../../public/assets/js/front.js') ?: time() }}"></script>
</body>
</html>
