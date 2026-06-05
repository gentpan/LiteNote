<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $pageTitle ?? '后台' }} - LiteNote Admin</title>
    <link rel="stylesheet" href="https://static.bluecdn.com/libs/fontawesome/7.2.0/css/all.min.css">
    @php
        $__adminCss = '/assets/css/admin.css';
        $__adminJs = '/assets/js/admin.js';
    @endphp
    <link rel="stylesheet" href="{{ $__adminCss }}?v={{ @filemtime(__DIR__ . '/../../public' . $__adminCss) ?: time() }}">
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
            '仪表盘' => '查看内容、评论和访问数据的实时概览',
            '文章管理' => '管理长文、草稿、分类、置顶和推荐内容',
            '写文章' => '编辑 Markdown 正文、封面、摘要和发布状态',
            '导入 Markdown' => '从本地 Markdown 快速创建文章',
            '页面管理' => '维护关于、友链等自定义独立页面',
            '分类管理' => '组织文章栏目、导航展示和分类色彩',
            '滔客管理' => '管理短内容、图片动态和心情标签',
            '写滔客' => '发布短动态、图片和心情标签',
            '音乐管理' => '维护独立音乐、封面、歌词和播放状态',
            '添加音乐' => '录入音频地址、唱片封面、歌词和展示排序',
            '编辑音乐' => '更新音频信息、歌词、封面和公开状态',
            '评论管理' => '审核评论、处理垃圾内容和回复状态',
            '附件管理' => '上传、浏览和复制站点媒体资源',
            '友情链接' => '维护友链、Logo、描述和订阅源',
            'Umami 统计' => '从自建 Umami API 读取访客、来源和页面数据',
            '系统设置' => '配置站点资料、功能开关、评论和 AI 服务',
            '个人资料' => '维护管理员信息、头像和社交链接',
        ];
        $__desc = $__pageDescriptions[$pageTitle ?? ''] ?? 'LiteNote 内容管理后台';
        $__aid = (int)(\App\Core\Session::get('admin_user.id', 0) ?? 0);
        $__u = $__aid > 0 ? \App\Models\User::find($__aid) : null;
        $__adminName = (string)($admin['nickname'] ?? $admin['username'] ?? 'Admin');
        $__adminRole = (string)($admin['role'] ?? 'admin');
        $__adminEmail = (string)($__u->email ?? '');
        $__adminAvatar = $__u ? $__u->getAvatarUrl(80) : \App\Services\Gravatar::url($__adminEmail, 80, 'identicon');
    @endphp
    <aside class="admin-sidebar">
        <div class="admin-brand">
            <a href="/admin/profile" class="admin-brand-link" title="个人资料">
                <img class="admin-brand-avatar"
                     src="{{ $__adminAvatar }}"
                     alt="{{ $__adminName }}"
                     width="36" height="36" loading="lazy">
                <div class="admin-brand-text">
                    <h1><i class="fa-regular fa-note-sticky"></i> {{ $__adminName ?: 'LiteNote' }}</h1>
                    <p>{{ $__adminRole }} · v1.0 后台</p>
                </div>
            </a>
        </div>
        <nav class="admin-menu">
            <a href="/admin" class="{{ $__isActive('/admin') ? 'active' : '' }}"><i class="fa-solid fa-house"></i><span>仪表盘</span></a>
            <div class="menu-group">内容</div>
            <a href="/admin/posts" class="{{ $__isActive('/admin/posts') ? 'active' : '' }}"><i class="fa-regular fa-file-lines"></i><span>文章</span></a>
            <a href="/admin/pages" class="{{ $__isActive('/admin/pages') ? 'active' : '' }}"><i class="fa-regular fa-bookmark"></i><span>页面</span></a>
            <a href="/admin/categories" class="{{ $__isActive('/admin/categories') ? 'active' : '' }}"><i class="fa-solid fa-folder"></i><span>分类</span></a>
            <a href="/admin/talk" class="{{ $__isActive('/admin/talk') ? 'active' : '' }}"><i class="fa-regular fa-comments"></i><span>滔客</span></a>
            <a href="/admin/music" class="{{ $__isActive('/admin/music') ? 'active' : '' }}"><i class="fa-solid fa-music"></i><span>音乐</span></a>
            <a href="/admin/comments" class="{{ $__isActive('/admin/comments') ? 'active' : '' }}"><i class="fa-regular fa-comment-dots"></i><span>评论</span></a>
            <div class="menu-group">资源</div>
            <a href="/admin/attachments" class="{{ $__isActive('/admin/attachments') ? 'active' : '' }}"><i class="fa-solid fa-paperclip"></i><span>附件</span></a>
            <a href="/admin/links" class="{{ $__isActive('/admin/links') ? 'active' : '' }}"><i class="fa-solid fa-link"></i><span>友情链接</span></a>
            <div class="menu-group">系统</div>
            <a href="/admin/stats" class="{{ $__isActive('/admin/stats') ? 'active' : '' }}"><i class="fa-solid fa-chart-column"></i><span>Umami</span></a>
            <a href="/admin/settings" class="{{ $__isActive('/admin/settings') ? 'active' : '' }}"><i class="fa-solid fa-gear"></i><span>设置</span></a>
            <a href="/admin/profile" class="{{ $__isActive('/admin/profile') ? 'active' : '' }}"><i class="fa-regular fa-user"></i><span>个人资料</span></a>
            <div class="menu-group">前端</div>
            <a href="/" target="_blank"><i class="fa-solid fa-globe"></i><span>查看网站</span></a>
            <a href="/admin/logout"><i class="fa-solid fa-door-open"></i><span>退出</span></a>
        </nav>
    </aside>
    <div class="admin-wrapper">
        <header class="admin-header">
            <div class="admin-page-title">
                <h2>{{ $pageTitle ?? '后台' }}</h2>
                <p>{{ $__desc }}</p>
            </div>
            <div class="admin-user">
                <a href="/" target="_blank" class="admin-preview-link"><i class="fa-solid fa-arrow-up-right-from-square"></i> 查看网站</a>
                <div class="admin-account">
                    <button type="button" class="admin-account-trigger" aria-label="管理员菜单">
                        <img src="{{ $__adminAvatar }}" alt="{{ $__adminName }}" width="38" height="38" loading="lazy">
                        <i class="fa-solid fa-chevron-down"></i>
                    </button>
                    <div class="admin-account-menu" role="menu">
                        <div class="admin-account-head">
                            <span>当前登录</span>
                            <div>
                                <img src="{{ $__adminAvatar }}" alt="{{ $__adminName }}" width="34" height="34" loading="lazy">
                                <strong>{{ $__adminEmail ?: $__adminName }}</strong>
                            </div>
                        </div>
                        <div class="admin-account-list">
                            <a href="/admin/profile" role="menuitem">
                                <span class="admin-account-menu-icon"><i class="fa-regular fa-user"></i></span>
                                <span>个人资料</span>
                                <i class="fa-solid fa-angle-right"></i>
                            </a>
                            <a href="/admin/settings" role="menuitem">
                                <span class="admin-account-menu-icon"><i class="fa-solid fa-gear"></i></span>
                                <span>系统设置</span>
                                <i class="fa-solid fa-angle-right"></i>
                            </a>
                            <a href="/" target="_blank" role="menuitem">
                                <span class="admin-account-menu-icon"><i class="fa-solid fa-globe"></i></span>
                                <span>查看网站</span>
                                <i class="fa-solid fa-angle-right"></i>
                            </a>
                            <a href="/admin/logout" class="admin-account-logout" role="menuitem">
                                <span class="admin-account-menu-icon"><i class="fa-solid fa-door-open"></i></span>
                                <span>退出登录</span>
                                <i class="fa-solid fa-angle-right"></i>
                            </a>
                        </div>
                    </div>
                </div>
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
                        <button type="button" class="admin-toast-close" aria-label="关闭提示"><i class="fa-solid fa-xmark"></i></button>
                    </div>
                @endif
                @if($__toastError)
                    <div class="admin-toast admin-toast-error" role="alert">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                        <span class="admin-toast-message">{{ $__toastError }}</span>
                        <button type="button" class="admin-toast-close" aria-label="关闭提示"><i class="fa-solid fa-xmark"></i></button>
                    </div>
                @endif
            </div>
        @endif
        <main class="admin-content">
            @yield('content')
        </main>
    </div>
    <script src="{{ $__adminJs }}?v={{ @filemtime(__DIR__ . '/../../public' . $__adminJs) ?: time() }}"></script>
</body>
</html>
