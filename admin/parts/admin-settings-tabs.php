@php
    $__settingsPath = parse_url($_SERVER['REQUEST_URI'] ?? '/admin/settings', PHP_URL_PATH) ?: '/admin/settings';
    $__settingsTabs = [
        [
            'href' => '/admin/settings',
            'icon' => 'fa-solid fa-sliders',
            'label' => '基础设置',
            'active' => $__settingsPath === '/admin/settings' || $__settingsPath === '/admin/settings/',
        ],
        [
            'href' => '/admin/settings/reading',
            'icon' => 'fa-regular fa-newspaper',
            'label' => '阅读',
            'active' => str_starts_with($__settingsPath, '/admin/settings/reading'),
        ],
        [
            'href' => '/admin/settings/comments',
            'icon' => 'fa-regular fa-comment-dots',
            'label' => '评论',
            'active' => str_starts_with($__settingsPath, '/admin/settings/comments'),
        ],
        [
            'href' => '/admin/settings/permalinks',
            'icon' => 'fa-solid fa-link',
            'label' => '固定链接',
            'active' => str_starts_with($__settingsPath, '/admin/settings/permalinks'),
        ],
        [
            'href' => '/admin/settings/mail',
            'icon' => 'fa-regular fa-envelope',
            'label' => '邮件',
            'active' => str_starts_with($__settingsPath, '/admin/settings/mail'),
        ],
    ];
@endphp
<nav class="settings-subtabs" aria-label="设置分类">
    @foreach($__settingsTabs as $__tab)
        <a href="{{ $__tab['href'] }}" class="{{ $__tab['active'] ? 'active' : '' }}">
            <i class="{{ $__tab['icon'] }}"></i>
            <span>{{ $__tab['label'] }}</span>
        </a>
    @endforeach
</nav>
