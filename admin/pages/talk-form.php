@extends('layouts.admin')

@section('content')
    @php
        $musicOptions = $musicOptions ?? [];
        $formType = $formType ?? 'talk';
        $selectedMusicId = (int)($item->music_id ?? 0);
        $isPublic = !$item || (int)($item->is_public ?? 1) === 1;
        $publishedAt = (string)($item->published_at ?? $item->created_at ?? date('Y-m-d H:i:s'));
        $action = $item ? '/admin/talk/'.$item->id.'/edit' : '/admin/talk/create';
    @endphp

    <div class="admin-toolbar talk-form-switch">
        <a class="btn {{ $formType === 'talk' ? 'btn-primary' : '' }}" href="{{ $item ? '/admin/talk/'.$item->id.'/edit' : '/admin/talk/create?type=talk' }}">
            <i class="fa-regular fa-pen-to-square"></i> 写滔客
        </a>
        <a class="btn {{ $formType === 'tweet' ? 'btn-primary' : '' }}" href="{{ $item ? '/admin/talk/'.$item->id.'/edit' : '/admin/talk/create?type=tweet' }}">
            <i class="fa-brands fa-x-twitter"></i> 分享 X
        </a>
        <a class="btn {{ $formType === 'music' ? 'btn-primary' : '' }}" href="{{ $item ? '/admin/talk/'.$item->id.'/edit' : '/admin/talk/create?type=music' }}">
            <i class="fa-solid fa-music"></i> 分享音乐
        </a>
    </div>

    <form method="post" action="{{ $action }}" class="admin-form" data-dirty-watch>
        <input type="hidden" name="_csrf" value="{{ $csrf }}">
        <input type="hidden" name="post_type" value="{{ $formType }}">

        @if($formType === 'tweet')
            <div class="admin-form-section">
                <h3><i class="fa-brands fa-x-twitter"></i> 分享 X</h3>
                <div class="form-group">
                    <label>X 链接 *</label>
                    <input type="url" name="tweet_url" value="{{ $item->tweet_url ?? '' }}" required placeholder="https://x.com/user/status/1994155465488670828">
                    <small class="hint">只需要填写 X 链接。保存时由服务器抓取内容、作者、图片等信息，并保存为本地 JSON 用于前端渲染。</small>
                </div>
            </div>
        @elseif($formType === 'music')
            <div class="admin-form-section">
                <h3><i class="fa-solid fa-music"></i> 分享音乐</h3>
                <div class="form-group">
                    <label>选择音乐 *</label>
                    <select name="music_id" required>
                        <option value="0">请选择音乐</option>
                        @foreach($musicOptions as $musicOption)
                            <option value="{{ $musicOption->id }}" @if((int)$musicOption->id === $selectedMusicId) selected @endif>{{ $musicOption->title }}{{ $musicOption->artist ? ' - '.$musicOption->artist : '' }}</option>
                        @endforeach
                    </select>
                    <small class="hint">这里是把曲库里的音乐分享到滔客时间线；新增歌曲请先到音乐管理添加。</small>
                </div>
                <div class="form-group">
                    <label>分享文案</label>
                    <textarea name="content" rows="3" placeholder="选填，留空会自动生成分享文案">{{ $item->content ?? '' }}</textarea>
                </div>
            </div>
        @else
            <div class="admin-form-section">
                <h3><i class="fa-regular fa-pen-to-square"></i> 写滔客</h3>
                <div class="form-group">
                    <label>内容 *</label>
                    <textarea name="content" rows="5" required placeholder="写点什么...">{{ $item->content ?? '' }}</textarea>
                </div>
                <div class="form-row">
                    <div class="form-group flex-2">
                        <label>图片 URL（多个用英文逗号分隔）</label>
                        <input type="text" name="images" value="{{ $item->images ?? '' }}">
                    </div>
                    <div class="form-group">
                        <label>心情</label>
                        <input type="text" name="mood" value="{{ $item->mood ?? '' }}" placeholder="例如：开心">
                    </div>
                </div>
            </div>
        @endif

        @if($formType === 'talk')
            <div class="form-row">
                <div class="form-group">
                    <label>发布时间</label>
                    <input type="text" name="published_at" value="{{ $publishedAt }}" placeholder="2026-06-05 18:00:00">
                </div>
                <div class="form-group">
                    <label>状态</label>
                    <input type="hidden" name="is_public" value="0">
                    <label class="admin-inline-check"><input type="checkbox" name="is_public" value="1" @if($isPublic) checked @endif> 公开展示</label>
                </div>
            </div>
        @endif

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">保存</button>
            <a href="/admin/talk" class="btn">取消</a>
        </div>
    </form>
@endsection
