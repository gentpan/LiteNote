@if(\App\Core\Session::hasFlash('talk_publish_success'))
    <div hidden data-toast-type="success" data-toast-message="{{ \App\Core\Session::getFlash('talk_publish_success') }}"></div>
@endif
@if(\App\Core\Session::hasFlash('talk_publish_error'))
    <div hidden data-toast-type="error" data-toast-message="{{ \App\Core\Session::getFlash('talk_publish_error') }}"></div>
@endif
@if(!empty($currentAdmin))
    @php $musicOptions = $musicOptions ?? []; @endphp
    <form class="front-publish-form" method="post" action="/talk/publish">
        <input type="hidden" name="_csrf" value="{{ \App\Core\Session::csrfToken() }}">
        <div class="front-publish-head">
            <span>发布滔客</span>
            <label class="front-publish-toggle" title="公开展示">
                <input type="hidden" name="is_public" value="0">
                <input class="front-publish-toggle-input" type="checkbox" name="is_public" value="1" checked>
                <span class="front-publish-toggle-track" aria-hidden="true">
                    <span class="front-publish-toggle-thumb"></span>
                </span>
                <span class="front-publish-toggle-text">公开</span>
            </label>
        </div>
        <textarea name="content" rows="4" placeholder="今天想说点什么...（用 #关键词 添加标签，会自动渲染）" required></textarea>
        <div class="front-publish-images">
            <input type="text" name="images" placeholder="图片 URL，多个用英文逗号分隔">
            <button type="button" class="fp-upload-btn fp-tool-btn"><i class="fa-solid fa-arrow-up-from-bracket"></i> 上传</button>
            <button type="button"
                    class="fp-music-btn fp-tool-btn"
                    aria-expanded="false"
                    aria-controls="front-publish-music-panel">
                <i class="fa-solid fa-music"></i> 音乐
            </button>
            <input type="file" class="fp-upload-file" accept="image/*" hidden>
            <span class="fp-upload-status" hidden aria-live="polite">
                <span class="fp-upload-progress"><span></span></span>
                <span class="fp-upload-percent">0%</span>
            </span>
        </div>
        <div class="front-publish-music" id="front-publish-music-panel" hidden>
            <select id="front-publish-music" name="music_id">
                <option value="0">不关联音乐</option>
                @foreach($musicOptions as $musicOption)
                    <option value="{{ $musicOption->id }}">{{ $musicOption->title }}{{ $musicOption->artist ? ' - '.$musicOption->artist : '' }}</option>
                @endforeach
            </select>
        </div>
        <div class="front-publish-actions">
            <button type="submit" class="publish-btn">发布</button>
        </div>
    </form>
@endif
