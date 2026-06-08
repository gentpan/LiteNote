@if(\App\Core\Session::hasFlash('talk_publish_success'))
    <div hidden data-toast-type="success" data-toast-message="{{ \App\Core\Session::getFlash('talk_publish_success') }}"></div>
@endif
@if(\App\Core\Session::hasFlash('talk_publish_error'))
    <div hidden data-toast-type="error" data-toast-message="{{ \App\Core\Session::getFlash('talk_publish_error') }}"></div>
@endif
@if(!empty($currentAdmin))
    @php $mapboxToken = trim((string)($site['site_mapbox_token'] ?? '')); @endphp
    <form class="front-publish-form" method="post" action="/talk/publish" data-mapbox-token="{{ $mapboxToken }}">
        <input type="hidden" name="_csrf" value="{{ \App\Core\Session::csrfToken() }}">
        <input type="hidden" name="location_name" value="">
        <input type="hidden" name="location_city" value="">
        <input type="hidden" name="location_lat" value="">
        <input type="hidden" name="location_lng" value="">
        <input type="hidden" name="location_provider" value="">
        <input type="hidden" name="location_data" value="">
        <input type="hidden" name="weather_label" value="">
        <input type="hidden" name="weather_icon" value="">
        <input type="hidden" name="weather_temp" value="">
        <input type="hidden" name="weather_code" value="">
        <input type="hidden" name="weather_data" value="">
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
            <button type="button" class="fp-location-btn fp-tool-btn" aria-expanded="false">
                <i class="fa-solid fa-location-dot"></i><span data-location-label>位置</span>
            </button>
            <button type="button" class="fp-weather-btn fp-tool-btn">
                <i class="fa-solid fa-cloud-sun"></i><span data-weather-label>天气</span>
            </button>
            <input type="file" class="fp-upload-file" accept="image/*" hidden>
            <span class="fp-upload-status" hidden aria-live="polite">
                <span class="fp-upload-progress"><span></span></span>
                <span class="fp-upload-percent">0%</span>
            </span>
        </div>
        <div class="front-publish-location" hidden>
            <div class="fp-location-search">
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                <input type="text" class="fp-location-input" placeholder="{{ $mapboxToken !== '' ? '搜索城市或地点，选择候选结果' : '需要先配置 Mapbox Token' }}" autocomplete="off" {{ $mapboxToken === '' ? 'disabled' : '' }}>
                <button type="button" class="fp-location-current"><i class="fa-solid fa-crosshairs"></i> 当前</button>
                <button type="button" class="fp-location-clear"><i class="fa-solid fa-xmark"></i> 清除</button>
            </div>
            <div class="fp-location-results" hidden></div>
            @if($mapboxToken === '')
                <p class="fp-location-hint">位置只允许使用 Mapbox 候选或当前位置反查，后台基础设置填入 Mapbox 公开 Token 后可用。</p>
            @endif
        </div>
        <div class="front-publish-actions">
            <button type="submit" class="publish-btn">发布</button>
        </div>
    </form>
@endif
