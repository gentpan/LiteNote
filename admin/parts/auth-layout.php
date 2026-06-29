<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $pageTitle ?? '后台' }} - LiteNote Admin</title>
    {!! \App\Services\FaviconService::adminHeadHtml() !!}
    <link rel="stylesheet" href="https://static.bluecdn.com/libs/fontawesome/7.3.0/css/all.min.css">
    @php
        $__adminCss = '/admin/assets/css/admin.css';
        $__adminJs = '/admin/assets/js/admin.js';
    @endphp
    <link rel="stylesheet" href="{{ $__adminCss }}?v={{ @filemtime(BASE_PATH . $__adminCss) ?: time() }}">
</head>
<body class="admin-body auth-body">
    <div class="auth-container">
        @yield('content')
    </div>
    <script src="{{ $__adminJs }}?v={{ @filemtime(BASE_PATH . $__adminJs) ?: time() }}"></script>
</body>
</html>
