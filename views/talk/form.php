@extends('layouts.admin')

@section('content')
    <form method="post" action="{{ $item ? '/admin/talk/'.$item->id.'/edit' : '/admin/talk/create' }}" class="admin-form">
        <input type="hidden" name="_csrf" value="{{ $csrf }}">
        <div class="form-group">
            <label>内容 *</label>
            <textarea name="content" rows="4" required>{{ $item->content ?? '' }}</textarea>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>图片 URL（多个用英文逗号分隔）</label>
                <input type="text" name="images" value="{{ $item->images ?? '' }}">
            </div>
            <div class="form-group">
                <label>音乐链接</label>
                <input type="text" name="music" value="{{ $item->music ?? '' }}" placeholder="网易云/QQ音乐/音频直链">
                <small class="hint">音频直链(mp3/m4a…)渲染成封面播放器卡片；网易云/QQ 用官方嵌入</small>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>歌名（卡片标题）</label>
                <input type="text" name="music_title" value="{{ $item->music_title ?? '' }}" placeholder="选填，留空用文件名">
            </div>
            <div class="form-group">
                <label>歌手</label>
                <input type="text" name="music_artist" value="{{ $item->music_artist ?? '' }}" placeholder="选填">
            </div>
            <div class="form-group">
                <label>封面图 URL</label>
                <input type="text" name="music_cover" value="{{ $item->music_cover ?? '' }}" placeholder="选填">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>心情</label>
                <input type="text" name="mood" value="{{ $item->mood ?? '' }}" placeholder='<i class="fa-regular fa-face-smile"></i> 开心'>
            </div>
        </div>
        <div class="form-group">
            <label><input type="checkbox" name="is_public" value="1" checked> 公开</label>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">保存</button>
            <a href="/admin/talk" class="btn">取消</a>
        </div>
    </form>
@endsection
