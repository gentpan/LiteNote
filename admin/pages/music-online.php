@extends('layouts.admin')

@section('content')
<div class="music-admin-shell">
    <div class="music-online-layout">
        <aside class="meting-search-panel music-online-search"
               data-meting-search
               data-search-url="/admin/music/meting/search"
               data-song-url="/admin/music/meting/song">
            <div class="meting-search-head">
                <div>
                    <h3>搜索线上音乐</h3>
                    <p>选择歌曲后自动填入音频，并在保存时缓存封面和歌词。</p>
                </div>
                <i class="fa-solid fa-cloud"></i>
            </div>
            <div class="meting-search-controls">
                <select data-meting-provider aria-label="音乐平台">
                    <option value="netease">网易云</option>
                    <option value="tencent">QQ 音乐</option>
                    <option value="kugou">酷狗</option>
                </select>
                <div class="meting-search-box">
                    <input type="search" data-meting-keyword placeholder="搜索歌曲 / 歌手">
                    <button type="button" class="btn btn-primary" data-meting-submit>
                        <i class="fa-solid fa-magnifying-glass"></i>
                    </button>
                </div>
            </div>
            <div class="meting-search-status" data-meting-status></div>
            <div class="meting-result-list" data-meting-results></div>
        </aside>

        <form method="post" action="/admin/music/create" class="admin-form music-editor-form music-online-form" data-dirty-watch>
            <input type="hidden" name="_csrf" value="{{ $csrf }}">
            <input type="hidden" name="lyrics" value="">
            <input type="hidden" name="is_public" value="1">
            <input type="hidden" name="source" value="">
            <input type="hidden" name="source_provider" value="">
            <input type="hidden" name="source_id" value="">

            <div class="admin-form-head">
                <h3>待保存音乐</h3>
                <p>音频保留线上播放地址，封面和歌词保存到本地。</p>
            </div>

            <div class="form-row">
                <div class="form-group flex-2">
                    <label>歌名 *</label>
                    <input type="text" name="title" required placeholder="先搜索并选择一首歌">
                </div>
                <div class="form-group">
                    <label>歌手</label>
                    <input type="text" name="artist" placeholder="自动填入">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>专辑</label>
                    <input type="text" name="album" placeholder="自动填入">
                </div>
                <div class="form-group">
                    <label>时长</label>
                    <input type="text" name="duration" placeholder="自动填入">
                </div>
            </div>

            <div class="form-group">
                <label>音频 URL *</label>
                <input type="url" name="audio_url" required placeholder="搜索选择后自动填入">
            </div>

            <div class="form-group">
                <label>封面图 URL</label>
                <input type="url" name="cover_url" placeholder="搜索选择后自动填入">
            </div>

            <div class="form-group">
                <label>歌词文件 URL</label>
                <input type="text" name="lyrics_url" placeholder="搜索选择后自动填入">
                <small class="hint">保存后会替换为本地歌词文件地址，不把歌词正文写入数据库。</small>
            </div>

            <div class="form-group">
                <label>排序</label>
                <input type="number" name="sort" value="0">
                <small class="hint">用于音乐库列表排序；发布到说说时再选择发布时间。</small>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">保存线上音乐</button>
                <a href="/admin/music" class="btn">取消</a>
            </div>
        </form>
    </div>
</div>
@endsection
