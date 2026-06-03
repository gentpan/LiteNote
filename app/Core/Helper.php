<?php
declare(strict_types=1);

namespace App\Core;

/**
 * 通用辅助函数
 */
final class Helper
{
    public static function url(string $path = '/'): string
    {
        $base = rtrim(Config::get('app.url', ''), '/');
        $path = '/' . ltrim($path, '/');
        return $base . $path;
    }

    public static function asset(string $path): string
    {
        return self::url('/assets/' . ltrim($path, '/'));
    }

    public static function e(?string $str): string
    {
        return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
    }

    public static function slugify(string $text): string
    {
        // 中文转拼音跳过，直接保留
        $text = trim($text);
        // 替换非字母数字为 -
        $text = preg_replace('/[^\p{L}\p{N}]+/u', '-', $text) ?? '';
        $text = trim($text, '-');
        $text = mb_strtolower($text);
        if ($text === '') {
            $text = substr(md5(uniqid('', true)), 0, 8);
        }
        return $text;
    }

    public static function formatDate(string|int|\DateTimeInterface $date, string $format = 'Y-m-d H:i'): string
    {
        if (is_int($date)) {
            return date($format, $date);
        } elseif ($date instanceof \DateTimeInterface) {
            $d = $date;
        } else {
            try {
                $d = new \DateTime($date);
            } catch (\Exception) {
                return '';
            }
        }
        return $d->format($format);
    }

    public static function humanDate(string|int|\DateTimeInterface $date): string
    {
        $ts = self::timestamp($date);
        $now = time();
        $diff = max(0, $now - $ts);
        return match (true) {
            $diff < 60        => '刚刚',
            $diff < 3600      => floor($diff / 60) . ' 分钟前',
            $diff < 86400     => floor($diff / 3600) . ' 小时前',
            $diff < 86400 * 30 => floor($diff / 86400) . ' 天前',
            $diff < 86400 * 365 => floor($diff / (86400 * 30)) . ' 个月前',
            default           => floor($diff / (86400 * 365)) . ' 年前',
        };
    }

    public static function fullDate(string|int|\DateTimeInterface $date): string
    {
        return self::formatDate(self::timestamp($date), 'Y-m-d H:i:s');
    }

    public static function timeTag(string|int|\DateTimeInterface $date, ?string $class = null): string
    {
        $ts = self::timestamp($date);
        $full = self::formatDate($ts, 'Y-m-d H:i:s');
        $classAttr = $class ? ' class="' . self::e($class) . '"' : '';
        return '<time' . $classAttr . ' datetime="' . self::e(date('c', $ts)) . '" title="' . self::e($full) . '">'
            . self::e(self::humanDate($ts))
            . '</time>';
    }

    private static function timestamp(string|int|\DateTimeInterface $date): int
    {
        return match (true) {
            $date instanceof \DateTimeInterface => $date->getTimestamp(),
            is_int($date)                      => $date,
            default                            => strtotime((string)$date) ?: time(),
        };
    }

    public static function truncate(string $text, int $length = 200, string $suffix = '...'): string
    {
        $text = strip_tags($text);
        $text = preg_replace('/\s+/', ' ', $text) ?? '';
        if (mb_strlen($text) <= $length) return $text;
        return mb_substr($text, 0, $length) . $suffix;
    }

    public static function highlight(string $text, string $keyword): string
    {
        if ($keyword === '') return $text;
        return preg_replace_callback(
            '/' . preg_quote($keyword, '/') . '/iu',
            fn($m) => '<mark>' . $m[0] . '</mark>',
            $text
        ) ?? $text;
    }

    public static function clientIp(): string
    {
        $keys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        foreach ($keys as $k) {
            if (!empty($_SERVER[$k])) {
                $ip = explode(',', $_SERVER[$k])[0];
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return '0.0.0.0';
    }

    public static function isHttps(): bool
    {
        return (
            ($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off'
            || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'
            || ($_SERVER['SERVER_PORT'] ?? '') == 443
        );
    }

    public static function randomToken(int $length = 32): string
    {
        return bin2hex(random_bytes($length));
    }

    public static function safeFilename(string $name): string
    {
        $ext = pathinfo($name, PATHINFO_EXTENSION);
        $base = pathinfo($name, PATHINFO_FILENAME);
        $base = preg_replace('/[^\p{L}\p{N}_-]/u', '_', $base) ?? '';
        if ($base === '') $base = 'file';
        return $base . '_' . substr(md5(uniqid('', true)), 0, 6) . '.' . strtolower($ext);
    }

    public static function bytesToHuman(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * 简易分页 HTML
     */
    public static function paginate(int $current, int $total, int $perPage, string $baseUrl): string
    {
        if ($total <= $perPage) return '';
        $pages = (int)ceil($total / $perPage);
        $current = max(1, min($current, $pages));
        $html = '<div class="pagination">';
        if ($current > 1) {
            $html .= '<a href="' . self::buildUrl($baseUrl, ['page' => $current - 1]) . '">&laquo; 上一页</a>';
        }
        $start = max(1, $current - 3);
        $end   = min($pages, $current + 3);
        if ($start > 1) {
            $html .= '<a href="' . self::buildUrl($baseUrl, ['page' => 1]) . '">1</a>';
            if ($start > 2) $html .= '<span>...</span>';
        }
        for ($i = $start; $i <= $end; $i++) {
            if ($i === $current) {
                $html .= '<span class="active">' . $i . '</span>';
            } else {
                $html .= '<a href="' . self::buildUrl($baseUrl, ['page' => $i]) . '">' . $i . '</a>';
            }
        }
        if ($end < $pages) {
            if ($end < $pages - 1) $html .= '<span>...</span>';
            $html .= '<a href="' . self::buildUrl($baseUrl, ['page' => $pages]) . '">' . $pages . '</a>';
        }
        if ($current < $pages) {
            $html .= '<a href="' . self::buildUrl($baseUrl, ['page' => $current + 1]) . '">下一页 &raquo;</a>';
        }
        $html .= '</div>';
        return $html;
    }

    public static function buildUrl(string $base, array $params): string
    {
        $sep = str_contains($base, '?') ? '&' : '?';
        return $base . $sep . http_build_query($params);
    }
}
