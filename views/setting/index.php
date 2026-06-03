@extends('layouts.admin')

@section('content')
    <form method="post" action="/admin/settings/save" class="admin-form">
        <input type="hidden" name="_csrf" value="{{ $csrf }}">
        @foreach($grouped as $group => $items)
            <h3 class="settings-group-title">
                @php
                    $groupLabels = [
                        'basic' => '基础设置',
                        'comment' => '评论设置',
                        'feature' => '功能开关',
                    ];
                @endphp
                {{ $groupLabels[$group] ?? $group }}
            </h3>
            <div class="settings-section">
                @foreach($items as $item)
                    @php
                        $val = (string)$item['v'];
                        // bool 字段判定:值是 '0'/'1' 或 type='bool'
                        $isToggle = in_array($val, ['0', '1'], true) || ($item['type'] ?? '') === 'bool';
                    @endphp
                    <div class="form-group @if($isToggle) form-group-toggle @endif">
                        <label for="setting-{{ $item['k'] }}">
                            {{ $item['label'] ?: $item['k'] }}
                            <code class="setting-key">{{ $item['k'] }}</code>
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
                        @elseif(mb_strlen($val) > 100 || str_contains($val, "\n"))
                            <textarea name="settings[{{ $item['k'] }}]" id="setting-{{ $item['k'] }}" rows="3">{{ $val }}</textarea>
                        @else
                            <input type="text" name="settings[{{ $item['k'] }}]" id="setting-{{ $item['k'] }}" value="{{ $val }}">
                        @endif
                    </div>
                @endforeach
            </div>
        @endforeach
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">保存设置</button>
        </div>
    </form>

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
    </script>
@endsection
