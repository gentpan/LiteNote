@extends('layouts.admin')

@section('content')
    <div class="admin-toolbar">
        <a class="btn btn-primary" href="/admin/posts/create"><i class="fa-solid fa-pen"></i> 写新文章</a>
        <a class="btn" href="/admin/posts/import"><i class="fa-solid fa-file-import"></i> 导入 Markdown</a>
        <form method="get" class="admin-search">
            <input type="text" name="q" value="{{ $keyword }}" placeholder="搜索标题...">
            <select name="status">
                <option value="">全部状态</option>
                <option value="published" {{ ($status ?? '') === 'published' ? 'selected' : '' }}>已发布</option>
                <option value="draft" {{ ($status ?? '') === 'draft' ? 'selected' : '' }}>草稿</option>
            </select>
            <button type="submit">筛选</button>
        </form>
    </div>

    <form method="post" action="/admin/posts/bulk">
        <input type="hidden" name="_csrf" value="{{ $csrf }}">
        <div class="bulk-bar">
            <select name="bulk_action">
                <option value="">批量操作</option>
                <option value="publish">发布</option>
                <option value="draft">转草稿</option>
                <option value="top">置顶</option>
                <option value="untop">取消置顶</option>
                <option value="delete">删除</option>
            </select>
            <button type="submit" onclick="return confirm('确定执行？')"><i class="fa-solid fa-check"></i> 应用</button>
        </div>
        <table class="admin-table">
            <thead>
                <tr>
                    <th><input type="checkbox" id="check-all"></th>
                    <th>ID</th>
                    <th>标题</th>
                    <th>分类</th>
                    <th>浏览</th>
                    <th>评论</th>
                    <th>状态</th>
                    <th>发布时间</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                @foreach($posts as $p)
                <tr>
                    <td><input type="checkbox" name="ids[]" value="{{ $p->id }}"></td>
                    <td>{{ $p->id }}</td>
                    <td>
                        @if($p->is_top)<span class="badge badge-top">顶</span>@endif
                        <a href="/post/{{ $p->slug }}.html" target="_blank">{{ $p->title }}</a>
                    </td>
                    <td>{{ $p->getCategory()?->name }}</td>
                    <td>{{ $p->views }}</td>
                    <td>{{ $p->comments_count }}</td>
                    <td><span class="status status-{{ $p->status }}">{{ $p->status }}</span></td>
                    <td>{{ \App\Core\Helper::humanDate($p->published_at) }}</td>
                    <td>
                        <a href="/admin/posts/{{ $p->id }}/edit" class="link-btn"><i class="fa-solid fa-pen-to-square"></i> 编辑</a>
                        <form method="post" action="/admin/posts/{{ $p->id }}/delete" style="display:inline" onsubmit="return confirm('确定删除？')">
                            <input type="hidden" name="_csrf" value="{{ $csrf }}">
                            <button type="submit" class="link-btn link-danger"><i class="fa-solid fa-trash"></i> 删除</button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </form>
    {!! $paginator ?? '' !!}

    <script>
    document.getElementById('check-all').addEventListener('change', function() {
        document.querySelectorAll('input[name="ids[]"]').forEach(cb => cb.checked = this.checked);
    });
    </script>
@endsection
