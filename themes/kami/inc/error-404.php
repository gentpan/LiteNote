@extends('layouts.front')

@section('content')
    <div class="kami-error">
        <p class="kami-error-code">404</p>
        <p class="kami-error-title">页面不存在</p>
        <p class="kami-error-msg">{{ $message ?? '你访问的内容已移动或从未存在。' }}</p>
        <a class="kami-error-home" href="/">← 回到首页</a>
    </div>
@endsection
