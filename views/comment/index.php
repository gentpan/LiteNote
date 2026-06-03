@extends('layouts.admin')

@section('content')
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
                        <a href="/page/{{ $c['target_slug'] }}.html" target="_blank"><i class="fa-regular fa-bookmark"></i> {{ $c['target_title'] }}</a>
                    @else
                        <span class="muted">无</span>
                    @endif
                </td>
                <td><div class="comment-cell">{{ $c['content'] }}</div></td>
                <td><span class="status status-{{ $c['status'] }}">{{ $c['status'] }}</span></td>
                <td>{!! \App\Core\Helper::timeTag($c['created_at']) !!}</td>
                <td>
                    @if($c['status'] !== 'approved')
                    <button type="button" class="link-btn approve-cmt" data-id="{{ $c['id'] }}">通过</button>
                    @endif
                    @if($c['status'] !== 'spam')
                    <button type="button" class="link-btn spam-cmt" data-id="{{ $c['id'] }}">垃圾</button>
                    @endif
                    <button type="button" class="link-btn link-danger del-cmt" data-id="{{ $c['id'] }}">删除</button>
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
        fetch(url, { method: 'POST', body: fd }).then(r => r.json()).then(d => { if (d.code === 0) after(); else alert(d.msg); });
    }
    document.querySelectorAll('.approve-cmt').forEach(b => b.addEventListener('click', () => act('/admin/comments/approve', b.dataset.id, () => location.reload())));
    document.querySelectorAll('.spam-cmt').forEach(b => b.addEventListener('click', () => act('/admin/comments/spam', b.dataset.id, () => location.reload())));
    document.querySelectorAll('.del-cmt').forEach(b => b.addEventListener('click', () => {
        if (!confirm('确定删除？')) return;
        act('/admin/comments/delete', b.dataset.id, () => location.reload());
    }));
    </script>
@endsection
