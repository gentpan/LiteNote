<?php
declare(strict_types=1);

namespace App\Models;

use App\Services\Gravatar;

final class User extends Model
{
    protected static string $table = 'users';

    public static function byUsername(string $username): ?self
    {
        return self::findBy('username', $username);
    }

    public function verifyPassword(string $password): bool
    {
        return password_verify($password, $this->password);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * 头像 URL(优先级:用户手动填的 avatar 字段 > gravatar 邮箱哈希)。
     */
    public function getAvatarUrl(int $size = 80): string
    {
        $avatar = trim((string)($this->avatar ?? ''));
        if ($avatar !== '') {
            return $avatar;
        }
        return Gravatar::url((string)($this->email ?? ''), $size, 'identicon');
    }

    /**
     * 社交链接数组。结构: [['key'=>..., 'url'=>..., 'icon'=>..., 'label'=>..., 'qr'=>...], ...]
     * - key  : 平台标识(github / x / email / website ...),用作 HTML id/类名
     * - url  : 完整 URL
     * - icon : 用户填的 fontawesome HTML 片段,已 sanitize
     * - label: 显示名(优先用用户填的,缺省从 key 推断,例如 github → GitHub)
     */
    public function getSocialLinks(): array
    {
        $raw = (string)($this->socials ?? '');
        if ($raw === '') return [];

        $arr = json_decode($raw, true);
        if (!is_array($arr)) return [];

        $out = [];
        foreach ($arr as $row) {
            if (!is_array($row)) continue;
            $key  = trim((string)($row['key'] ?? ''));
            $url  = trim((string)($row['url'] ?? ''));
            $icon = self::sanitizeIcon((string)($row['icon'] ?? ''));
            $qr = trim((string)($row['qr'] ?? ''));
            $lowerKey = strtolower($key);
            $path = parse_url($url, PHP_URL_PATH) ?: $url;
            if (in_array($lowerKey, ['rss', 'feed'], true) || in_array(rtrim($path, '/'), ['/rss.xml', '/feed'], true)) continue;
            $isQrPlatform = in_array(strtolower($key), ['wechat', 'weixin', 'telegram'], true);
            if ($key === '' || ($url === '' && (!$isQrPlatform || $qr === ''))) continue;
            $out[] = [
                'key'   => $key,
                'url'   => $url,
                'icon'  => $icon,
                'label' => trim((string)($row['label'] ?? '')) ?: self::labelFromKey($key),
                'qr'    => $qr,
            ];
        }
        return $out;
    }

    /**
     * 直接写社交链接(JSON 字符串)。调用方应先过滤数据。
     */
    public function setSocialLinks(array $links): void
    {
        $clean = [];
        foreach ($links as $row) {
            if (!is_array($row)) continue;
            $key  = trim((string)($row['key'] ?? ''));
            $url  = trim((string)($row['url'] ?? ''));
            $icon = self::sanitizeIcon((string)($row['icon'] ?? ''));
            $qr = trim((string)($row['qr'] ?? ''));
            $lowerKey = strtolower($key);
            $path = parse_url($url, PHP_URL_PATH) ?: $url;
            if (in_array($lowerKey, ['rss', 'feed'], true) || in_array(rtrim($path, '/'), ['/rss.xml', '/feed'], true)) continue;
            $isQrPlatform = in_array(strtolower($key), ['wechat', 'weixin', 'telegram'], true);
            if ($key === '' || ($url === '' && (!$isQrPlatform || $qr === ''))) continue;
            $clean[] = [
                'key'   => $key,
                'url'   => $url,
                'icon'  => $icon,
                'label' => trim((string)($row['label'] ?? '')) ?: self::labelFromKey($key),
                'qr'    => $qr,
            ];
        }
        $this->socials = $clean ? json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
    }

    /**
     * 净化用户输入的 fontawesome 图标 HTML。
     * 白名单:
     *   - 必须是 <i class="..."></i> 结构
     *   - class 值只能含 [a-z0-9 _-] 字符
     *   - 不允许任何 on* 事件、style、script 等属性
     * 失败时返回安全的占位 icon。
     */
    public static function sanitizeIcon(string $icon): string
    {
        $icon = trim($icon);
        if ($icon === '') {
            return '<i class="fa-solid fa-link"></i>';
        }
        // 用户可能只填 class 名(如 "fa-brands fa-github"),自动包成 <i>
        if (!str_starts_with($icon, '<i')) {
            if (preg_match('/^[a-zA-Z0-9 _\-]+$/', $icon)) {
                return '<i class="' . $icon . '"></i>';
            }
            return '<i class="fa-solid fa-link"></i>';
        }
        // 严格匹配 <i class="..."></i> 结构
        if (preg_match('#^<i\s+class="([a-zA-Z0-9 _\-]+)"\s*></i>$#', $icon, $m)) {
            return '<i class="' . $m[1] . '"></i>';
        }
        return '<i class="fa-solid fa-link"></i>';
    }

    /**
     * 从 key 推断默认显示名。
     */
    public static function labelFromKey(string $key): string
    {
        static $map = [
            'github'   => 'GitHub',
            'x'        => 'X',
            'twitter'  => 'Twitter',
            'weibo'    => '微博',
            'wechat'   => '微信',
            'email'    => '邮箱',
            'website'  => '网站',
            'telegram' => 'Telegram',
            'mastodon' => 'Mastodon',
            'bilibili' => '哔哩哔哩',
            'zhihu'    => '知乎',
            'rss'      => 'RSS',
        ];
        $k = strtolower($key);
        return $map[$k] ?? ucfirst($k);
    }

    /**
     * 预置常用平台的 (key, default icon, label) 列表,供前端"快速添加"按钮用。
     * @return array<int, array{key:string, icon:string, label:string}>
     */
    public static function presetSocials(): array
    {
        return [
            ['key' => 'github',   'icon' => 'fa-brands fa-github',         'label' => 'GitHub'],
            ['key' => 'x',        'icon' => 'fa-brands fa-x-twitter',      'label' => 'X'],
            ['key' => 'weibo',    'icon' => 'fa-brands fa-weibo',          'label' => '微博'],
            ['key' => 'wechat',   'icon' => 'fa-brands fa-weixin',         'label' => '微信'],
            ['key' => 'email',    'icon' => 'fa-solid fa-envelope',        'label' => '邮箱'],
            ['key' => 'website',  'icon' => 'fa-solid fa-globe',           'label' => '网站'],
            ['key' => 'telegram', 'icon' => 'fa-brands fa-telegram',       'label' => 'Telegram'],
            ['key' => 'mastodon', 'icon' => 'fa-brands fa-mastodon',       'label' => 'Mastodon'],
            ['key' => 'bilibili', 'icon' => 'fa-brands fa-bilibili',       'label' => '哔哩哔哩'],
        ];
    }
}
