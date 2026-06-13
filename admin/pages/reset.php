@extends('layouts.admin_auth')

@section('content')
    <div class="login-card">
        <h1><i class="fa-solid fa-lock"></i> 重置密码</h1>
        <p class="subtitle">设置一个新的登录密码</p>

        @if($error)
            <div hidden data-toast-type="error" data-toast-message="{{ $error }}"></div>
        @endif

        @if(!$valid)
            <div hidden data-toast-type="error" data-toast-message="重置链接无效或已过期,请重新申请。"></div>
            <p class="login-foot">
                <a href="/admin/forgot"><i class="fa-solid fa-rotate-right"></i> 重新申请</a>
            </p>
        @else
            <form method="post" action="/admin/reset">
                <input type="hidden" name="_csrf" value="{{ $csrf }}">
                <input type="hidden" name="token" value="{{ $token }}">
                <div class="form-group">
                    <label>新密码</label>
                    <input type="password" name="password" minlength="6" required autofocus placeholder="至少 6 位">
                </div>
                <div class="form-group">
                    <label>确认新密码</label>
                    <input type="password" name="password_confirm" minlength="6" required>
                </div>
                <button type="submit" class="btn btn-primary btn-block">
                    <span class="admin-check-icon" aria-hidden="true"><i class="fa-solid fa-check"></i></span> 确认重置
                </button>
            </form>
            <p class="login-foot">
                <a href="/?login=1"><i class="fa-solid fa-arrow-left"></i> 返回登录</a>
            </p>
        @endif
    </div>
@endsection
