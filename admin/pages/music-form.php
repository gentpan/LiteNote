@extends('layouts.admin')

@section('content')
<div class="music-admin-shell">
    <form method="post" action="{{ $item ? '/admin/music/'.$item->id.'/edit' : '/admin/music/create' }}" class="admin-form music-editor-form" data-dirty-watch>
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
                <div class="admin-upload-field"
                     data-upload-field
                     data-upload-url="/admin/attachments/upload"
                     data-csrf="{{ $csrf }}"
                     data-upload-accept="audio/*,.mp3,.m4a,.wav,.ogg,.flac,.aac">
                    <input type="url" name="audio_url" value="{{ $item->audio_url ?? '' }}" required placeholder="https://example.com/song.mp3">
                    <button type="button" class="admin-upload-field-btn" data-upload-trigger aria-label="上传音频" title="上传音频">
                        <i class="fa-solid fa-arrow-up-from-bracket"></i>
                    </button>
                    <input type="file" data-upload-input accept="audio/*,.mp3,.m4a,.wav,.ogg,.flac,.aac" hidden>
                </div>
                <small class="hint">请填写浏览器可直接播放的 mp3 / m4a / wav / ogg 等音频地址。</small>
            </div>
            <div class="form-group flex-2">
                <label>封面图 URL</label>
                <div class="admin-upload-field"
                     data-upload-field
                     data-upload-url="/admin/attachments/upload"
                     data-csrf="{{ $csrf }}"
                     data-upload-accept="image/*">
                    <input type="url" name="cover_url" value="{{ $item->cover_url ?? '' }}" placeholder="https://example.com/cover.jpg">
                    <button type="button" class="admin-upload-field-btn" data-upload-trigger aria-label="上传封面" title="上传封面">
                        <i class="fa-solid fa-arrow-up-from-bracket"></i>
                    </button>
                    <input type="file" data-upload-input accept="image/*" hidden>
                </div>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>时长</label>
                <input type="text" name="duration" value="{{ $item->duration ?? '' }}" placeholder="例如：5:44">
            </div>
            <div class="form-group">
                <label>排序</label>
                <input type="number" name="sort" value="{{ $item->sort ?? 0 }}">
                <small class="hint">用于音乐库列表排序；发布到说说时再选择发布时间。</small>
            </div>
        </div>

        <div class="form-group">
            <label>歌词文件 URL</label>
            <div class="admin-upload-field"
                 data-upload-field
                 data-upload-url="/admin/attachments/upload"
                 data-csrf="{{ $csrf }}"
                 data-upload-accept=".lrc,.txt,text/plain">
                <input type="text" name="lyrics_url" value="{{ $item->lyrics_url ?? '' }}" placeholder="https://example.com/song.lrc 或 /uploads/...">
                <button type="button" class="admin-upload-field-btn" data-upload-trigger aria-label="上传歌词文件" title="上传歌词文件">
                    <i class="fa-solid fa-arrow-up-from-bracket"></i>
                </button>
                <input type="file" data-upload-input accept=".lrc,.txt,text/plain" hidden>
            </div>
            <small class="hint">支持 LRC / TXT 文件链接。Meting 搜索会优先填入这里，不把歌词正文写入数据库。</small>
        </div>

        <div class="form-group">
            <label>歌词 / 文案</label>
            <textarea name="lyrics" rows="6" data-lrc-input placeholder="[00:12.00] 也可以直接粘贴 LRC 或普通歌词文本">{{ $item->lyrics ?? '' }}</textarea>
            <small class="hint">可直接粘贴 LRC 格式文本；如果同时填写歌词文件 URL，前台优先加载歌词文件。</small>
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

    @if($item)
        <form method="post" action="/admin/music/delete" class="admin-form music-delete-panel"
              data-confirm="确定删除这首音乐？此操作不可撤销。"
              data-confirm-title="删除音乐"
              data-confirm-text="确认删除">
            <input type="hidden" name="_csrf" value="{{ $csrf }}">
            <input type="hidden" name="id" value="{{ $item->id }}">
            <div class="music-delete-panel-body">
                <div>
                    <h3>删除音乐</h3>
                    @if((int)($linkedTalkCount ?? 0) > 0)
                        <p class="music-delete-warning">这首音乐已被 {{ (int)$linkedTalkCount }} 条音乐说说使用。</p>
                        <label class="music-delete-option">
                            <input type="checkbox" name="delete_talks" value="1">
                            同时删除这些音乐说说
                        </label>
                        <p>不勾选时，只删除音乐，并保留说说内容、取消音乐关联。</p>
                    @else
                        <p>删除后，这首音乐不会再出现在音乐库和说说音乐选择里。</p>
                    @endif
                </div>
                <button type="submit" class="btn btn-danger"><i class="fa-regular fa-trash-can"></i> 删除</button>
            </div>
        </form>
    @endif
</div>
@endsection
