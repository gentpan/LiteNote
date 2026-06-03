@extends('layouts.front')

@section('content')
    <div class="install-done">
        <h1><i class="fa-solid fa-party-horn"></i> 安装完成</h1>
        <p>数据库已初始化，示例数据已写入。</p>
        <h3>操作日志：</h3>
        <ul>
            @foreach($log as $line)
                <li>{{ $line }}</li>
            @endforeach
        </ul>
        <p>
            <a class="btn" href="/">访问首页</a>
            <a class="btn btn-primary" href="/admin/login">进入后台</a>
        </p>
        <p class="hint">默认管理员账号: <code>admin</code> / <code>admin123</code>（登录后请立即修改）</p>
    </div>
@endsection
