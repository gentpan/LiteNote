@extends('layouts.admin')

@section('content')
    @php
        $summary = $report['summary'] ?? [];
        $active = $report['active'] ?? [];
        $pages = $report['pages'] ?? [];
        $referrers = $report['referrers'] ?? [];
        $browsers = $report['browsers'] ?? [];
        $devices = $report['devices'] ?? [];
        $countries = $report['countries'] ?? [];
        $config = $report['config'] ?? [];
        $visits = max(0, (int)($summary['visits'] ?? 0));
        $bounces = max(0, (int)($summary['bounces'] ?? 0));
        $bounceRate = $visits > 0 ? round($bounces / $visits * 100, 1) . '%' : '-';
    @endphp

    <div class="admin-toolbar">
        <a class="btn {{ ($report['days'] ?? 7) === 7 ? 'btn-primary' : '' }}" href="/admin/stats?days=7">7 天</a>
        <a class="btn {{ ($report['days'] ?? 7) === 30 ? 'btn-primary' : '' }}" href="/admin/stats?days=30">30 天</a>
        <a class="btn" href="/admin/settings"><i class="fa-solid fa-gear"></i> 配置 Umami</a>
        @if(!empty($config['baseUrl']))
            <a class="btn" href="{{ $config['baseUrl'] }}" target="_blank" rel="nofollow noopener"><i class="fa-solid fa-arrow-up-right-from-square"></i> 打开 Umami</a>
        @endif
    </div>

    @if(empty($report['configured']))
        <div class="alert alert-info">
            <i class="fa-solid fa-chart-column"></i>
            <span>访问统计已切换为 Umami。请到「设置 → 统计设置」填写 Umami 地址、Website ID 和访问令牌/API Key。</span>
        </div>
        <div class="admin-col">
            <h3><i class="fa-solid fa-list-check"></i> 需要的配置</h3>
            <table class="admin-table">
                <tbody>
                    <tr><th>Umami 地址</th><td>例如 <code>https://umami.example.com</code></td></tr>
                    <tr><th>Website ID</th><td>Umami 网站设置里的 UUID</td></tr>
                    <tr><th>访问令牌</th><td>自建 Umami 使用 <code>Authorization: Bearer &lt;token&gt;</code></td></tr>
                    <tr><th>跟踪脚本</th><td>配置启用后前台会自动加载 <code>/script.js</code></td></tr>
                </tbody>
            </table>
        </div>
    @elseif(!empty($report['error']))
        <div class="alert alert-error">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <span>Umami API 请求失败：{{ $report['error'] }}</span>
        </div>
    @else
        <div class="stat-grid">
            <div class="stat-card">
                <div class="stat-num">{{ (int)($active['visitors'] ?? 0) }}</div>
                <div class="stat-label">实时访客</div>
            </div>
            <div class="stat-card">
                <div class="stat-num">{{ (int)($summary['pageviews'] ?? 0) }}</div>
                <div class="stat-label">页面浏览</div>
            </div>
            <div class="stat-card">
                <div class="stat-num">{{ (int)($summary['visitors'] ?? 0) }}</div>
                <div class="stat-label">独立访客</div>
            </div>
            <div class="stat-card">
                <div class="stat-num">{{ (int)($summary['visits'] ?? 0) }}</div>
                <div class="stat-label">访问次数</div>
            </div>
            <div class="stat-card">
                <div class="stat-num">{{ $bounceRate }}</div>
                <div class="stat-label">跳出率</div>
            </div>
        </div>

        <div class="admin-row">
            <section class="admin-col">
                <h3><i class="fa-solid fa-file-lines"></i> 热门页面</h3>
                @php $rows = $pages; $nameLabel = '路径'; @endphp
                @include('stat.metric-table')
            </section>
            <section class="admin-col">
                <h3><i class="fa-solid fa-link"></i> 来源</h3>
                @php $rows = $referrers; $nameLabel = '来源'; @endphp
                @include('stat.metric-table')
            </section>
        </div>

        <div class="admin-row" style="margin-top:22px">
            <section class="admin-col">
                <h3><i class="fa-brands fa-chrome"></i> 浏览器</h3>
                @php $rows = $browsers; $nameLabel = '浏览器'; @endphp
                @include('stat.metric-table')
            </section>
            <section class="admin-col">
                <h3><i class="fa-solid fa-mobile-screen"></i> 设备</h3>
                @php $rows = $devices; $nameLabel = '设备'; @endphp
                @include('stat.metric-table')
            </section>
        </div>

        <section class="admin-col" style="margin-top:22px">
            <h3><i class="fa-solid fa-globe"></i> 国家/地区</h3>
            @php $rows = $countries; $nameLabel = '国家/地区'; @endphp
            @include('stat.metric-table')
        </section>
    @endif
@endsection
