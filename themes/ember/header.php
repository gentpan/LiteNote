<!DOCTYPE html>
<html lang="zh-CN" data-site-theme="ember">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ \App\Core\Helper::documentTitle($pageTitle ?? null, $site ?? []) }}</title>
    <meta name="description" content="{{ $site['description'] ?? '' }}">
    <meta name="keywords" content="{{ $site['keywords'] ?? '' }}">
    <meta name="csrf-token" content="{{ \App\Core\Session::csrfToken() }}">
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
            \App\Services\ThemeManager::styleAsset('/themes/ember/assets/main.css'),
            \App\Services\ThemeManager::styleAsset('/themes/ember/assets/pages.css'),
            $needsHomeCss ? \App\Services\ThemeManager::styleAsset('/themes/ember/assets/home.css') : null,
            \App\Services\ThemeManager::styleAsset('/themes/ember/assets/icons/ln-icons.css'),
        ]);
        $mainJs = \App\Services\ThemeManager::scriptAsset('/themes/ember/assets/main.js');
        $lnIconsJs = \App\Services\ThemeManager::scriptAsset('/themes/ember/assets/icons/ln-icons.js');
        $emberLnIconBySlug = [
            'activity' => 'activity',
            'talk' => 'message-circle',
            'music' => 'audio-lines',
            'archives' => 'archive',
            'about' => 'user',
            'friends' => 'users',
            'posts' => 'file-text',
        ];
    @endphp
    <link rel="preconnect" href="https://static.bluecdn.com" crossorigin>
    <link rel="preconnect" href="https://gravatar.bluecdn.com" crossorigin>
    <link rel="stylesheet" href="https://static.bluecdn.com/libs/fontawesome/7.3.0/css/all.min.css">
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
    {!! \App\Services\ArticleFontService::headHtml($needsArticleFont) !!}
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
                ['title' => '友链', 'slug' => 'friends', 'url' => '/links', 'icon' => 'fa-solid fa-user-group'],
                ['title' => '归档', 'slug' => 'archives', 'url' => '/archives', 'icon' => 'fa-solid fa-box-archive'],
                ['title' => '关于', 'slug' => 'about', 'url' => '/about', 'icon' => 'fa-regular fa-circle-user'],
                ['title' => '订阅', 'slug' => 'feeds', 'url' => '/subscribe', 'icon' => 'fa-solid fa-square-rss'],
            ];
        }
        $navMainItems = [];
        $navMoreItems = [];      // 次级页面(订阅等)
        $navMoreDemoted = [];    // 移动端从底栏挪到「更多」的项
        $navCtaItem = ['title' => '动态', 'slug' => 'activity', 'url' => '/activity', 'icon' => 'fa-solid fa-chart-simple'];
        foreach ($navItems as $navItem) {
            $navItemSlug = $navItem['slug'] ?? '';
            if ($navItemSlug === 'activity') {
                $navCtaItem = $navItem;
                continue;
            }
            if ($navItemSlug === 'feeds') {
                // 订阅仍收进「更多」
                $navMoreItems[] = $navItem;
                continue;
            }
            $navMainItems[] = $navItem;
            // 移动端底栏默认只显示 文章/滔客/书签/动态;音乐、友链、归档、关于 收进「更多」(桌面端仍在导航条)
            if (in_array($navItemSlug, ['music', 'friends', 'archives', 'about'], true)) {
                $navMoreDemoted[] = $navItem;
            }
        }
        // 确保归档/关于一定出现在桌面菜单栏
        $navMainSlugs = array_map(static function ($item) {
            return (string)($item['slug'] ?? '');
        }, $navMainItems);
        if (!in_array('archives', $navMainSlugs, true)) {
            $archivesItem = ['title' => '归档', 'slug' => 'archives', 'url' => '/archives', 'icon' => 'fa-solid fa-box-archive'];
            $navMainItems[] = $archivesItem;
            $navMoreDemoted[] = $archivesItem;
        }
        if (!in_array('about', $navMainSlugs, true)) {
            $aboutItem = ['title' => '关于', 'slug' => 'about', 'url' => '/about', 'icon' => 'fa-regular fa-circle-user'];
            $navMainItems[] = $aboutItem;
            $navMoreDemoted[] = $aboutItem;
        }
        // 「更多」浮层顺序:被挪下来的在前,次级页面在后
        $navMoreItems = array_merge($navMoreDemoted, $navMoreItems);
        $hasRecentActivity = false;
        try {
            $hasRecentActivity = (new \App\Core\FileCache())->remember('nav.recent-activity', 60, static function (): bool {
                $recentActivitySince = date('Y-m-d H:i:s', time() - 6 * 3600);
                $recentActivityNow = date('Y-m-d H:i:s');
                return (int)\App\Models\Activity::db()->fetchColumn(
                    "SELECT COUNT(*) FROM activities WHERE visibility = 'public' AND happened_at >= ? AND happened_at <= ?",
                    [$recentActivitySince, $recentActivityNow]
                ) > 0;
            });
        } catch (\Throwable $e) {
            $hasRecentActivity = false;
        }
    @endphp

    {{-- 顶部导航栏（桌面端） / 底部导航栏（移动端） --}}
    <nav class="site-nav-bar">
        <div class="nav-brand">
            <div class="nav-identity-orb" data-nav-identity>
                <a href="/" class="nav-avatar {{ ($activeNav ?? '') === 'home' ? 'active' : '' }}" aria-label="{{ !empty($author) ? ('返回首页 · ' . ($author->nickname ?: $author->username)) : '返回首页' }}">
                    @if(!empty($author))
                        <img class="nav-avatar-img" src="{{ $author->getAvatarUrl(40) }}" alt="{{ $author->nickname }}" width="32" height="32" loading="lazy" data-blogger-avatar="{{ $author->getAvatarUrl(40) }}" data-blogger-name="{{ $author->nickname ?: $author->username }}">
                    @else
                        <span class="nav-avatar-fallback">@include('partials.ln-icon', ['name' => 'home'])</span>
                    @endif
                    <span class="nav-avatar-home" aria-hidden="true">@include('partials.ln-icon', ['name' => 'home'])</span>
                </a>
            </div>
        </div>

        <div class="nav-pill" id="nav-shell">
            <div class="nav-row">
                <div class="nav-main-links">
                    <a href="/posts" class="nav-link nav-dd-trigger {{ ($activeNav ?? '') === 'posts' ? 'active' : '' }}" aria-label="文章" aria-haspopup="true">
                        @include('partials.ln-icon', ['name' => 'file-text', 'class' => 'nav-link-icon'])
                        <span>文章</span>
                        <i class="fa-solid fa-chevron-down nav-dd-caret" aria-hidden="true"></i>
                    </a>
                    @foreach($navMainItems as $navItem)
                        @php
                            $navItemActive = ($activeNav ?? '') === ($navItem['slug'] ?? '');
                            $navLnIcon = $emberLnIconBySlug[$navItem['slug'] ?? ''] ?? '';
                        @endphp
                        <a href="{{ $navItem['url'] ?? '#' }}" class="nav-link {{ $navItemActive ? 'active' : '' }}" data-nav-slug="{{ $navItem['slug'] ?? '' }}" aria-label="{{ $navItem['title'] ?? '' }}">
                            @if($navLnIcon !== '')
                                @include('partials.ln-icon', ['name' => $navLnIcon, 'class' => 'nav-link-icon'])
                            @else
                                <i class="{{ $navItem['icon'] ?? 'fa-regular fa-file-lines' }} nav-link-icon" aria-hidden="true"></i>
                            @endif
                            <span>{{ $navItem['title'] ?? '' }}</span>
                        </a>
                    @endforeach
                </div>
                <div class="nav-actions">
                    @if($navCtaItem)
                        @php
                            $navCtaSlug = $navCtaItem['slug'] ?? '';
                            $navCtaActive = ($activeNav ?? '') === $navCtaSlug || ($navCtaSlug === 'activity' && ($pageTitle ?? '') === '动态');
                            $navCtaLn = $emberLnIconBySlug[$navCtaSlug] ?? 'activity';
                        @endphp
                        <a href="{{ $navCtaItem['url'] ?? '#' }}" class="nav-cta {{ $navCtaActive ? 'active' : '' }} {{ $hasRecentActivity ? 'has-recent-activity' : '' }}">
                            @if($navCtaSlug === 'activity')
                                @include('partials.ln-icon', ['name' => $navCtaLn, 'class' => 'nav-activity-bars'])
                                <i class="fa-solid fa-gamepad nav-cta-mobile-icon" aria-hidden="true"></i>
                            @else
                                @include('partials.ln-icon', ['name' => $navCtaLn])
                            @endif
                            <span>{{ $navCtaItem['title'] ?? '动态' }}</span>
                        </a>
                    @endif
                    @if(!empty($navMoreItems))
                        <button type="button" class="nav-more-toggle" data-nav-more aria-label="更多" aria-expanded="false" aria-haspopup="true">
                            @include('partials.ln-icon', ['name' => 'menu', 'class' => 'nav-more-bars'])
                            @include('partials.ln-icon', ['name' => 'x', 'class' => 'nav-more-close'])
                            <span>更多</span>
                        </button>
                        <div class="nav-more-menu" data-nav-more-menu role="menu" aria-label="更多页面">
                            @foreach($navMoreItems as $moreItem)
                                @php $moreLnIcon = $emberLnIconBySlug[$moreItem['slug'] ?? ''] ?? ''; @endphp
                                <a href="{{ $moreItem['url'] ?? '#' }}" class="nav-more-item" role="menuitem">
                                    @if($moreLnIcon !== '')
                                        @include('partials.ln-icon', ['name' => $moreLnIcon, 'class' => 'nav-link-icon'])
                                    @else
                                        <i class="{{ $moreItem['icon'] ?? 'fa-regular fa-file-lines' }} nav-link-icon" aria-hidden="true"></i>
                                    @endif
                                    <span>{{ $moreItem['title'] ?? '' }}</span>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
            <div class="nav-drawer">
                {{-- inner 不带内边距，收起时 0fr 轨道才能真正压到 0 --}}
                <div class="nav-drawer-inner">
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
        </div>

        {{-- 右：搜索、主题与账号工具区 --}}
        <div class="nav-tools" aria-label="快捷操作">
            <button type="button" class="nav-tool-btn" data-search-toggle aria-label="搜索" title="搜索">
                @include('partials.ln-icon', ['name' => 'search'])
            </button>
            <button type="button" class="nav-tool-btn nav-tool-theme" data-theme-toggle aria-label="切换深色模式" title="切换深色模式">
                <span class="side-theme-icon" aria-hidden="true">
                    <span class="theme-icon-moon">@include('partials.ln-icon', ['name' => 'moon'])</span>
                    <span class="theme-icon-sun">@include('partials.ln-icon', ['name' => 'sun'])</span>
                </span>
                <span class="side-theme-label" data-theme-label hidden>深色模式</span>
            </button>
            @if(!empty($currentAdmin))
                <a class="nav-tool-btn" href="/admin" aria-label="进入后台" title="进入后台">
                    @include('partials.ln-icon', ['name' => 'gauge'])
                </a>
                <form method="post" action="/admin/logout" class="nav-tool-form">
                    <input type="hidden" name="_csrf" value="{{ \App\Core\Session::csrfToken() }}">
                    <button type="submit" class="nav-tool-btn" aria-label="登出" title="登出">
                        <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i>
                    </button>
                </form>
            @else
                <div class="nav-tool-identity side-identity" data-side-identity>
                    <button type="button" class="nav-tool-btn nav-account-btn" data-account-open data-login-open aria-label="账号与身份">
                        <img class="side-identity-avatar" data-side-identity-avatar alt="" hidden>
                        <span class="side-identity-fallback" aria-hidden="true">@include('partials.ln-icon', ['name' => 'user'])</span>
                    </button>
                    <div class="side-identity-card" aria-hidden="true">
                        <span class="side-identity-name" data-side-identity-name></span>
                        <span class="side-identity-stat" data-side-identity-stat>设置评论身份 / 注册</span>
                    </div>
                </div>
                @if(!empty($currentMember))
                    <form method="post" action="/admin/logout" class="nav-tool-form">
                        <input type="hidden" name="_csrf" value="{{ \App\Core\Session::csrfToken() }}">
                        <button type="submit" class="nav-tool-btn" aria-label="登出" title="登出">
                            <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i>
                        </button>
                    </form>
                @endif
            @endif
        </div>
    </nav>

    <div class="site-search-overlay" data-search-overlay hidden>
        <button type="button" class="site-search-close" data-search-close aria-label="关闭搜索">
            @include('partials.ln-icon', ['name' => 'x'])
        </button>
        <div class="site-search-panel" role="search" aria-label="站内搜索">
            <form action="/search" method="get" class="site-search-pop-form">
                <label class="site-search-field">
                    <span class="site-search-ico" aria-hidden="true">@include('partials.ln-icon', ['name' => 'search'])</span>
                    <input type="search" name="q" value="{{ $keyword ?? '' }}" placeholder="搜文章、说说、音乐、页面…" autocomplete="off" data-search-input>
                </label>
                <div class="site-search-actions">
                    <span class="site-search-hint">Esc 关闭</span>
                    <button type="submit" class="site-search-submit">搜索</button>
                </div>
            </form>
        </div>
    </div>

    @if(empty($currentAdmin))
    @php
        $accountMemberLoggedIn = !empty($currentMember);
    @endphp
    <div class="login-overlay account-overlay" data-login-overlay data-account-overlay hidden>
        <div class="login-modal account-modal" role="dialog" aria-modal="true" aria-label="账号与评论身份" data-account-member="{{ $accountMemberLoggedIn ? '1' : '0' }}">
            <button type="button" class="login-modal-close" data-login-close data-account-close aria-label="关闭"><i class="fa-solid fa-circle-xmark" aria-hidden="true"></i></button>
            @if(!$accountMemberLoggedIn)
            <div class="account-tabs" role="tablist" aria-label="账号面板">
                <button type="button" class="account-tab is-active" role="tab" aria-selected="true" data-account-tab="identity">身份信息</button>
                <button type="button" class="account-tab" role="tab" aria-selected="false" data-account-tab="register">注册</button>
            </div>
            @else
            <div class="login-modal-head account-modal-head">
                <span class="login-modal-icon"><i class="fa-regular fa-circle-user" aria-hidden="true"></i></span>
                <div>
                    <p class="login-modal-title">评论身份</p>
                    <p class="login-modal-subtitle">读者 · {{ $currentMember->nickname ?: $currentMember->username }}</p>
                </div>
            </div>
            <div class="account-passkey-bar">
                <button type="button" class="login-modal-passkey" data-bind-passkey>@include('partials.ln-icon', ['name' => 'key']) 绑定 Passkey</button>
                <p class="login-modal-hint">绑定后可用指纹 / Face ID / 安全密钥登录</p>
            </div>
            @endif

            <div class="account-panel" data-account-panel="identity">
                @if(!$accountMemberLoggedIn)
                <div class="login-modal-head account-modal-head">
                    <span class="login-modal-icon"><i class="fa-regular fa-circle-user" aria-hidden="true"></i></span>
                    <div>
                        <p class="login-modal-title">评论身份</p>
                        <p class="login-modal-subtitle">保存后评论表单会自动使用这份资料</p>
                    </div>
                </div>
                @endif
                <form class="login-modal-form" data-identity-form>
                    <div class="account-identity-preview-wrap">
                        <img class="nav-identity-preview account-identity-preview" alt="">
                    </div>
                    <label class="login-modal-field"><i class="fa-regular fa-circle-user" aria-hidden="true"></i><input name="nickname" placeholder="昵称 *" required maxlength="50"></label>
                    <label class="login-modal-field"><i class="fa-regular fa-envelope" aria-hidden="true"></i><input name="email" type="email" placeholder="邮箱 *" required></label>
                    <label class="login-modal-field"><i class="fa-solid fa-link" aria-hidden="true"></i><input name="website" placeholder="网站（选填）"></label>
                    <label class="login-modal-field login-modal-captcha nav-identity-captcha" data-nav-identity-captcha hidden>
                        <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
                        <input name="captcha" placeholder="验证码 *" autocomplete="off" maxlength="4">
                        <img class="login-modal-captcha-img nav-identity-captcha-img" data-nav-identity-captcha-img src="" alt="点击刷新验证码" title="看不清？点击刷新">
                    </label>
                    <p class="login-modal-hint" data-nav-identity-captcha-tip hidden>首次用此邮箱评论需填验证码，通过后以后免验。</p>
                    <p class="login-modal-error" data-identity-error hidden></p>
                    <div class="account-identity-actions">
                        <button type="button" class="login-modal-ghost" data-nav-identity-clear>清除</button>
                        <button type="submit" class="login-modal-submit">保存</button>
                    </div>
                </form>
            </div>

            <div class="account-panel" data-account-panel="register" hidden>
                <div class="account-auth-mode" data-auth-mode-panel="register">
                    <div class="login-modal-head account-modal-head">
                        <span class="login-modal-icon"><i class="fa-solid fa-user-plus" aria-hidden="true"></i></span>
                        <div>
                            <p class="login-modal-title">注册读者账号</p>
                            <p class="login-modal-subtitle">需验证邮箱后才能登录 · 读者身份，非管理员</p>
                        </div>
                    </div>
                    <form class="login-modal-form" data-register-form>
                        <input type="hidden" name="_csrf" value="{{ \App\Core\Session::csrfToken() }}">
                        <label class="login-modal-field">@include('partials.ln-icon', ['name' => 'user'])<input name="username" placeholder="用户名 *" autocomplete="username" required pattern="[A-Za-z0-9_]{3,30}" title="3–30 位字母、数字或下划线"></label>
                        <label class="login-modal-field">@include('partials.ln-icon', ['name' => 'lock'])<input name="password" type="password" placeholder="密码 *（至少 6 位）" autocomplete="new-password" required minlength="6"></label>
                        <label class="login-modal-field"><i class="fa-regular fa-circle-user" aria-hidden="true"></i><input name="nickname" placeholder="昵称 *" autocomplete="nickname" required maxlength="50"></label>
                        <label class="login-modal-field"><i class="fa-regular fa-envelope" aria-hidden="true"></i><input name="email" type="email" placeholder="邮箱 *（用于验证）" autocomplete="email" required></label>
                        <label class="login-modal-field"><i class="fa-solid fa-link" aria-hidden="true"></i><input name="website" placeholder="网站（选填）" autocomplete="url"></label>
                        <label class="login-modal-field login-modal-captcha">
                            <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
                            <input name="captcha" placeholder="验证码 *" autocomplete="off" maxlength="4" required>
                            <img class="login-modal-captcha-img" data-register-captcha-img src="/captcha?t={{ time() }}" alt="点击刷新验证码" title="看不清？点击刷新">
                        </label>
                        <p class="login-modal-hint">注册后会发送验证邮件，点击链接激活后即可登录。用户名 admin / xifeng 及昵称「西风」等不可用。</p>
                        <p class="login-modal-error" data-register-error hidden></p>
                        <p class="login-modal-success" data-register-success hidden></p>
                        <button type="submit" class="login-modal-submit"><i class="fa-solid fa-user-plus" aria-hidden="true"></i> 注册并发送验证邮件</button>
                        <button type="button" class="login-modal-switch" data-auth-mode="login">已有账号？去登录</button>
                    </form>
                </div>
                <div class="account-auth-mode" data-auth-mode-panel="login" hidden>
                    <div class="login-modal-head account-modal-head">
                        <span class="login-modal-icon"><i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i></span>
                        <div>
                            <p class="login-modal-title">登录</p>
                            <p class="login-modal-subtitle">{{ $site['title'] ?? 'LiteNote' }} 账号入口</p>
                        </div>
                    </div>
                    <form class="login-modal-form" data-login-form>
                        <input type="hidden" name="_csrf" value="{{ \App\Core\Session::csrfToken() }}">
                        <label class="login-modal-field">@include('partials.ln-icon', ['name' => 'user'])<input name="username" placeholder="用户名" autocomplete="username" required></label>
                        <label class="login-modal-field">@include('partials.ln-icon', ['name' => 'lock'])<input name="password" type="password" placeholder="密码" autocomplete="current-password" required></label>
                        <p class="login-modal-error" data-login-error hidden></p>
                        <button type="submit" class="login-modal-submit"><i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i> 登录</button>
                        <button type="button" class="login-modal-passkey" data-login-passkey>@include('partials.ln-icon', ['name' => 'key']) 使用 Passkey 登录</button>
                        <button type="button" class="login-modal-switch" data-resend-verify hidden>重发验证邮件</button>
                        <a class="login-modal-forgot" href="/admin/forgot">忘记密码？</a>
                        <button type="button" class="login-modal-switch" data-auth-mode="register">没有账号？去注册</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    @endif

    <div class="container {{ ($activeNav ?? '') === 'archives' ? 'container-archive' : '' }}">
        <main class="site-main">
