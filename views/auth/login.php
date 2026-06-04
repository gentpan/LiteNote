@extends('layouts.admin_auth')

@section('content')
    <div class="login-card">
        <h1><i class="fa-regular fa-note-sticky"></i> LiteNote 后台</h1>
        <p class="subtitle">请登录</p>
        @if($error)
            <div class="alert alert-error">{{ $error }}</div>
        @endif
        @if(\App\Core\Session::hasFlash('reset_success'))
            <div class="alert alert-success">{{ \App\Core\Session::getFlash('reset_success') }}</div>
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

        <script src="/assets/js/passkey.js"></script>
        <script>
        document.getElementById('passkey-login-btn').addEventListener('click', async () => {
            try {
                const result = await loginWithPasskey();
                if (result.success) {
                    window.location.href = '/admin';
                } else {
                    alert('Passkey 登录失败');
                }
            } catch (e) {
                alert('Passkey 登录失败: ' + e.message);
            }
        });
        </script>
    </div>
@endsection
