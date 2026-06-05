@extends('layouts.admin')

@section('content')
    @php
        $statCards = [
            ['label' => '文章总数', 'value' => $stats['posts'] ?? 0, 'icon' => 'fa-regular fa-file-lines', 'tone' => 'blue'],
            ['label' => '评论总数', 'value' => $stats['comments'] ?? 0, 'icon' => 'fa-regular fa-comment-dots', 'tone' => 'green'],
            ['label' => '待审核评论', 'value' => $stats['pending'] ?? 0, 'icon' => 'fa-solid fa-shield-halved', 'tone' => 'amber'],
            ['label' => '今日 PV', 'value' => $stats['today']['pv'] ?? 0, 'icon' => 'fa-regular fa-eye', 'tone' => 'blue'],
            ['label' => '今日 UV', 'value' => $stats['today']['uv'] ?? 0, 'icon' => 'fa-regular fa-user', 'tone' => 'cyan'],
            ['label' => '近 30 日 PV', 'value' => $stats['total']['pv'] ?? 0, 'icon' => 'fa-solid fa-chart-line', 'tone' => 'violet'],
        ];
        $quickActions = [
            ['label' => '写文章', 'href' => '/admin/posts/create', 'icon' => 'fa-regular fa-pen-to-square'],
            ['label' => '发布滔客', 'href' => '/admin/talk/create', 'icon' => 'fa-regular fa-comments'],
            ['label' => '管理附件', 'href' => '/admin/attachments', 'icon' => 'fa-solid fa-paperclip'],
            ['label' => '系统设置', 'href' => '/admin/settings', 'icon' => 'fa-solid fa-gear'],
        ];
        $statusLabels = [
            'published' => '已发布',
            'draft' => '草稿',
            'pending' => '待审核',
            'approved' => '已通过',
            'spam' => '垃圾',
        ];
    @endphp

    <div class="stat-grid dashboard-stat-grid">
        @foreach($statCards as $card)
            <div class="stat-card stat-card-{{ $card['tone'] }}">
                <div class="stat-card-head">
                    <span class="stat-icon"><i class="{{ $card['icon'] }}"></i></span>
                    <span class="stat-label">{{ $card['label'] }}</span>
                </div>
                <div class="stat-num">{{ number_format((int)$card['value']) }}</div>
            </div>
        @endforeach
    </div>

    <div class="dashboard-actions">
        @foreach($quickActions as $action)
            <a href="{{ $action['href'] }}" class="dashboard-action">
                <span><i class="{{ $action['icon'] }}"></i></span>
                <strong>{{ $action['label'] }}</strong>
                <i class="fa-solid fa-angle-right"></i>
            </a>
        @endforeach
    </div>

    <div class="admin-row">
        <section class="admin-col">
            <div class="admin-col-head">
                <h3><i class="fa-regular fa-clock"></i> 最近文章</h3>
                <a class="btn btn-sm dashboard-more" href="/admin/posts"><i class="fa-regular fa-file-lines"></i> 查看全部</a>
            </div>
            <table class="admin-table">
                <thead><tr><th>标题</th><th>状态</th><th>浏览</th><th>评论</th><th>时间</th></tr></thead>
                <tbody>
                    @if(empty($latestPosts))
                        <tr><td colspan="5" class="table-empty">暂无文章</td></tr>
                    @else
                        @foreach($latestPosts as $p)
                        <tr>
                            <td>
                                <a class="dashboard-post-link" href="/post/{{ $p->slug }}.html" target="_blank">
                                    <i class="fa-regular fa-file-lines"></i>
                                    <span>{{ $p->title }}</span>
                                </a>
                            </td>
                            <td><span class="status status-{{ $p->status }}">{{ $statusLabels[$p->status] ?? $p->status }}</span></td>
                            <td><span class="dashboard-metric"><i class="fa-regular fa-eye"></i>{{ number_format((int)($p->views ?? 0)) }}</span></td>
                            <td><span class="dashboard-metric"><i class="fa-regular fa-comment"></i>{{ number_format((int)($p->comments_count ?? 0)) }}</span></td>
                            <td>{!! \App\Core\Helper::dateTimeTag($p->created_at) !!}</td>
                        </tr>
                        @endforeach
                    @endif
                </tbody>
            </table>
        </section>
        <section class="admin-col">
            <h3><i class="fa-regular fa-comment-dots"></i> 待审核评论</h3>
            @if(empty($pendingComments))
                <p class="empty dashboard-empty"><span class="admin-check-icon" aria-hidden="true"><i class="fa-solid fa-check"></i></span> 无待审核评论</p>
            @else
            <ul class="pending-comments">
                @foreach($pendingComments as $c)
                <li>
                    <div class="pending-comment-head">
                        <span><i class="fa-regular fa-user"></i> <strong>{{ $c['nickname'] }}</strong></span>
                        @if($c['post_id'])
                            <a href="/post/{{ $c['target_slug'] }}.html" target="_blank"><i class="fa-regular fa-file-lines"></i> {{ $c['target_title'] }}</a>
                        @else
                            <span><i class="fa-regular fa-bookmark"></i> 页面 {{ $c['target_title'] }}</span>
                        @endif
                    </div>
                    <p>{{ $c['content'] }}</p>
                    <a class="btn btn-sm dashboard-more" href="/admin/comments?status=pending"><i class="fa-solid fa-arrow-right"></i> 去处理</a>
                </li>
                @endforeach
            </ul>
            @endif
        </section>
    </div>
@endsection
