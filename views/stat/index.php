@extends('layouts.admin')

@section('content')
    <div class="stat-grid">
        <div class="stat-card"><div class="stat-num">{{ $today['pv'] }}</div><div class="stat-label">今日 PV</div></div>
        <div class="stat-card"><div class="stat-num">{{ $today['uv'] }}</div><div class="stat-label">今日 UV</div></div>
        <div class="stat-card"><div class="stat-num">{{ $total['pv'] }}</div><div class="stat-label">总 PV</div></div>
        <div class="stat-card"><div class="stat-num">{{ $total['uv'] }}</div><div class="stat-label">总 UV</div></div>
    </div>

    <h3>最近 7 天</h3>
    <table class="admin-table">
        <thead><tr><th>日期</th><th>PV</th><th>UV</th></tr></thead>
        <tbody>
            @foreach($last7 as $row)
            <tr>
                <td>{{ $row['day'] }}</td>
                <td>{{ $row['pv'] }}</td>
                <td>{{ $row['uv'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <h3>热门文章</h3>
    <table class="admin-table">
        <thead><tr><th>路径</th><th>访问次数</th></tr></thead>
        <tbody>
            @foreach($topPosts as $r)
            <tr>
                <td><a href="{{ $r['path'] }}" target="_blank">{{ $r['path'] }}</a></td>
                <td>{{ $r['hits'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
@endsection
