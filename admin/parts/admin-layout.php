<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $pageTitle ?? '后台' }} - LiteNote Admin</title>
    {!! \App\Services\FaviconService::adminHeadHtml() !!}
    <link rel="stylesheet" href="https://static.bluecdn.com/libs/fontawesome/7.2.0/css/all.min.css">
    @php
        $__adminCss = '/admin/assets/css/admin.css';
        $__adminJs = '/admin/assets/js/admin.js';
    @endphp
    <link rel="stylesheet" href="{{ $__adminCss }}?v={{ @filemtime(BASE_PATH . $__adminCss) ?: time() }}">
</head>
<body class="admin-body">
    @php
        $__path = parse_url($_SERVER['REQUEST_URI'] ?? '/admin', PHP_URL_PATH) ?: '/admin';
        $__isActive = static function (string $href) use ($__path): bool {
            if ($href === '/admin') {
                return $__path === '/admin' || $__path === '/admin/';
            }
            return $__path === $href || str_starts_with($__path, rtrim($href, '/') . '/');
        };
        $__pageDescriptions = [
            '仪表盘' => '查看内容和评论的实时概览',
            '文章管理' => '管理长文、草稿、分类、置顶和推荐内容',
            '写文章' => '编辑 Markdown 正文、封面、摘要和发布状态',
            '导入 Markdown' => '从本地 Markdown 快速创建文章',
            '页面管理' => '维护关于、友链等自定义独立页面',
            '滔客管理' => '管理短内容、图片动态和心情标签',
            '写滔客' => '发布短动态、图片和心情标签',
            '音乐管理' => '维护独立音乐、封面、歌词和播放状态',
            '添加音乐' => '录入音频地址、唱片封面、歌词和展示排序',
            '编辑音乐' => '更新音频信息、歌词、封面和公开状态',
            '动态管理' => '维护 Activity 动态、可见性和手动生活记录',
            '动态同步' => '配置第三方平台 API，并把公开活动同步到本地动态库',
            '编辑动态' => '更新动态内容、时间、来源和可见性',
            '评论管理' => '审核评论、处理垃圾内容和回复状态',
            '附件管理' => '上传、浏览和复制站点媒体资源',
            '主题管理' => '管理前台主题包、截图、切换和删除',
            '插件管理' => '查看已安装插件包，预留扩展能力入口',
            '友情链接' => '维护友链、Logo、描述和订阅源',
            '系统设置' => '配置站点资料、阅读、评论和邮件通知',
            '个人资料' => '维护管理员信息、头像和社交链接',
        ];
        $__desc = $__pageDescriptions[$pageTitle ?? ''] ?? 'LiteNote 内容管理后台';
    @endphp
    <aside class="admin-sidebar">
        <div class="admin-brand">
            <a href="/admin" class="admin-brand-link" title="LiteNote 后台">
                <span class="admin-brand-logo" aria-hidden="true">
                    <svg class="litenote-logo-svg" viewBox="128 128 784 784" version="1.1" xmlns="http://www.w3.org/2000/svg" focusable="false">
                        <path d="M164 144h84v744h-84c-11.05 0-20-8.95-20-20V164c0-11.05 8.95-20 20-20z" fill="currentColor"></path>
                        <path d="M272 144h595.24c11.05 0 20 8.95 20 20l0.76 564.32c-0.52 56-19.36 97.52-56 123.68-33.72 24-82.04 36.28-143.8 36.28-5.32 0-10.68 0-16.2-0.28H272V144z" fill="currentColor"></path>
                        <path d="M604 144h192v332.44l-94.32-42.88L604 476.24V144z" fill="currentColor"></path>
                    </svg>
                </span>
                <div class="admin-brand-text">
                    <h1>LiteNote <span class="admin-version-badge" aria-label="LiteNote v1.0">v1.0</span></h1>
                </div>
            </a>
        </div>
        <nav class="admin-menu">
            <a href="/admin" class="{{ $__isActive('/admin') ? 'active' : '' }}"><i class="fa-solid fa-house"></i><span>仪表盘</span></a>
            <div class="menu-group">内容</div>
            <a href="/admin/posts" class="{{ $__isActive('/admin/posts') ? 'active' : '' }}"><i class="fa-regular fa-file-lines"></i><span>文章</span></a>
            <a href="/admin/pages" class="{{ $__isActive('/admin/pages') ? 'active' : '' }}"><i class="fa-regular fa-bookmark"></i><span>页面</span></a>
            <a href="/admin/talk" class="{{ $__isActive('/admin/talk') ? 'active' : '' }}"><i class="fa-regular fa-comments"></i><span>滔客</span></a>
            <a href="/admin/activities" class="{{ $__isActive('/admin/activities') ? 'active' : '' }}"><i class="fa-solid fa-chart-simple"></i><span>动态</span></a>
            <a href="/admin/music" class="{{ $__isActive('/admin/music') ? 'active' : '' }}"><i class="fa-solid fa-music"></i><span>音乐</span></a>
            <a href="/admin/comments" class="{{ $__isActive('/admin/comments') ? 'active' : '' }}"><i class="fa-regular fa-comment-dots"></i><span>评论</span></a>
            <div class="menu-group">资源</div>
            <a href="/admin/attachments" class="{{ $__isActive('/admin/attachments') ? 'active' : '' }}"><i class="fa-solid fa-paperclip"></i><span>附件</span></a>
            <a href="/admin/themes" class="{{ $__isActive('/admin/themes') ? 'active' : '' }}"><i class="fa-solid fa-palette"></i><span>主题</span></a>
            <a href="/admin/plugins" class="{{ $__isActive('/admin/plugins') ? 'active' : '' }}"><i class="fa-solid fa-plug"></i><span>插件</span></a>
            <a href="/admin/links" class="{{ $__isActive('/admin/links') ? 'active' : '' }}"><i class="fa-solid fa-link"></i><span>友情链接</span></a>
            <div class="menu-group">系统</div>
            <a href="/admin/settings" class="{{ $__isActive('/admin/settings') ? 'active' : '' }}"><i class="fa-solid fa-gear"></i><span>设置</span></a>
            <a href="/admin/profile" class="{{ $__isActive('/admin/profile') ? 'active' : '' }}"><i class="fa-regular fa-user"></i><span>个人资料</span></a>
        </nav>
        <div class="admin-sidebar-footer">
            <a href="/admin/logout" class="admin-sidebar-logout"><i class="fa-solid fa-door-open"></i><span>退出</span></a>
        </div>
    </aside>
    <div class="admin-wrapper">
        <header class="admin-header">
            <div class="admin-page-title">
                <h2>{{ $pageTitle ?? '后台' }}</h2>
                <p>{{ $__desc }}</p>
            </div>
            <div class="admin-user">
                <a href="/" target="_blank" class="admin-preview-link" title="查看网站" aria-label="查看网站"><i class="fa-solid fa-house"></i></a>
            </div>
        </header>
        @php
            $__toastSuccess = \App\Core\Session::getFlash('success');
            $__toastError = \App\Core\Session::getFlash('error');
        @endphp
        @if($__toastSuccess || $__toastError)
            <div class="admin-toast-stack" aria-live="polite" aria-atomic="true">
                @if($__toastSuccess)
                    <div class="admin-toast admin-toast-success" role="status">
                        <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                        <span class="admin-toast-message">{{ $__toastSuccess }}</span>
                    </div>
                @endif
                @if($__toastError)
                    <div class="admin-toast admin-toast-error" role="alert">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                        <span class="admin-toast-message">{{ $__toastError }}</span>
                    </div>
                @endif
            </div>
        @endif
        <main class="admin-content">
            @yield('content')
        </main>
    </div>
    <script src="{{ $__adminJs }}?v={{ @filemtime(BASE_PATH . $__adminJs) ?: time() }}"></script>
</body>
</html>
