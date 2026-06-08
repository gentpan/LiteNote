@extends('layouts.admin')

@section('content')
    @php
        $postStatusLabels = \App\Enums\PostStatus::options();
    @endphp
    <div class="admin-toolbar">
        <a class="btn btn-primary" href="/admin/posts/create"><i class="fa-solid fa-pen"></i> 写新文章</a>
        <a class="btn" href="/admin/posts/import"><i class="fa-solid fa-file-import"></i> 导入 Markdown</a>
        <button type="button" class="btn" data-open-category-dialog><i class="fa-solid fa-folder-tree"></i> 分类</button>
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

    @include('partials.admin-category-dialog')

    <form method="post" action="/admin/posts/bulk"
          data-no-dirty-form
          data-confirm="确定执行所选批量操作？"
          data-confirm-title="确认执行操作"
          data-confirm-tone="primary"
          data-confirm-text="确认执行">
        <input type="hidden" name="_csrf" value="{{ $csrf }}">
        <table class="admin-table admin-action-table admin-action-table-wide post-admin-table">
            <colgroup>
                <col class="post-col-check">
                <col class="post-col-id">
                <col class="post-col-title">
                <col class="post-col-category">
                <col class="post-col-views">
                <col class="post-col-comments">
                <col class="post-col-status">
                <col class="post-col-date">
                <col class="post-col-actions">
            </colgroup>
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
                        @if($p->is_top)<span class="badge badge-top" data-post-badge="is_top">顶</span>@endif
                        @if($p->is_recommend)<span class="badge badge-recommend" data-post-badge="is_recommend">荐</span>@endif
                        <a href="{{ $p->getUrl() }}" target="_blank">{{ $p->title }}</a>
                    </td>
                    <td>{{ $p->getCategory()?->name }}</td>
                    <td>{{ $p->views }}</td>
                    <td>{{ $p->comments_count }}</td>
                    <td><span class="status status-{{ $p->status }}">{{ $postStatusLabels[$p->status] ?? $p->status }}</span></td>
                    <td>{!! \App\Core\Helper::dateTimeTag($p->published_at) !!}</td>
                    <td>
                        <div class="post-action-bar">
                            <a href="/admin/posts/{{ $p->id }}/edit" class="post-action-btn post-action-edit" title="编辑" aria-label="编辑">
                                <i class="fa-regular fa-pen-to-square"></i>
                            </a>
                            <button type="button"
                                    class="post-action-btn post-action-top {{ $p->is_top ? 'is-active' : '' }}"
                                    title="{{ $p->is_top ? '取消置顶' : '置顶' }}"
                                    aria-label="{{ $p->is_top ? '取消置顶' : '置顶' }}"
                                    data-post-toggle
                                    data-field="is_top"
                                    data-id="{{ $p->id }}"
                                    data-active="{{ $p->is_top ? '1' : '0' }}"
                                    data-action="/admin/posts/{{ $p->id }}/toggle"
                                    data-csrf="{{ $csrf }}">
                                <i class="fa-solid fa-thumbtack"></i>
                            </button>
                            <button type="button"
                                    class="post-action-btn post-action-recommend {{ $p->is_recommend ? 'is-active' : '' }}"
                                    title="{{ $p->is_recommend ? '取消推荐' : '推荐' }}"
                                    aria-label="{{ $p->is_recommend ? '取消推荐' : '推荐' }}"
                                    data-post-toggle
                                    data-field="is_recommend"
                                    data-id="{{ $p->id }}"
                                    data-active="{{ $p->is_recommend ? '1' : '0' }}"
                                    data-action="/admin/posts/{{ $p->id }}/toggle"
                                    data-csrf="{{ $csrf }}">
                                <i class="fa-solid fa-star"></i>
                            </button>
                            <button type="submit"
                                    form="post-delete-form-{{ $p->id }}"
                                    class="post-action-btn post-action-delete"
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
        <div class="bulk-bar" data-bulk-bar hidden>
            <select name="bulk_action">
                <option value="">批量操作</option>
                <option value="publish">发布</option>
                <option value="draft">转草稿</option>
                <option value="top">置顶</option>
                <option value="untop">取消置顶</option>
                <option value="delete">删除</option>
            </select>
            <button type="submit"><i class="fa-solid fa-arrow-right"></i> 应用</button>
        </div>
    </form>

    @foreach($posts as $p)
        <form id="post-delete-form-{{ $p->id }}" method="post" action="/admin/posts/{{ $p->id }}/delete" class="hidden"
              data-no-dirty-form
              data-confirm="确定删除这篇文章？删除后关联评论也会一并移除，此操作不可撤销。"
              data-confirm-title="删除文章"
              data-confirm-text="确认删除">
            <input type="hidden" name="_csrf" value="{{ $csrf }}">
        </form>
    @endforeach
    {!! $paginator ?? '' !!}
@endsection
