@extends('layouts.admin')

@section('content')
    @php
        $musicOptions = $musicOptions ?? [];
        $selectedMusicId = (int)($item->music_id ?? 0);
        $isPublic = !$item || (int)($item->is_public ?? 1) === 1;
    @endphp
    <form method="post" action="{{ $item ? '/admin/talk/'.$item->id.'/edit' : '/admin/talk/create' }}" class="admin-form" data-dirty-watch>
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
                <label>心情</label>
                <input type="text" name="mood" value="{{ $item->mood ?? '' }}" placeholder='<i class="fa-regular fa-face-smile"></i> 开心'>
            </div>
        </div>
        <div class="form-group">
            <label>关联音乐</label>
            <select name="music_id">
                <option value="0">不关联音乐</option>
                @foreach($musicOptions as $musicOption)
                    <option value="{{ $musicOption->id }}" @if((int)$musicOption->id === $selectedMusicId) selected @endif>{{ $musicOption->title }}{{ $musicOption->artist ? ' - '.$musicOption->artist : '' }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group">
            <label><input type="checkbox" name="is_public" value="1" @if($isPublic) checked @endif> 公开</label>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">保存</button>
            <a href="/admin/talk" class="btn">取消</a>
        </div>
    </form>
@endsection
