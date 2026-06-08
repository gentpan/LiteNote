@extends('layouts.admin')

@section('content')
    <div class="settings-page-shell">
        @include('partials.admin-settings-tabs')

        @php $favicon = $favicon ?? \App\Services\FaviconService::status(); @endphp
        @if(!empty($showFavicon))
        <section class="admin-form favicon-settings-panel" data-favicon-panel>
            <h3 class="settings-group-title"><i class="fa-solid fa-icons"></i> 站点图标</h3>
            <div class="favicon-settings-body">
                <div class="favicon-preview-box">
                    <img src="{{ $favicon['preview'] ?? '/admin/assets/img/litenote-logo.svg' }}" alt="站点图标预览" width="96" height="96">
                </div>
                <div class="favicon-settings-main">
                    <div class="favicon-upload-row">
                        <input type="file" id="faviconUploadInput" accept="image/jpeg,image/png,image/gif,image/webp,image/svg+xml" hidden data-no-dirty>
                        <button type="button" class="btn" id="faviconUploadBtn"><i class="fa-solid fa-upload"></i> 上传图标</button>
                        <span class="muted small" id="faviconUploadStatus">
                            @if(!empty($favicon['updated_at']))
                                最近生成：{{ $favicon['updated_at'] }}
                            @else
                                未上传时前后台使用默认蓝色 LiteNote 图标
                            @endif
                        </span>
                    </div>
                    @if(!empty($favicon['error']))
                        <div class="alert alert-info favicon-alert"><i class="fa-solid fa-circle-info"></i> <span>{{ $favicon['error'] }}</span></div>
                    @endif
                    <div class="favicon-asset-grid">
                        @foreach(($favicon['assets'] ?? []) as $asset)
                            <span class="favicon-asset {{ !empty($asset['exists']) ? 'is-ready' : '' }}">
                                <i class="fa-solid {{ !empty($asset['exists']) ? 'fa-check' : 'fa-minus' }}"></i>
                                {{ $asset['label'] }}
                            </span>
                        @endforeach
                    </div>
                    <p class="field-hint">上传 JPG / PNG / GIF / WebP 会自动生成 favicon.ico、Apple、Android、Windows Tile 和 Manifest。SVG 会作为 SVG 图标保存；若服务器没有 SVG 栅格化组件，则不会生成 PNG/ICO。</p>
                </div>
            </div>
        </section>
        @endif

        @if(!empty($permalinkConflicts))
            <div class="alert alert-info">
                <i class="fa-solid fa-circle-info"></i>
                <span>简短模式下页面优先。以下文章 slug 与页面冲突：{{ implode('、', array_map(static fn($row) => $row['slug'], $permalinkConflicts)) }}</span>
            </div>
        @endif

        <form method="post" action="/admin/settings/save" class="admin-form" data-dirty-watch>
            <input type="hidden" name="_csrf" value="{{ $csrf }}">
            <input type="hidden" name="section" value="{{ $section ?? 'basic' }}">
            @foreach($grouped as $group => $items)
                <h3 class="settings-group-title">
                    @php
                        $groupLabels = [
                            'basic' => '基础设置',
                            'comment' => '评论设置',
                            'permalink' => '固定链接',
                            'link' => '链接设置',
                            'media' => '媒体设置',
                            'security' => '安全设置',
                        ];
                        $groupIcons = [
                            'basic' => 'fa-solid fa-sliders',
                            'comment' => 'fa-regular fa-comment-dots',
                            'reading' => 'fa-regular fa-newspaper',
                            'permalink' => 'fa-solid fa-link',
                            'link' => 'fa-solid fa-link',
                            'media' => 'fa-regular fa-image',
                            'security' => 'fa-solid fa-shield-halved',
                        ];
                    @endphp
                    <i class="{{ $groupIcons[$group] ?? 'fa-solid fa-gear' }}"></i>
                    {{ $groupLabels[$group] ?? $group }}
                </h3>
                <div class="settings-section">
                    @foreach($items as $item)
                        @php
                            $val = (string)$item['v'];
                            $type = (string)($item['type'] ?? 'string');
                            $toggleKeys = [
                                'comment_need_audit',
                                'comment_captcha',
                            ];
                            $isToggle = $type === 'bool' || in_array((string)$item['k'], $toggleKeys, true);
                            $selectOptions = [
                                'permalink_mode' => [
                                    'default' => '默认：/post/{slug}.html',
                                    'simple' => '简短：/{slug}',
                                    'category' => '分类：/{category}/{slug}',
                                    'numeric' => '数字模式',
                                ],
                                'permalink_numeric_source' => [
                                    'six' => '6 位固定码（100001 起）',
                                    'id' => '文章 ID',
                                ],
                                'permalink_numeric_suffix' => [
                                    '.html' => '.html',
                                    '' => '无后缀',
                                ],
                            ];
                            $fieldHints = [
                                'permalink_numeric_prefix' => '可为空，或填写 post、archive 等。只允许字母、数字、下划线和短横线。',
                                'permalink_mode' => '简短模式会优先匹配页面，再匹配文章；保存后如有冲突会提示。',
                                'site_analytics_code' => '填入统计平台提供的完整代码，例如 <script>...</script>。保存后会在前台页面底部自动加载。',
                                'site_mapbox_token' => '用于滔客发布时搜索/反查城市，请填写 Mapbox public token，不要填写 secret token。',
                            ];
                        @endphp
                        <div class="form-group @if($isToggle) form-group-toggle @endif">
                            <label for="setting-{{ $item['k'] }}">
                                {{ $item['label'] ?: $item['k'] }}
                            </label>

                            @if($isToggle)
                                {{-- 圆形 iOS 风格 toggle --}}
                                <div class="toggle-switch {{ $val === '1' ? 'on' : '' }}" data-key="{{ $item['k'] }}">
                                    <input type="hidden" name="settings[{{ $item['k'] }}]"
                                           value="{{ $val }}"
                                           id="setting-{{ $item['k'] }}">
                                    <button type="button" class="toggle-track" aria-pressed="{{ $val === '1' ? 'true' : 'false' }}">
                                        <span class="toggle-thumb"></span>
                                    </button>
                                    <span class="toggle-state">{{ $val === '1' ? '已开启' : '已关闭' }}</span>
                                </div>
                            @elseif(isset($selectOptions[$item['k']]))
                                <select name="settings[{{ $item['k'] }}]" id="setting-{{ $item['k'] }}">
                                    @foreach($selectOptions[$item['k']] as $optionValue => $optionLabel)
                                        <option value="{{ $optionValue }}" {{ $val === (string)$optionValue ? 'selected' : '' }}>{{ $optionLabel }}</option>
                                    @endforeach
                                </select>
                            @elseif($type === 'number')
                                @php
                                    $numberBounds = [];
                                    $bounds = $numberBounds[(string)$item['k']] ?? [];
                                @endphp
                                <input type="number" name="settings[{{ $item['k'] }}]" id="setting-{{ $item['k'] }}" value="{{ $val }}" @if(isset($bounds['min'])) min="{{ $bounds['min'] }}" @endif @if(isset($bounds['max'])) max="{{ $bounds['max'] }}" @endif>
                            @elseif(mb_strlen($val) > 100 || str_contains($val, "\n"))
                                <textarea name="settings[{{ $item['k'] }}]" id="setting-{{ $item['k'] }}" rows="3">{{ $val }}</textarea>
                            @elseif($type === 'textarea')
                                <textarea name="settings[{{ $item['k'] }}]" id="setting-{{ $item['k'] }}" rows="3">{{ $val }}</textarea>
                            @elseif($type === 'password')
                                <input type="password" name="settings[{{ $item['k'] }}]" id="setting-{{ $item['k'] }}" value="{{ $val }}" autocomplete="off">
                            @elseif($item['k'] === 'site_avatar_url')
                                <div class="setting-upload-field">
                                    <input type="url" name="settings[{{ $item['k'] }}]" id="setting-{{ $item['k'] }}" value="{{ $val }}" placeholder="https://example.com/uploads/logo.webp">
                                    <input type="file" id="siteLogoUploadInput" accept="image/jpeg,image/png,image/gif,image/webp" hidden data-no-dirty>
                                    <button type="button" class="btn" id="siteLogoUploadBtn"><i class="fa-solid fa-upload"></i> 上传</button>
                                </div>
                                <p class="field-hint" id="siteLogoUploadStatus">用于前台站点资料展示；个人头像请在个人资料里设置。</p>
                            @else
                                <input type="text" name="settings[{{ $item['k'] }}]" id="setting-{{ $item['k'] }}" value="{{ $val }}">
                            @endif
                            @if(!empty($fieldHints[$item['k']]))
                                <p class="field-hint">{{ $fieldHints[$item['k']] }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endforeach
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">保存设置</button>
            </div>
        </form>
    </div>

    <script>
    // 圆形 toggle 点击交互
    document.querySelectorAll('.toggle-switch .toggle-track').forEach(function (track) {
        track.addEventListener('click', function (e) {
            e.preventDefault();
            var wrap = this.closest('.toggle-switch');
            var input = wrap.querySelector('input[type="hidden"]');
            var state = wrap.querySelector('.toggle-state');
            var on = wrap.classList.toggle('on');
            input.value = on ? '1' : '0';
            this.setAttribute('aria-pressed', on ? 'true' : 'false');
            state.textContent = on ? '已开启' : '已关闭';
        });
    });

    (function () {
        var input = document.getElementById('faviconUploadInput');
        var btn = document.getElementById('faviconUploadBtn');
        var status = document.getElementById('faviconUploadStatus');
        var csrf = '{{ $csrf }}';
        if (!input || !btn) return;

        btn.addEventListener('click', function () {
            input.click();
        });

        input.addEventListener('change', function () {
            if (!input.files || !input.files[0]) return;
            var file = input.files[0];
            btn.disabled = true;
            status.textContent = '正在生成全套图标...';
            var upload = window.adminUploadFile
                ? window.adminUploadFile({
                    url: '/admin/settings/favicon',
                    fields: { _csrf: csrf },
                    fileField: 'favicon',
                    file: file,
                    successMessage: '图标已生成'
                })
                : (function () {
                    var fd = new FormData();
                    fd.append('_csrf', csrf);
                    fd.append('favicon', file);
                    return fetch('/admin/settings/favicon', {
                        method: 'POST',
                        body: fd,
                        credentials: 'same-origin'
                    }).then(function (r) { return r.json(); });
                })();
            upload
                .then(function (res) {
                    if (!res || res.code !== 0) {
                        throw new Error((res && res.msg) || '生成失败');
                    }
                    setTimeout(function () { window.location.reload(); }, 600);
                })
                .catch(function (err) {
                    status.textContent = err.message || '生成失败';
                    window.adminToast && window.adminToast(status.textContent, 'error');
                })
                .finally(function () {
                    btn.disabled = false;
                    input.value = '';
                });
        });
    })();

    (function () {
        var input = document.getElementById('siteLogoUploadInput');
        var btn = document.getElementById('siteLogoUploadBtn');
        var field = document.getElementById('setting-site_avatar_url');
        var status = document.getElementById('siteLogoUploadStatus');
        var csrf = '{{ $csrf }}';
        if (!input || !btn || !field) return;

        btn.addEventListener('click', function () {
            input.click();
        });

        input.addEventListener('change', function () {
            if (!input.files || !input.files[0]) return;
            var file = input.files[0];
            btn.disabled = true;
            status.textContent = '正在上传 Logo...';
            var upload = window.adminUploadFile
                ? window.adminUploadFile({
                    url: '/admin/settings/site-logo',
                    fields: { _csrf: csrf },
                    fileField: 'logo',
                    file: file,
                    successMessage: 'Logo 已上传'
                })
                : (function () {
                    var fd = new FormData();
                    fd.append('_csrf', csrf);
                    fd.append('logo', file);
                    return fetch('/admin/settings/site-logo', {
                        method: 'POST',
                        body: fd,
                        credentials: 'same-origin'
                    }).then(function (r) { return r.json(); });
                })();
            upload
                .then(function (res) {
                    if (!res || res.code !== 0) {
                        throw new Error((res && res.msg) || '上传失败');
                    }
                    field.value = res.data.url || '';
                    field.dispatchEvent(new Event('input', { bubbles: true }));
                    status.textContent = '已上传，保存设置后生效';
                })
                .catch(function (err) {
                    status.textContent = err.message || '上传失败';
                    window.adminToast && window.adminToast(status.textContent, 'error');
                })
                .finally(function () {
                    btn.disabled = false;
                    input.value = '';
                });
        });
    })();
    </script>
@endsection
