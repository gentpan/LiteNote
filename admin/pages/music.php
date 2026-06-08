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
                    $isShared = !empty(($sharedMusicIds ?? [])[(int)$m->id]);
                @endphp
                <article class="music-admin-card" aria-label="{{ $m->title }}">
                    <span class="music-admin-cover">
                        @if($cover !== '')
                            <img src="{{ $cover }}" alt="" loading="lazy">
                        @else
                            <span>{{ $m->fallbackInitial() }}</span>
                        @endif
                    </span>

                    <span class="music-admin-card-main">
                        <span class="music-admin-card-head">
                            <a class="music-admin-title-block" href="/admin/music/{{ $m->id }}/edit" aria-label="编辑 {{ $m->title }}">
                                <strong>{{ $m->title }}</strong>
                                <span>{{ $artist }}</span>
                            </a>
                        </span>

                        <span class="music-admin-footer">
                            <span class="music-admin-stats">
                                <span><i class="fa-regular fa-circle-play"></i> {{ (int)($m->play_count ?? 0) }}</span>
                                <span><i class="fa-regular fa-comment"></i> {{ (int)($m->comments_count ?? 0) }}</span>
                            </span>
                            <span class="music-admin-card-actions">
                                <button type="button"
                                        class="music-share-trigger admin-action-btn {{ $isShared ? 'is-shared' : '' }}"
                                        data-music-share
                                        data-share-action="/admin/music/{{ $m->id }}/share"
                                        data-music-title="{{ $m->title }}"
                                        data-music-artist="{{ $artist }}"
                                        title="{{ $isShared ? '已分享到首页，可再次分享' : '分享到首页' }}"
                                        aria-label="分享 {{ $m->title }}">
                                    <i class="fa-solid fa-share-nodes"></i>
                                </button>
                            </span>
                        </span>
                    </span>
                </article>
            @endforeach
        </div>
    @endif

    {!! $paginator ?? '' !!}
</div>

<div class="admin-dialog-backdrop music-share-dialog" data-music-share-dialog hidden>
    <div class="admin-dialog-shell">
        <form method="post" action="" class="admin-dialog music-share-dialog-panel" data-music-share-form>
            <input type="hidden" name="_csrf" value="{{ $csrf }}">
            <div class="admin-dialog-body">
                <div class="admin-dialog-layout">
                    <div class="admin-dialog-icon admin-dialog-icon-primary">
                        <i class="fa-solid fa-music"></i>
                    </div>
                    <div class="admin-dialog-copy">
                        <h3>分享音乐</h3>
                        <p data-music-share-title>输入这首歌的分享文案。</p>
                    </div>
                </div>
                <textarea name="content" rows="4" placeholder="选填，留空会自动使用“分享一首音乐”"></textarea>
            </div>
            <div class="admin-dialog-actions">
                <button type="submit" class="btn btn-primary">发布分享</button>
                <button type="button" class="btn admin-dialog-cancel" data-music-share-cancel>取消</button>
            </div>
        </form>
    </div>
</div>
@endsection
