@extends('layouts.admin')

@section('content')
    @php
        $rowNo = (($page ?? 1) - 1) * ($perPage ?? 20);
        $talkMapboxToken = trim((string)($site['site_mapbox_token'] ?? \App\Models\Setting::get('site_mapbox_token', '')));
    @endphp
<div class="talk-admin-page">
    <table class="admin-table admin-action-table talk-admin-table">
        <thead>
            <tr><th>序号</th><th>内容</th><th>公开</th><th>时间</th><th>操作</th></tr>
        </thead>
        <tbody>
            @foreach($list as $s)
            @php $rowNo++; @endphp
            <tr>
                <td>{{ $rowNo }}</td>
                <td><div class="comment-cell" data-talk-content>{{ \App\Core\Helper::truncate($s->content, 100) }}</div></td>
                <td data-talk-public>{!! $s->is_public ? '<span class="status status-published">公开</span>' : '<span class="status status-draft">隐藏</span>' !!}</td>
                <td data-talk-time>{!! \App\Core\Helper::dateTimeTag($s->published_at ?: $s->created_at) !!}</td>
                <td>
                    <div class="admin-action-bar">
                        <button type="button"
                                class="admin-action-btn admin-action-toggle"
                                title="{{ $s->is_public ? '设为隐藏' : '恢复公开' }}"
                                aria-label="{{ $s->is_public ? '设为隐藏' : '恢复公开' }}"
                                data-talk-toggle
                                data-id="{{ $s->id }}">
                            <i class="fa-solid {{ $s->is_public ? 'fa-eye-slash' : 'fa-eye' }}"></i>
                        </button>
                        <button type="button"
                                class="admin-action-btn admin-action-edit"
                                title="编辑"
                                aria-label="编辑"
                                data-talk-edit
                                data-id="{{ $s->id }}">
                            <i class="fa-regular fa-pen-to-square"></i>
                        </button>
                        <button type="submit"
                                form="talk-delete-form-{{ $s->id }}"
                                class="admin-action-btn admin-action-delete"
                                title="删除"
                                aria-label="删除">
                            <i class="fa-regular fa-trash-can"></i>
                        </button>
                    </div>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    @foreach($list as $s)
        <form id="talk-delete-form-{{ $s->id }}" method="post" action="/admin/talk/delete" class="hidden"
              data-confirm="确定删除这条说说？此操作不可撤销。"
              data-confirm-title="删除说说"
              data-confirm-text="确认删除">
            <input type="hidden" name="_csrf" value="{{ $csrf }}">
            <input type="hidden" name="id" value="{{ $s->id }}">
        </form>
    @endforeach
    {!! $paginator ?? '' !!}
</div>

    <div class="admin-dialog-backdrop talk-edit-dialog" data-talk-edit-dialog hidden>
        <div class="admin-dialog-shell">
            <form method="post" action="" class="admin-dialog talk-edit-dialog-panel" data-talk-edit-form data-no-submit-loading data-mapbox-token="{{ $talkMapboxToken }}">
                <input type="hidden" name="_csrf" value="{{ $csrf }}">
                <input type="hidden" name="post_type" value="talk" data-talk-field="post_type">
                <input type="hidden" name="music_id" value="0" data-talk-field="music_id">
                <input type="hidden" name="location_city" data-talk-field="location_city">
                <input type="hidden" name="location_lat" data-talk-field="location_lat">
                <input type="hidden" name="location_lng" data-talk-field="location_lng">
                <input type="hidden" name="location_provider" data-talk-field="location_provider">
                <input type="hidden" name="location_data" data-talk-field="location_data">
                <input type="hidden" name="weather_icon" data-talk-field="weather_icon">
                <input type="hidden" name="weather_code" data-talk-field="weather_code">
                <input type="hidden" name="weather_data" data-talk-field="weather_data">
                <div class="admin-dialog-body">
                    <div class="admin-dialog-layout talk-edit-dialog-layout">
                        <div class="admin-dialog-icon admin-dialog-icon-primary">
                            <i class="fa-regular fa-pen-to-square"></i>
                        </div>
                        <div class="admin-dialog-copy talk-edit-dialog-copy">
                            <h3>编辑滔客</h3>
                            <p>调整内容、图片和展示状态。</p>
                        </div>
                        <div class="talk-edit-form-grid">
                                <label>
                                    <span>内容</span>
                                    <textarea name="content" rows="5" required data-talk-field="content"></textarea>
                                </label>
                                <label>
                                    <span>图片 URL</span>
                                    <div class="admin-upload-field talk-edit-image-field">
                                        <input type="text" name="images" data-talk-field="images" placeholder="多个图片用英文逗号分隔">
                                        <button type="button" class="admin-upload-field-btn" data-talk-image-upload aria-label="上传图片" title="上传图片">
                                            <i class="fa-solid fa-arrow-up-from-bracket" aria-hidden="true"></i>
                                        </button>
                                        <input type="file" accept="image/*" data-talk-image-file hidden>
                                    </div>
                                </label>
                                <div class="talk-edit-form-row">
                                    <label>
                                        <span>位置</span>
                                        <div class="talk-edit-control-field">
                                            <input type="text" name="location_name" data-talk-field="location_name" placeholder="搜索或手动填写位置">
                                            <button type="button" class="admin-upload-field-btn talk-edit-tool-btn" data-talk-location-toggle aria-expanded="false" aria-label="搜索位置" title="搜索位置">
                                                <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
                                            </button>
                                        </div>
                                    </label>
                                    <label>
                                        <span>天气</span>
                                        <div class="talk-edit-weather-field">
                                            <input type="text" name="weather_label" data-talk-field="weather_label" placeholder="例如 多云">
                                            <input type="text" name="weather_temp" data-talk-field="weather_temp" placeholder="温度">
                                            <button type="button" class="admin-upload-field-btn talk-edit-tool-btn" data-talk-weather-fetch aria-label="获取天气" title="获取天气">
                                                <i class="fa-solid fa-cloud-sun" aria-hidden="true"></i>
                                            </button>
                                        </div>
                                    </label>
                                </div>
                                <div class="talk-edit-location-panel" data-talk-location-panel hidden>
                                    <div class="talk-edit-location-search">
                                        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                                        <input type="text" data-talk-location-input placeholder="{{ $talkMapboxToken !== '' ? '搜索城市或地点，选择候选结果' : '需要先配置 Mapbox Token' }}" autocomplete="off" {{ $talkMapboxToken === '' ? 'disabled' : '' }}>
                                        <button type="button" class="btn btn-secondary" data-talk-location-current>
                                            <i class="fa-solid fa-crosshairs" aria-hidden="true"></i> 当前
                                        </button>
                                        <button type="button" class="btn btn-secondary" data-talk-location-clear>
                                            <i class="fa-solid fa-circle-xmark" aria-hidden="true"></i> 清除
                                        </button>
                                    </div>
                                    <div class="talk-edit-location-results" data-talk-location-results hidden></div>
                                    @if($talkMapboxToken === '')
                                        <p class="talk-edit-location-hint">基础设置填入 Mapbox 公开 Token 后可搜索位置；当前天气仍可尝试用浏览器定位或 IP 获取。</p>
                                    @endif
                                </div>
                                <label>
                                    <span>发布时间</span>
                                    <input type="text" name="published_at" data-talk-field="published_at" placeholder="2026-06-05 18:00:00">
                                </label>
                                <label class="admin-inline-check talk-edit-public">
                                    <input type="hidden" name="is_public" value="0">
                                    <input type="checkbox" name="is_public" value="1" data-talk-field="is_public">
                                    公开展示
                                </label>
                        </div>
                    </div>
                </div>
                <div class="admin-dialog-actions">
                    <button type="submit" class="btn btn-primary" data-talk-save>保存</button>
                    <button type="button" class="btn admin-dialog-cancel" data-talk-edit-cancel>取消</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    (function () {
        var dialog = document.querySelector('[data-talk-edit-dialog]');
        var form = document.querySelector('[data-talk-edit-form]');
        if (!dialog || !form) return;

        var row = null;
        var fields = {
            postType: form.querySelector('[data-talk-field="post_type"]'),
            musicId: form.querySelector('[data-talk-field="music_id"]'),
            content: form.querySelector('[data-talk-field="content"]'),
            images: form.querySelector('[data-talk-field="images"]'),
            publishedAt: form.querySelector('[data-talk-field="published_at"]'),
            isPublic: form.querySelector('[data-talk-field="is_public"]'),
            locationName: form.querySelector('[data-talk-field="location_name"]'),
            locationCity: form.querySelector('[data-talk-field="location_city"]'),
            locationLat: form.querySelector('[data-talk-field="location_lat"]'),
            locationLng: form.querySelector('[data-talk-field="location_lng"]'),
            locationProvider: form.querySelector('[data-talk-field="location_provider"]'),
            locationData: form.querySelector('[data-talk-field="location_data"]'),
            weatherLabel: form.querySelector('[data-talk-field="weather_label"]'),
            weatherIcon: form.querySelector('[data-talk-field="weather_icon"]'),
            weatherTemp: form.querySelector('[data-talk-field="weather_temp"]'),
            weatherCode: form.querySelector('[data-talk-field="weather_code"]'),
            weatherData: form.querySelector('[data-talk-field="weather_data"]')
        };
        var imageUploadBtn = form.querySelector('[data-talk-image-upload]');
        var imageFileInput = form.querySelector('[data-talk-image-file]');
        var locationToggleBtn = form.querySelector('[data-talk-location-toggle]');
        var locationPanel = form.querySelector('[data-talk-location-panel]');
        var locationInput = form.querySelector('[data-talk-location-input]');
        var locationCurrent = form.querySelector('[data-talk-location-current]');
        var locationClear = form.querySelector('[data-talk-location-clear]');
        var locationResults = form.querySelector('[data-talk-location-results]');
        var weatherFetchBtn = form.querySelector('[data-talk-weather-fetch]');
        var mapboxToken = String(form.dataset.mapboxToken || '').trim();
        var searchTimer = null;
        var searchAbort = null;

        function toast(message, type) {
            if (window.adminToast) {
                window.adminToast(message, type || 'info');
            } else {
                alert(message);
            }
        }

        function safeJson(value) {
            try {
                return JSON.stringify(value || {});
            } catch (e) {
                return '';
            }
        }

        function syncLocationButton() {
            if (!locationToggleBtn) return;
            var name = fields.locationName ? fields.locationName.value.trim() : '';
            locationToggleBtn.classList.toggle('is-active', !!name);
            locationToggleBtn.title = name ? '已设置位置，点击修改' : '搜索位置';
            locationToggleBtn.setAttribute('aria-label', locationToggleBtn.title);
        }

        function setLocation(place) {
            var name = String((place && place.name) || '').trim();
            var city = String((place && place.city) || name).trim();
            var fullName = String((place && (place.fullName || place.full_name || place.place_name)) || name || city).trim();
            var lat = String((place && place.lat) || '').trim();
            var lng = String((place && place.lng) || '').trim();
            var provider = String((place && place.provider) || (lat && lng ? 'mapbox' : 'manual')).trim();
            if (fields.locationName) fields.locationName.value = name;
            if (fields.locationCity) fields.locationCity.value = city;
            if (fields.locationLat) fields.locationLat.value = lat;
            if (fields.locationLng) fields.locationLng.value = lng;
            if (fields.locationProvider) fields.locationProvider.value = name ? provider : '';
            if (fields.locationData) {
                fields.locationData.value = name ? safeJson({
                    id: place && place.id || '',
                    name: name,
                    city: city,
                    full_name: fullName,
                    lat: lat,
                    lng: lng,
                    provider: provider
                }) : '';
            }
            if (locationInput) locationInput.value = name;
            if (name && locationPanel && locationToggleBtn) {
                locationPanel.hidden = true;
                locationToggleBtn.setAttribute('aria-expanded', 'false');
                if (weatherFetchBtn && form.dataset.weatherPending === '1') {
                    form.dataset.weatherPending = '';
                    setTimeout(function () { weatherFetchBtn.click(); }, 80);
                }
            }
            syncLocationButton();
        }

        function setManualLocation(name) {
            name = String(name || '').trim();
            setLocation(name ? {
                id: '',
                name: name,
                city: name,
                fullName: name,
                lat: '',
                lng: '',
                provider: 'manual'
            } : null);
        }

        function syncWeatherData() {
            var label = fields.weatherLabel ? fields.weatherLabel.value.trim() : '';
            var temp = fields.weatherTemp ? fields.weatherTemp.value.trim() : '';
            var icon = fields.weatherIcon && fields.weatherIcon.value ? fields.weatherIcon.value : 'fa-solid fa-cloud-sun';
            if (fields.weatherIcon && label && !fields.weatherIcon.value) {
                fields.weatherIcon.value = icon;
            }
            if (fields.weatherData) {
                fields.weatherData.value = label ? safeJson({
                    label: label,
                    icon: icon,
                    temperature: temp,
                    code: fields.weatherCode ? fields.weatherCode.value : '0',
                    place: fields.locationName ? fields.locationName.value.trim() : '',
                    source: 'admin',
                    fetched_at: ''
                }) : '';
            }
            if (weatherFetchBtn) {
                weatherFetchBtn.classList.toggle('is-active', !!label);
                weatherFetchBtn.title = label ? '重新获取天气' : '获取天气';
                weatherFetchBtn.setAttribute('aria-label', weatherFetchBtn.title);
            }
        }

        function setWeather(weather) {
            var label = String((weather && weather.label) || '').trim();
            var icon = String((weather && weather.icon) || 'fa-solid fa-cloud-sun').trim();
            var temp = weather && weather.temperature !== undefined ? String(weather.temperature).trim() : '';
            if (fields.weatherLabel) fields.weatherLabel.value = label;
            if (fields.weatherIcon) fields.weatherIcon.value = label ? icon : '';
            if (fields.weatherTemp) fields.weatherTemp.value = temp;
            if (fields.weatherCode) fields.weatherCode.value = weather && weather.code !== undefined ? String(weather.code) : '';
            if (fields.weatherData) fields.weatherData.value = label ? safeJson(weather || {}) : '';
            syncWeatherData();
        }

        function requestWeather(lat, lng, place) {
            var params = new URLSearchParams();
            if (lat && lng) {
                params.set('lat', lat);
                params.set('lng', lng);
            }
            if (place) params.set('place', place);
            return fetch('/talk/weather?' + params.toString(), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            }).then(function (resp) {
                return resp.json().then(function (data) {
                    if (!resp.ok || !data || data.code !== 0) {
                        throw new Error((data && data.msg) || '天气获取失败');
                    }
                    return data.data;
                });
            });
        }

        function fetchWeatherForEdit() {
            var lat = fields.locationLat && fields.locationLat.value;
            var lng = fields.locationLng && fields.locationLng.value;
            var place = (fields.locationName && fields.locationName.value) || (fields.locationCity && fields.locationCity.value) || '';
            if (lat && lng) {
                return requestWeather(lat, lng, place);
            }
            if (!navigator.geolocation) {
                return requestWeather('', '', place);
            }
            return new Promise(function (resolve, reject) {
                navigator.geolocation.getCurrentPosition(function (pos) {
                    requestWeather(pos.coords.latitude, pos.coords.longitude, place).then(resolve, reject);
                }, function () {
                    requestWeather('', '', place).then(resolve, reject);
                }, { enableHighAccuracy: false, timeout: 9000, maximumAge: 600000 });
            });
        }

        function featureCity(feature) {
            var context = Array.isArray(feature && feature.context) ? feature.context : [];
            var city = '';
            context.some(function (item) {
                var id = String(item.id || '');
                if (id.indexOf('place.') === 0 || id.indexOf('locality.') === 0 || id.indexOf('region.') === 0) {
                    city = String(item.text || item.text_zh || item.short_code || '').trim();
                    return city !== '';
                }
                return false;
            });
            return city || String((feature && feature.text) || '').trim();
        }

        function featureToPlace(feature) {
            var center = Array.isArray(feature && feature.center) ? feature.center : [];
            var city = featureCity(feature);
            var name = String((feature && (feature.text_zh || feature.text)) || city || '').trim();
            var fullName = String((feature && (feature.place_name_zh || feature.place_name)) || name || city || '').trim();
            return {
                id: feature && feature.id || '',
                name: name || fullName,
                city: city,
                fullName: fullName,
                lng: center[0] !== undefined ? String(center[0]) : '',
                lat: center[1] !== undefined ? String(center[1]) : '',
                provider: 'mapbox'
            };
        }

        function renderLocationResults(features, emptyText) {
            if (!locationResults) return;
            locationResults.innerHTML = '';
            var list = Array.isArray(features) ? features : [];
            if (!list.length) {
                if (emptyText) {
                    var empty = document.createElement('div');
                    empty.className = 'talk-edit-location-empty';
                    empty.textContent = emptyText;
                    locationResults.appendChild(empty);
                    locationResults.hidden = false;
                } else {
                    locationResults.hidden = true;
                }
                return;
            }
            list.slice(0, 6).forEach(function (feature) {
                var place = featureToPlace(feature);
                var item = document.createElement('button');
                item.type = 'button';
                item.className = 'talk-edit-location-result';
                item.innerHTML = '<i class="fa-solid fa-location-dot" aria-hidden="true"></i><span></span>';
                item.querySelector('span').textContent = place.fullName || place.name;
                item.addEventListener('click', function () { setLocation(place); });
                locationResults.appendChild(item);
            });
            locationResults.hidden = false;
        }

        function mapboxFetch(url) {
            if (searchAbort) searchAbort.abort();
            searchAbort = new AbortController();
            return fetch(url, { signal: searchAbort.signal }).then(function (resp) {
                if (!resp.ok) throw new Error('mapbox request failed');
                return resp.json();
            });
        }

        function searchLocation(query) {
            query = String(query || '').trim();
            if (!query) {
                renderLocationResults([]);
                return;
            }
            if (!mapboxToken) {
                renderLocationResults([], '需要先配置 Mapbox Token');
                return;
            }
            var url = 'https://api.mapbox.com/geocoding/v5/mapbox.places/'
                + encodeURIComponent(query)
                + '.json?access_token=' + encodeURIComponent(mapboxToken)
                + '&language=zh-Hans&limit=6&types=place,locality,region,country';
            mapboxFetch(url).then(function (data) {
                renderLocationResults(data && data.features, '没有找到这个位置');
            }).catch(function (err) {
                if (err && err.name === 'AbortError') return;
                renderLocationResults([], '位置搜索失败，请稍后重试');
            });
        }

        function reverseLocation(lat, lng) {
            if (!mapboxToken) {
                toast('需要先在基础设置填入 Mapbox 公开 Token', 'error');
                return;
            }
            var url = 'https://api.mapbox.com/geocoding/v5/mapbox.places/'
                + encodeURIComponent(lng + ',' + lat)
                + '.json?access_token=' + encodeURIComponent(mapboxToken)
                + '&language=zh-Hans&limit=1&types=place,locality,region,country';
            renderLocationResults([], '定位中...');
            mapboxFetch(url).then(function (data) {
                var feature = data && data.features && data.features[0];
                if (!feature) {
                    renderLocationResults([], '没有识别到城市，请搜索后选择候选位置');
                    return;
                }
                setLocation(featureToPlace(feature));
            }).catch(function (err) {
                if (err && err.name === 'AbortError') return;
                renderLocationResults([], '定位失败，请搜索后选择候选位置');
            });
        }

        function openDialog(data, sourceRow) {
            row = sourceRow;
            form.action = '/admin/talk/' + data.id + '/edit';
            fields.postType.value = data.post_type || 'talk';
            fields.musicId.value = data.music_id || '0';
            fields.content.value = data.content || '';
            fields.images.value = data.images || '';
            fields.publishedAt.value = data.published_at || '';
            fields.isPublic.checked = String(data.is_public || '0') === '1';
            fields.locationName.value = data.location_name || '';
            fields.locationCity.value = data.location_city || '';
            fields.locationLat.value = data.location_lat || '';
            fields.locationLng.value = data.location_lng || '';
            fields.locationProvider.value = data.location_provider || '';
            fields.locationData.value = data.location_data || '';
            fields.weatherLabel.value = data.weather_label || '';
            fields.weatherIcon.value = data.weather_icon || '';
            fields.weatherTemp.value = data.weather_temp || '';
            fields.weatherCode.value = data.weather_code || '';
            fields.weatherData.value = data.weather_data || '';
            if (locationInput) locationInput.value = fields.locationName.value || fields.locationCity.value || '';
            if (locationPanel && locationToggleBtn) {
                locationPanel.hidden = true;
                locationToggleBtn.setAttribute('aria-expanded', 'false');
            }
            renderLocationResults([]);
            syncLocationButton();
            syncWeatherData();
            dialog.hidden = false;
            document.body.classList.add('admin-dialog-open');
            setTimeout(function () { fields.content.focus(); }, 0);
        }

        function closeDialog() {
            dialog.hidden = true;
            document.body.classList.remove('admin-dialog-open');
            row = null;
        }

        document.querySelectorAll('[data-talk-edit]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var id = btn.dataset.id;
                if (!id) return;
                btn.disabled = true;
                fetch('/admin/talk/' + encodeURIComponent(id) + '/edit', {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                    .then(function (r) { return r.json(); })
                    .then(function (res) {
                        if (!res || res.code !== 0) throw new Error((res && res.msg) || '读取失败');
                        openDialog(res.data, btn.closest('tr'));
                    })
                    .catch(function (err) {
                        toast(err.message || '读取失败', 'error');
                    })
                    .finally(function () { btn.disabled = false; });
            });
        });

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var save = form.querySelector('[data-talk-save]');
            syncWeatherData();
            var fd = new FormData(form);
            save.disabled = true;
            fetch(form.action, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: fd
            })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (!res || res.code !== 0) throw new Error((res && res.msg) || '保存失败');
                    if (row && res.data) {
                        row.querySelector('[data-talk-content]').textContent = res.data.content_preview || '';
                        updatePublicState(row, Number(res.data.is_public) === 1);
                        row.querySelector('[data-talk-time]').textContent = res.data.published_at || '';
                    }
                    closeDialog();
                    window.adminToast && window.adminToast(res.msg || '已保存', 'success');
                })
                .catch(function (err) {
                    toast(err.message || '保存失败', 'error');
                })
                .finally(function () { save.disabled = false; });
        });

        if (imageUploadBtn && imageFileInput && fields.images) {
            imageUploadBtn.addEventListener('click', function () {
                imageFileInput.click();
            });
            imageFileInput.addEventListener('change', function () {
                var file = imageFileInput.files && imageFileInput.files[0];
                if (!file) return;
                var original = imageUploadBtn.innerHTML;
                imageUploadBtn.disabled = true;
                imageUploadBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i>';
                var upload = window.adminUploadFile
                    ? window.adminUploadFile({
                        url: '/admin/posts/upload-image',
                        fields: { _csrf: '{{ $csrf }}', purpose: 'talk' },
                        fileField: 'image',
                        file: file,
                        successMessage: '图片已上传'
                    })
                    : fetch('/admin/posts/upload-image', {
                        method: 'POST',
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                        body: (function () {
                            var fd = new FormData();
                            fd.append('_csrf', '{{ $csrf }}');
                            fd.append('purpose', 'talk');
                            fd.append('image', file);
                            return fd;
                        })()
                    }).then(function (r) { return r.json(); });

                upload
                    .then(function (res) {
                        if (!res || res.code !== 0 || !res.data || !res.data.url) {
                            throw new Error((res && res.msg) || '上传失败');
                        }
                        var current = fields.images.value.trim();
                        fields.images.value = current ? current.replace(/,+$/g, '') + ',' + res.data.url : res.data.url;
                        fields.images.dispatchEvent(new Event('input', { bubbles: true }));
                    })
                    .catch(function (err) {
                        toast(err.message || '图片上传失败', 'error');
                    })
                    .finally(function () {
                        imageUploadBtn.disabled = false;
                        imageUploadBtn.innerHTML = original;
                        imageFileInput.value = '';
                    });
            });
        }

        if (fields.locationName) fields.locationName.addEventListener('input', function () { setManualLocation(fields.locationName.value); });
        if (fields.weatherLabel) fields.weatherLabel.addEventListener('input', syncWeatherData);
        if (fields.weatherTemp) fields.weatherTemp.addEventListener('input', syncWeatherData);
        if (locationToggleBtn && locationPanel) {
            locationToggleBtn.addEventListener('click', function () {
                var open = locationPanel.hidden;
                locationPanel.hidden = !open;
                locationToggleBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
                if (open && locationInput) {
                    setTimeout(function () { locationInput.focus(); }, 0);
                }
            });
        }
        if (locationInput) {
            locationInput.addEventListener('input', function () {
                clearTimeout(searchTimer);
                searchTimer = setTimeout(function () { searchLocation(locationInput.value); }, mapboxToken ? 260 : 0);
            });
            locationInput.addEventListener('keydown', function (e) {
                if (e.key !== 'Enter') return;
                e.preventDefault();
                searchLocation(locationInput.value);
            });
        }
        if (locationCurrent) {
            locationCurrent.addEventListener('click', function () {
                if (!navigator.geolocation) {
                    toast('当前浏览器不支持定位', 'error');
                    return;
                }
                renderLocationResults([], '定位中...');
                navigator.geolocation.getCurrentPosition(function (pos) {
                    reverseLocation(pos.coords.latitude, pos.coords.longitude);
                }, function () {
                    toast('没有获得定位权限', 'error');
                    renderLocationResults([], '没有获得定位权限，请搜索位置');
                }, { enableHighAccuracy: false, timeout: 9000, maximumAge: 600000 });
            });
        }
        if (locationClear) {
            locationClear.addEventListener('click', function () {
                if (locationInput) locationInput.value = '';
                renderLocationResults([]);
                setLocation(null);
                setWeather(null);
            });
        }
        if (weatherFetchBtn) {
            weatherFetchBtn.addEventListener('click', function () {
                var original = weatherFetchBtn.innerHTML;
                weatherFetchBtn.disabled = true;
                weatherFetchBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i>';
                fetchWeatherForEdit()
                    .then(function (weather) {
                        form.dataset.weatherPending = '';
                        setWeather(weather);
                        toast('天气已添加', 'success');
                    })
                    .catch(function (err) {
                        toast((err && err.message) || '天气获取失败', 'error');
                        form.dataset.weatherPending = '1';
                        if (locationPanel && locationToggleBtn) {
                            locationPanel.hidden = false;
                            locationToggleBtn.setAttribute('aria-expanded', 'true');
                        }
                        if (locationInput) setTimeout(function () { locationInput.focus(); }, 0);
                    })
                    .finally(function () {
                        weatherFetchBtn.disabled = false;
                        weatherFetchBtn.innerHTML = original;
                        syncWeatherData();
                    });
            });
        }

        function updatePublicState(sourceRow, isPublic) {
            var cell = sourceRow.querySelector('[data-talk-public]');
            var toggle = sourceRow.querySelector('[data-talk-toggle]');
            if (cell) {
                cell.innerHTML = isPublic
                    ? '<span class="status status-published">公开</span>'
                    : '<span class="status status-draft">隐藏</span>';
            }
            if (toggle) {
                toggle.title = isPublic ? '设为隐藏' : '恢复公开';
                toggle.setAttribute('aria-label', toggle.title);
                toggle.innerHTML = isPublic ? '<i class="fa-solid fa-eye-slash"></i>' : '<i class="fa-solid fa-eye"></i>';
            }
        }

        document.querySelectorAll('[data-talk-toggle]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var id = btn.dataset.id;
                if (!id) return;
                var fd = new FormData();
                fd.append('_csrf', '{{ $csrf }}');
                btn.disabled = true;
                fetch('/admin/talk/' + encodeURIComponent(id) + '/toggle', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: fd
                })
                    .then(function (r) { return r.json(); })
                    .then(function (res) {
                        if (!res || res.code !== 0) throw new Error((res && res.msg) || '操作失败');
                        updatePublicState(btn.closest('tr'), Number(res.data && res.data.is_public) === 1);
                        window.adminToast && window.adminToast(res.msg || '已更新', 'success');
                    })
                    .catch(function (err) {
                        toast(err.message || '操作失败', 'error');
                    })
                    .finally(function () { btn.disabled = false; });
            });
        });

        dialog.addEventListener('click', function (e) {
            if (e.target === dialog || e.target.classList.contains('admin-dialog-shell')) closeDialog();
        });
        document.querySelector('[data-talk-edit-cancel]').addEventListener('click', closeDialog);
        document.addEventListener('keydown', function (e) {
            if (!dialog.hidden && e.key === 'Escape') closeDialog();
        });
    })();
    </script>
@endsection
