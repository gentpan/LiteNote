<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $pageTitle ?? '后台' }} - LiteNote Admin</title>
    <link rel="stylesheet" href="https://static.bluecdn.com/libs/fontawesome/7.2.0/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/admin.css?v={{ @filemtime(__DIR__ . '/../../public/assets/css/admin.css') ?: time() }}">
</head>
<body class="admin-body auth-body">
    <div class="auth-container">
        @yield('content')
    </div>
    <script src="/assets/js/admin.js"></script>
</body>
</html>
