<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $pageTitle ?? '后台' }} - LiteNote Admin</title>
    {!! \App\Services\FaviconService::adminHeadHtml() !!}
    <link rel="stylesheet" href="https://static.bluecdn.com/libs/fontawesome/7.3.0/css/all.min.css">
    @php
        $__adminCss = \App\Services\PublishedAsset::url('/admin/assets/css/admin.css');
        $__adminJs = \App\Services\PublishedAsset::url('/admin/assets/js/admin.js');
    @endphp
    <link rel="stylesheet" href="{{ $__adminCss }}?v={{ \App\Services\PublishedAsset::version('/admin/assets/css/admin.css') }}">
</head>
<body class="admin-body auth-body">
    <div class="auth-container">
        @yield('content')
    </div>
    <script src="{{ $__adminJs }}?v={{ \App\Services\PublishedAsset::version('/admin/assets/js/admin.js') }}"></script>
</body>
</html>
