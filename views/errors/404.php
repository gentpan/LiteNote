@extends('layouts.front')

@section('content')
    <div class="error-page">
        <h1>404</h1>
        <p>{{ $message ?? '页面不存在' }}</p>
        <p><a href="/">回到首页</a></p>
    </div>
@endsection
