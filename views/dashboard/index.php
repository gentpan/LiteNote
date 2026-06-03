@extends('layouts.admin')

@section('content')
    <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-num">{{ $stats['posts'] }}</div>
            <div class="stat-label">文章总数</div>
        </div>
        <div class="stat-card">
            <div class="stat-num">{{ $stats['comments'] }}</div>
            <div class="stat-label">评论总数</div>
        </div>
        <div class="stat-card">
            <div class="stat-num">{{ $stats['pending'] }}</div>
            <div class="stat-label">待审核评论</div>
        </div>
        <div class="stat-card">
            <div class="stat-num">{{ $stats['today']['pv'] }}</div>
            <div class="stat-label">今日 PV</div>
        </div>
        <div class="stat-card">
            <div class="stat-num">{{ $stats['today']['uv'] }}</div>
            <div class="stat-label">今日 UV</div>
        </div>
        <div class="stat-card">
            <div class="stat-num">{{ $stats['total']['pv'] }}</div>
            <div class="stat-label">总 PV</div>
        </div>
    </div>

    <div class="admin-row">
        <section class="admin-col">
            <h3>最近文章</h3>
            <table class="admin-table">
                <thead><tr><th>标题</th><th>状态</th><th>时间</th></tr></thead>
                <tbody>
                    @foreach($latestPosts as $p)
                    <tr>
                        <td><a href="/post/{{ $p->slug }}.html" target="_blank">{{ $p->title }}</a></td>
                        <td><span class="status status-{{ $p->status }}">{{ $p->status }}</span></td>
                        <td>{!! \App\Core\Helper::timeTag($p->created_at) !!}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            <a class="btn" href="/admin/posts">查看全部 →</a>
        </section>
        <section class="admin-col">
            <h3>待审核评论</h3>
            @if(empty($pendingComments))
                <p class="empty">无待审核评论</p>
            @else
            <ul class="pending-comments">
                @foreach($pendingComments as $c)
                <li>
                    <strong>{{ $c['nickname'] }}</strong> 在
                    @if($c['post_id'])
                        <a href="/post/{{ $c['target_slug'] }}.html" target="_blank">{{ $c['target_title'] }}</a>
                    @else
                        页面 {{ $c['target_title'] }}
                    @endif
                    <p>{{ $c['content'] }}</p>
                    <a class="btn btn-sm" href="/admin/comments?status=pending">去处理 →</a>
                </li>
                @endforeach
            </ul>
            @endif
        </section>
    </div>
@endsection
