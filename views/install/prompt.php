@extends('layouts.front')

@section('content')
    <div class="install-prompt">
        <h1><i class="fa-solid fa-hand-sparkles"></i> 欢迎使用 LiteNote</h1>
        <p>检测到系统尚未安装，请先执行安装。</p>
        <p>
            <a class="btn btn-primary" href="{{ $installUrl }}"><i class="fa-solid fa-rocket"></i> 立即安装</a>
        </p>
        <p class="hint">安装过程会创建数据表、写入默认设置和示例数据。</p>
    </div>
@endsection
