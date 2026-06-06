@extends('layouts.admin')

@section('content')
    <div class="admin-toolbar">
        <a class="btn btn-primary" href="/admin/music/create"><i class="fa-solid fa-music"></i> 添加音乐</a>
    </div>

    <table class="admin-table admin-action-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>音乐</th>
                <th>音频</th>
                <th>排序</th>
                <th>播放</th>
                <th>喜欢</th>
                <th>状态</th>
                <th>发布时间</th>
                <th>更新时间</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            @foreach($list as $m)
            <tr>
                <td>{{ $m->id }}</td>
                <td>
                    <div class="comment-cell">
                        <strong>{{ $m->title }}</strong>
                        <small class="muted">
                            {{ $m->artist ?: '未知歌手' }}
                            @if($m->album) · {{ $m->album }} @endif
                            @if($m->mood) · #{{ $m->mood }} @endif
                        </small>
                    </div>
                </td>
                <td>
                    <a href="{{ $m->audio_url }}" target="_blank" rel="nofollow noopener">
                        <i class="fa-solid fa-headphones"></i> {{ $m->duration ?: '试听' }}
                    </a>
                </td>
                <td>{{ $m->sort }}</td>
                <td>{{ (int)($m->play_count ?? 0) }}</td>
                <td>{{ (int)($m->likes_count ?? 0) }}</td>
                <td>
                    @if((int)$m->is_public === 1)
                        <span class="status status-published">公开</span>
                    @else
                        <span class="status status-draft">隐藏</span>
                    @endif
                </td>
                <td>{!! \App\Core\Helper::dateTimeTag($m->publishedAt()) !!}</td>
                <td>{!! \App\Core\Helper::dateTimeTag($m->updated_at ?: $m->created_at) !!}</td>
                <td>
                    <div class="admin-action-bar">
                        <a href="/admin/music/{{ $m->id }}/edit"
                           class="admin-action-btn admin-action-edit"
                           title="编辑"
                           aria-label="编辑">
                            <i class="fa-regular fa-pen-to-square"></i>
                        </a>
                        <button type="submit"
                                form="music-delete-form-{{ $m->id }}"
                                class="admin-action-btn admin-action-delete"
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

    @foreach($list as $m)
        <form id="music-delete-form-{{ $m->id }}" method="post" action="/admin/music/delete" class="hidden"
              data-confirm="确定删除这首音乐？此操作不可撤销。"
              data-confirm-title="删除音乐"
              data-confirm-text="确认删除">
            <input type="hidden" name="_csrf" value="{{ $csrf }}">
            <input type="hidden" name="id" value="{{ $m->id }}">
        </form>
    @endforeach

    {!! $paginator ?? '' !!}
@endsection
