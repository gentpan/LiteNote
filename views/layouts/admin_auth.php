<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $pageTitle ?? '后台' }} - LiteNote Admin</title>
    <link rel="stylesheet" href="/assets/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/admin.css?v=1780502063">
</head>
<body class="admin-body auth-body">
    <div class="auth-container">
        @yield('content')
    </div>
    <script src="/assets/js/admin.js"></script>
</body>
</html>
