<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $pageTitle ?? '后台' }} - LiteNote Admin</title>
    <link rel="stylesheet" href="/assets/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/admin.css?v=1780502063">
</head>
<body class="admin-body">
    <aside class="admin-sidebar">
        <div class="admin-brand">
            <a href="/admin/profile" class="admin-brand-link" title="个人资料">
                @php
                    // 当前管理员头像(从 session id 查 user,失败 fallback identicon)
                    $__aid = (int)(\App\Core\Session::get('admin_user.id', 0) ?? 0);
                    $__u = $__aid > 0 ? \App\Models\User::find($__aid) : null;
                @endphp
                <img class="admin-brand-avatar"
                     src="{{ $__u ? $__u->getAvatarUrl(40) : '' }}"
                     alt="{{ $admin['nickname'] ?? 'Admin' }}"
                     width="36" height="36" loading="lazy">
                <div class="admin-brand-text">
                    <h1><i class="fa-regular fa-note-sticky"></i> {{ $admin['nickname'] ?? 'LiteNote' }}</h1>
                    <p>{{ $admin['role'] ?? 'admin' }} · v1.0 后台</p>
                </div>
            </a>
        </div>
        <nav class="admin-menu">
            <a href="/admin" class="{{ ($pageTitle ?? '') === '仪表盘' ? 'active' : '' }}"><i class="fa-solid fa-house"></i> 仪表盘</a>
            <div class="menu-group">内容</div>
            <a href="/admin/posts"><i class="fa-regular fa-file-lines"></i> 文章</a>
            <a href="/admin/pages"><i class="fa-regular fa-bookmark"></i> 页面</a>
            <a href="/admin/categories"><i class="fa-solid fa-folder"></i> 分类</a>
            <a href="/admin/shuoshuo"><i class="fa-regular fa-comments"></i> 说说</a>
            <a href="/admin/comments"><i class="fa-regular fa-comment-dots"></i> 评论</a>
            <div class="menu-group">资源</div>
            <a href="/admin/attachments"><i class="fa-solid fa-paperclip"></i> 附件</a>
            <a href="/admin/links"><i class="fa-solid fa-link"></i> 友情链接</a>
            <div class="menu-group">系统</div>
            <a href="/admin/stats"><i class="fa-solid fa-chart-column"></i> 统计</a>
            <a href="/admin/settings"><i class="fa-solid fa-gear"></i>️ 设置</a>
            <a href="/admin/profile"><i class="fa-regular fa-user"></i> 个人资料</a>
            <div class="menu-group">前端</div>
            <a href="/" target="_blank"><i class="fa-solid fa-globe"></i> 查看网站</a>
            <a href="/admin/logout"><i class="fa-solid fa-door-open"></i> 退出</a>
        </nav>
    </aside>
    <div class="admin-wrapper">
        <header class="admin-header">
            <h2>{{ $pageTitle ?? '后台' }}</h2>
            <div class="admin-user">
                <span><i class="fa-regular fa-user"></i> {{ $admin['nickname'] ?? $admin['username'] ?? 'Admin' }}</span>
            </div>
        </header>
        <main class="admin-content">
            @if(\App\Core\Session::getFlash('success'))
                <div class="alert alert-success">{{ \App\Core\Session::getFlash('success') }}</div>
            @endif
            @if(\App\Core\Session::getFlash('error'))
                <div class="alert alert-error">{{ \App\Core\Session::getFlash('error') }}</div>
            @endif
            @yield('content')
        </main>
    </div>
    <script src="/assets/js/admin.js"></script>
</body>
</html>
