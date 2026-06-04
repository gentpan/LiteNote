@if(\App\Core\Session::hasFlash('talk_publish_success'))
    <div class="alert alert-success">{{ \App\Core\Session::getFlash('talk_publish_success') }}</div>
@endif
@if(\App\Core\Session::hasFlash('talk_publish_error'))
    <div class="alert alert-error">{{ \App\Core\Session::getFlash('talk_publish_error') }}</div>
@endif
@if(!empty($currentAdmin))
    <form class="front-publish-form" method="post" action="/talk/publish">
        <input type="hidden" name="_csrf" value="{{ \App\Core\Session::csrfToken() }}">
        <div class="front-publish-head">
            <span>发布滔客</span>
            <label><input type="checkbox" name="is_public" value="1" checked> 公开</label>
        </div>
        <textarea name="content" rows="4" placeholder="今天想说点什么...（用 #关键词 添加标签，会自动渲染）" required></textarea>
        <div class="front-publish-images">
            <input type="text" name="images" placeholder="图片 URL，多个用英文逗号分隔">
            <button type="button" class="fp-upload-btn"><i class="fa-solid fa-arrow-up-from-bracket"></i> 上传</button>
            <input type="file" class="fp-upload-file" accept="image/*" hidden>
        </div>
        <details class="front-publish-music">
            <summary><i class="fa-solid fa-music"></i> 添加音乐</summary>
            <div class="front-publish-music-grid">
                <input type="text" name="music" placeholder="音乐链接：音频直链 mp3/m4a，或 网易云/QQ 链接">
                <input type="text" name="music_title" placeholder="歌名（卡片标题，选填）">
                <input type="text" name="music_artist" placeholder="歌手（选填）">
                <input type="text" name="music_cover" placeholder="封面图 URL（选填）">
            </div>
            <p class="front-publish-music-hint">音频直链会渲染成封面播放器卡片；网易云/QQ 链接则用官方播放器嵌入。</p>
        </details>
        <div class="front-publish-actions">
            <span>{{ $currentAdmin->nickname ?: $currentAdmin->username }}</span>
            <button type="submit">发布</button>
        </div>
    </form>
@endif
