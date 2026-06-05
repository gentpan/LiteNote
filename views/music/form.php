@extends('layouts.admin')

@section('content')
    <form method="post" action="{{ $item ? '/admin/music/'.$item->id.'/edit' : '/admin/music/create' }}" class="admin-form" data-dirty-watch>
        <input type="hidden" name="_csrf" value="{{ $csrf }}">

        <div class="form-row">
            <div class="form-group flex-2">
                <label>歌名 *</label>
                <input type="text" name="title" value="{{ $item->title ?? '' }}" required placeholder="例如：阴雨额度">
            </div>
            <div class="form-group">
                <label>歌手</label>
                <input type="text" name="artist" value="{{ $item->artist ?? '' }}" placeholder="例如：LiteNote FM">
            </div>
            <div class="form-group">
                <label>专辑</label>
                <input type="text" name="album" value="{{ $item->album ?? '' }}" placeholder="选填">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group flex-2">
                <label>音频 URL *</label>
                <input type="url" name="audio_url" value="{{ $item->audio_url ?? '' }}" required placeholder="https://example.com/song.mp3">
                <small class="hint">请填写浏览器可直接播放的 mp3 / m4a / wav / ogg 等音频地址。</small>
            </div>
            <div class="form-group flex-2">
                <label>封面图 URL</label>
                <input type="url" name="cover_url" value="{{ $item->cover_url ?? '' }}" placeholder="https://example.com/cover.jpg">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>心情标签</label>
                <input type="text" name="mood" value="{{ $item->mood ?? '' }}" placeholder="例如：阴雨额度">
            </div>
            <div class="form-group">
                <label>发布时间</label>
                <input type="datetime-local" name="published_at" value="{{ $item ? $item->publishedInputValue() : date('Y-m-d\TH:i') }}">
                <small class="hint">音乐页会按发布时间自动整理播放列表。</small>
            </div>
            <div class="form-group">
                <label>时长</label>
                <input type="text" name="duration" value="{{ $item->duration ?? '' }}" placeholder="例如：5:44">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>排序</label>
                <input type="number" name="sort" value="{{ $item->sort ?? 0 }}">
                <small class="hint">同一发布时间下，可用排序微调先后。</small>
            </div>
        </div>

        <div class="form-group">
            <label>一句描述</label>
            <input type="text" name="description" value="{{ $item->description ?? '' }}" placeholder="用于播放器里没有歌词时展示">
        </div>

        <div class="form-group">
            <label>歌词 / 文案</label>
            <textarea name="lyrics" rows="6" data-lrc-input placeholder="[00:12.00] 可以直接粘贴 LRC，系统会自动识别成纯歌词">{{ $item->lyrics ?? '' }}</textarea>
            <small class="hint">支持直接粘贴 LRC 格式文本，保存时会自动去掉时间轴和 ti/ar/al 等元信息。</small>
        </div>

        <div class="form-group">
            <input type="hidden" name="is_public" value="0">
            <label><input type="checkbox" name="is_public" value="1" {{ (int)($item->is_public ?? 1) === 1 ? 'checked' : '' }}> 公开展示</label>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">保存</button>
            <a href="/admin/music" class="btn">取消</a>
        </div>
    </form>
@endsection
