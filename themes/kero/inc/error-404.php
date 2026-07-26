@extends('layouts.front')

@section('content')
    <div class="kero-error">
        <p class="kero-error-code">404</p>
        <p class="kero-error-title">页面不存在</p>
        <p class="kero-error-msg">{{ $message ?? '你访问的内容已移动或从未存在。' }}</p>
        <a class="kero-error-home" href="/">← 回到首页</a>
    </div>
@endsection
