@extends('layouts.admin')

@section('content')
    <form method="post" action="{{ $item ? '/admin/shuoshuo/'.$item->id.'/edit' : '/admin/shuoshuo/create' }}" class="admin-form">
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
                <input type="text" name="music" value="{{ $item->music ?? '' }}" placeholder="网易云/QQ音乐/音频链接">
                <small class="hint">支持网易云音乐、QQ音乐、或直接音频文件链接</small>
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
            <a href="/admin/shuoshuo" class="btn">取消</a>
        </div>
    </form>
@endsection
