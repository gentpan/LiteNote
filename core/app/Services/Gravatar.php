<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Gravatar 头像服务
 *
 * 国内服务器直接连 gravatar.com/avatar/ 经常超时/被墙,
 * 使用 gravatar.bluecdn.com 作为代理,行为完全等价(同 hash 路径)。
 *
 * 文档参考:
 *   https://docs.gravatar.com/api/avatars/hash/
 *
 * 用法:
 *   <img src="{{ Gravatar::url($user->email, 80) }}">
 *   <img src="{{ Gravatar::url('foo@bar.com', 60, 'identicon') }}">
 */
final class Gravatar
{
    /** 代理域名(国内可达) */
    public const PROXY_HOST = 'gravatar.bluecdn.com';

    /** 备用代理:官方站(国外服务器用) */
    public const OFFICIAL_HOST = 'www.gravatar.com';

    /**
     * 构造 Gravatar URL。
     *
     * @param string      $email   邮箱(任意大小写、含前后空格均可)
     * @param int         $size    像素(1~2048),正方形
     * @param string|null $default 默认头像类型:
     *                              - 404         : 找不到返回 404
     *                              - mp / identicon / monsterid / wavatar / retro / robohash
     *                              - blank       : 1x1 透明
     *                              - URL 字符串  : 302 跳转到该 URL
     * @param string|null $rating  g / pg / r / x
     * @param bool        $secure  true=https(默认), false=http
     * @return string 完整头像 URL
     */
    public static function url(
        string $email,
        int $size = 80,
        ?string $default = 'identicon',
        ?string $rating = 'g',
        bool $secure = true
    ): string {
        $hash = self::hash($email);
        $scheme = $secure ? 'https' : 'http';
        $host = self::host();

        $query = http_build_query(array_filter([
            's' => max(1, min(2048, $size)),
            'd' => $default,
            'r' => $rating,
            'v' => '1.3',  // cache buster,任填,这里固定 1.3 即可
        ]));

        return sprintf('%s://%s/avatar/%s?%s', $scheme, $host, $hash, $query);
    }

    /**
     * Gravatar 邮箱哈希 = md5(strtolower(trim($email)))
     * 邮箱为空时,用全 0 哈希(即"00000000000000000000000000000000"),
     * 会触发 default 参数指定的兜底图。
     */
    public static function hash(string $email): string
    {
        $email = strtolower(trim($email));
        if ($email === '') {
            return str_repeat('0', 32);
        }
        return md5($email);
    }

    /**
     * 选择 host。
     * 优先用蓝云 CDN 代理(国内可达),失败/环境变量指定时回退到官方。
     * 也可以在 .env 或 config.php 里设 'gravatar.host' 覆盖。
     */
    public static function host(): string
    {
        $override = \App\Core\Config::get('gravatar.host');
        if (is_string($override) && $override !== '') {
            return $override;
        }
        return self::PROXY_HOST;
    }

    /**
     * 头像对应的 <img> 标签(带 alt + 懒加载 + 错误回退到默认 identicon)。
     *
     * @param string|null $email
     * @param string|null $name  alt 文本(昵称/用户名)
     */
    public static function img(?string $email, int $size = 60, ?string $name = null, array $attrs = []): string
    {
        $alt = htmlspecialchars((string)($name ?? $email ?? 'avatar'), ENT_QUOTES, 'UTF-8');
        $src = self::url((string)$email, $size);
        $attrStr = '';
        foreach ($attrs as $k => $v) {
            $attrStr .= ' ' . htmlspecialchars((string)$k, ENT_QUOTES, 'UTF-8')
                . '="' . htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8') . '"';
        }
        return sprintf(
            '<img src="%s" alt="%s" width="%d" height="%d" loading="lazy" class="avatar"%s>',
            htmlspecialchars($src, ENT_QUOTES, 'UTF-8'),
            $alt,
            $size, $size,
            $attrStr
        );
    }
}
