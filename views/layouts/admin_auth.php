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
<body class="admin-body auth-body">
    <div class="auth-container">
        @yield('content')
    </div>
    <script src="{{ $__adminJs }}?v={{ @filemtime(__DIR__ . '/../../public' . $__adminJs) ?: time() }}"></script>
</body>
</html>
