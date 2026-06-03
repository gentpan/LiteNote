@extends('layouts.admin_auth')

@section('content')
    <div class="login-card">
        <h1><i class="fa-regular fa-note-sticky"></i> LiteNote 后台</h1>
        <p class="subtitle">请登录</p>
        @if($error)
            <div class="alert alert-error">{{ $error }}</div>
        @endif
        <form method="post" action="/admin/login">
            <input type="hidden" name="_csrf" value="{{ $csrf }}">
            <div class="form-group">
                <label>用户名</label>
                <input type="text" name="username" required autofocus>
            </div>
            <div class="form-group">
                <label>密码</label>
                <input type="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary btn-block"><i class="fa-solid fa-right-to-bracket"></i> 登录</button>
        </form>
        <p class="hint">默认 admin / admin123（首次登录请修改）</p>
    </div>
@endsection
