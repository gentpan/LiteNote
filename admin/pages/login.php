@extends('layouts.admin_auth')

@section('content')
    <div class="login-card">
        <h1>
            <span class="login-logo" aria-hidden="true">
                <svg class="litenote-logo-svg" viewBox="128 128 784 784" version="1.1" xmlns="http://www.w3.org/2000/svg" focusable="false">
                    <path d="M164 144h84v744h-84c-11.05 0-20-8.95-20-20V164c0-11.05 8.95-20 20-20z" fill="currentColor"></path>
                    <path d="M272 144h595.24c11.05 0 20 8.95 20 20l0.76 564.32c-0.52 56-19.36 97.52-56 123.68-33.72 24-82.04 36.28-143.8 36.28-5.32 0-10.68 0-16.2-0.28H272V144z" fill="currentColor"></path>
                    <path d="M604 144h192v332.44l-94.32-42.88L604 476.24V144z" fill="currentColor"></path>
                </svg>
            </span>
            LiteNote 后台
        </h1>
        <p class="subtitle">请登录</p>
        @if($error)
            <div hidden data-toast-type="error" data-toast-message="{{ $error }}"></div>
        @endif
        @if(\App\Core\Session::hasFlash('reset_success'))
            <div hidden data-toast-type="success" data-toast-message="{{ \App\Core\Session::getFlash('reset_success') }}"></div>
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

        <button type="button" id="passkey-login-btn" class="btn btn-dark btn-block passkey-btn">
            <i class="fa-solid fa-key"></i> 使用 Passkey 登录
        </button>

        <p class="login-foot">
            <a href="/admin/forgot">忘记密码?</a>
        </p>

        <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.getElementById('passkey-login-btn').addEventListener('click', async () => {
                try {
                    const result = await loginWithPasskey();
                    if (result.success) {
                        window.location.href = '/admin';
                    } else {
                        window.adminToast && window.adminToast(result.message || 'Passkey 登录失败', 'error');
                    }
                } catch (e) {
                    window.adminToast && window.adminToast('Passkey 登录失败: ' + e.message, 'error');
                }
            });
        });
        </script>
    </div>
@endsection
