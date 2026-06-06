@extends('layouts.admin')

@section('content')
<div class="music-admin-shell">
    <div class="admin-toolbar music-admin-toolbar">
        <div class="admin-action-bar">
            <a class="btn btn-primary" href="/admin/music/create"><i class="fa-solid fa-upload"></i> 添加本地音乐</a>
            <a class="btn" href="/admin/music/online"><i class="fa-solid fa-cloud"></i> 添加线上音乐</a>
        </div>
        <form method="get" class="admin-search music-admin-search">
            <input type="search" name="q" value="{{ $keyword ?? '' }}" placeholder="搜索歌名 / 歌手 / 专辑">
            <button type="submit"><i class="fa-solid fa-magnifying-glass"></i> 搜索</button>
            @if(!empty($keyword))
                <a class="btn" href="/admin/music">清除</a>
            @endif
        </form>
    </div>

    @if(empty($list))
        <div class="admin-empty-card">
            @if(!empty($keyword))
                没有找到和“{{ $keyword }}”相关的音乐。
            @else
                还没有添加音乐。
            @endif
        </div>
    @else
        <div class="music-admin-grid">
            @foreach($list as $m)
                @php
                    $cover = trim((string)($m->cover_url ?? ''));
                    $artist = trim((string)($m->artist ?? '')) ?: '未知歌手';
                @endphp
                <a class="music-admin-card" href="/admin/music/{{ $m->id }}/edit" aria-label="编辑 {{ $m->title }}">
                    <span class="music-admin-cover">
                        @if($cover !== '')
                            <img src="{{ $cover }}" alt="" loading="lazy">
                        @else
                            <span>{{ $m->fallbackInitial() }}</span>
                        @endif
                    </span>

                    <span class="music-admin-card-main">
                        <span class="music-admin-card-head">
                            <span class="music-admin-title-block">
                                <strong>{{ $m->title }}</strong>
                                <span>{{ $artist }}</span>
                            </span>
                        </span>

                        <span class="music-admin-footer">
                            <span class="music-admin-stats">
                                <span><i class="fa-regular fa-circle-play"></i> {{ (int)($m->play_count ?? 0) }}</span>
                                <span><i class="fa-regular fa-comment"></i> {{ (int)($m->comments_count ?? 0) }}</span>
                            </span>
                        </span>
                    </span>
                </a>
            @endforeach
        </div>
    @endif

    {!! $paginator ?? '' !!}
</div>
@endsection
