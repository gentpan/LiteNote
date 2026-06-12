@extends('layouts.front')

@section('content')
    <div class="error-page">
        <h1>404</h1>
        <p>{{ $message ?? '页面不存在' }}</p>
        <p><a class="btn" href="/"><i class="fa-solid fa-house" aria-hidden="true"></i> 回到首页</a></p>
    </div>
@endsection
