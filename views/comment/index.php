@extends('layouts.admin')

@section('content')
    @php
        $commentStatusLabels = \App\Enums\CommentStatus::options();
    @endphp
    <div class="admin-toolbar">
        <a class="btn" href="/admin/comments?status=all">全部</a>
        <a class="btn" href="/admin/comments?status=pending">待审核</a>
        <a class="btn" href="/admin/comments?status=approved">已通过</a>
        <a class="btn" href="/admin/comments?status=spam">垃圾</a>
    </div>
    <table class="admin-table">
        <thead>
            <tr><th>ID</th><th>用户</th><th>目标</th><th>内容</th><th>状态</th><th>时间</th><th>操作</th></tr>
        </thead>
        <tbody>
            @foreach($comments as $c)
            <tr>
                <td>{{ $c['id'] }}</td>
                <td>
                    <div class="cmt-user-cell">
                        <img class="avatar avatar-sm" src="{{ \App\Services\Gravatar::url((string)($c['email'] ?? ''), 36) }}" alt="" loading="lazy" width="36" height="36">
                        <div>
                            <div>{{ $c['nickname'] }}</div>
                            @if($c['email'])<small class="muted">{{ $c['email'] }}</small>@endif
                        </div>
                    </div>
                </td>
                <td>
                    @if($c['post_id'])
                        <a href="/post/{{ $c['target_slug'] }}.html" target="_blank"><i class="fa-regular fa-file-lines"></i> {{ $c['target_title'] }}</a>
                    @elseif($c['page_id'])
                        <a href="/{{ $c['target_slug'] }}" target="_blank"><i class="fa-regular fa-bookmark"></i> {{ $c['target_title'] }}</a>
                    @elseif($c['talk_id'])
                        <a href="/#talk-{{ $c['talk_id'] }}" target="_blank"><i class="fa-regular fa-comments"></i> {{ $c['target_title'] }}</a>
                    @elseif($c['music_id'])
                        <a href="/music#music-comments" target="_blank"><i class="fa-solid fa-music"></i> {{ $c['target_title'] }}</a>
                    @else
                        <span class="muted">无</span>
                    @endif
                </td>
                <td><div class="comment-cell">{{ $c['content'] }}</div></td>
                <td><span class="status status-{{ $c['status'] }}">{{ $commentStatusLabels[$c['status']] ?? $c['status'] }}</span></td>
                <td>{!! \App\Core\Helper::timeTag($c['created_at']) !!}</td>
                <td>
                    <div class="admin-action-bar">
                    @if($c['status'] !== 'approved')
                        <button type="button"
                                class="admin-action-btn admin-action-approve approve-cmt"
                                data-id="{{ $c['id'] }}"
                                title="通过"
                                aria-label="通过">
                            <span class="admin-check-icon" aria-hidden="true"><i class="fa-solid fa-check"></i></span>
                        </button>
                    @endif
                    @if($c['status'] !== 'spam')
                        <button type="button"
                                class="admin-action-btn admin-action-spam spam-cmt"
                                data-id="{{ $c['id'] }}"
                                title="标记垃圾"
                                aria-label="标记垃圾">
                            <i class="fa-solid fa-ban"></i>
                        </button>
                    @endif
                        <button type="button"
                                class="admin-action-btn admin-action-delete del-cmt"
                                data-id="{{ $c['id'] }}"
                                title="删除"
                                aria-label="删除">
                            <i class="fa-regular fa-trash-can"></i>
                        </button>
                    </div>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    {!! $paginator ?? '' !!}

    <script>
    const csrf = '{{ $csrf }}';
    function act(url, id, after) {
        const fd = new FormData();
        fd.append('_csrf', csrf);
        fd.append('id', id);
        fetch(url, { method: 'POST', body: fd }).then(r => r.json()).then(d => {
            if (d.code === 0) {
                after();
            } else {
                window.adminToast && window.adminToast(d.msg || '操作失败', 'error');
            }
        });
    }
    document.querySelectorAll('.approve-cmt').forEach(b => b.addEventListener('click', () => act('/admin/comments/approve', b.dataset.id, () => location.reload())));
    document.querySelectorAll('.spam-cmt').forEach(b => b.addEventListener('click', () => act('/admin/comments/spam', b.dataset.id, () => location.reload())));
    document.querySelectorAll('.del-cmt').forEach(b => b.addEventListener('click', () => {
        if (!window.adminConfirm) return;
        window.adminConfirm({
            title: '删除评论',
            message: '确定删除这条评论？此操作不可撤销。',
            confirmText: '确认删除',
            tone: 'danger'
        }).then(ok => {
            if (ok) act('/admin/comments/delete', b.dataset.id, () => location.reload());
        });
    }));
    </script>
@endsection
