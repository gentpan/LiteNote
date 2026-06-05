@extends('layouts.admin_auth')

@section('content')
    <div class="login-card">
        <h1><i class="fa-solid fa-key"></i> 找回密码</h1>
        <p class="subtitle">通过邮箱重置后台密码</p>

        @if($error)
            <div hidden data-toast-type="error" data-toast-message="{{ $error }}"></div>
        @endif
        @if($success)
            <div hidden data-toast-type="success" data-toast-message="{{ $success }}"></div>
        @endif

        @if(!$mailEnabled)
            <div hidden data-toast-type="error" data-toast-message="后台尚未配置邮件发送服务,暂时无法通过邮箱找回密码。请使用 Passkey 登录,或在服务器上手动重置。"></div>
        @endif

        <form method="post" action="/admin/forgot">
            <input type="hidden" name="_csrf" value="{{ $csrf }}">
            <div class="form-group">
                <label>用户名或邮箱</label>
                <input type="text" name="account" required autofocus @if(!$mailEnabled) disabled @endif>
            </div>
            <button type="submit" class="btn btn-primary btn-block" @if(!$mailEnabled) disabled @endif>
                <i class="fa-solid fa-paper-plane"></i> 发送重置链接
            </button>
        </form>

        <p class="login-foot">
            <a href="/admin/login"><i class="fa-solid fa-arrow-left"></i> 返回登录</a>
        </p>
    </div>
@endsection
