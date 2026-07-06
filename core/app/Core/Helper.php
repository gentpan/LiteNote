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

    /**
     * 浏览器标签标题：内页为「页面标题 - 站点标题」，首页为「站点标题 - 副标题」。
     *
     * @param array<string, mixed> $site
     */
    public static function documentTitle(?string $pageTitle, array $site): string
    {
        $siteTitle = trim((string)($site['title'] ?? '')) ?: 'LiteNote';
        $siteSubtitle = trim((string)($site['subtitle'] ?? ''));
        $pageTitleText = trim((string)($pageTitle ?? ''));

        if ($pageTitleText !== '') {
            return $pageTitleText . ' - ' . $siteTitle;
        }
        if ($siteSubtitle !== '') {
            return $siteTitle . ' - ' . $siteSubtitle;
        }

        return $siteTitle;
    }

    public static function categoryUrl(string $slug): string
    {
        return self::url('/category/' . rawurlencode($slug));
    }

    public static function asset(string $path): string
    {
        return self::url('/admin/assets/' . ltrim($path, '/'));
    }

    public static function e(?string $str): string
    {
        return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
    }

    public static function publicErrorMessage(\Throwable $e, string $fallback = '操作失败，请稍后重试。'): string
    {
        if (Config::get('app.debug', false)) {
            $message = trim($e->getMessage());
            return $message !== '' ? $message : $fallback;
        }
        error_log($e->getMessage());
        return $fallback;
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
            $diff < 60         => max(1, $diff) . ' 秒前',
            $diff < 3600       => floor($diff / 60) . ' 分钟前',
            $diff < 86400      => floor($diff / 3600) . ' 小时前',
            $diff < 86400 * 7  => floor($diff / 86400) . ' 天前',
            default            => self::formatDate($ts, 'Y-m-d H:i'),
        };
    }

    public static function fullDate(string|int|\DateTimeInterface $date): string
    {
        return self::formatDate(self::timestamp($date), 'Y-m-d H:i:s');
    }

    public static function timeTag(string|int|\DateTimeInterface $date, ?string $class = null): string
    {
        $ts = self::timestamp($date);
        $label = self::humanDate($ts);
        $absolute = self::formatDate($ts, 'Y-m-d H:i');
        $classes = trim('time-tag' . ($class ? ' ' . $class : ''));
        $isRelative = time() - $ts >= 0 && time() - $ts < 86400 * 7;
        $attrs = [
            'class' => $classes,
            'datetime' => date('c', $ts),
        ];
        if ($isRelative) {
            $attrs['data-time-relative'] = $label;
            $attrs['data-time-absolute'] = $absolute;
        }
        $attrText = '';
        foreach ($attrs as $name => $value) {
            $attrText .= ' ' . $name . '="' . self::e((string)$value) . '"';
        }
        return '<time' . $attrText . '>'
            . self::e($label)
            . '</time>';
    }

    public static function dateTimeTag(string|int|\DateTimeInterface $date, ?string $class = null): string
    {
        $ts = self::timestamp($date);
        $label = self::formatDate($ts, 'Y-m-d H:i');
        $classes = trim('time-tag' . ($class ? ' ' . $class : ''));
        $classAttr = ' class="' . self::e($classes) . '"';
        return '<time' . $classAttr . ' datetime="' . self::e(date('c', $ts)) . '">'
            . self::e($label)
            . '</time>';
    }

    public static function compactNumber(int|float $number): string
    {
        $value = max(0, (float)$number);
        if ($value < 1000) {
            return (string)(int)$value;
        }

        $decimals = $value < 10000 ? 1 : 0;
        $compact = number_format($value / 1000, $decimals, '.', '');
        return preg_replace('/\.0$/', '', $compact) . 'k';
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
        $ascii = function_exists('iconv') ? @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $base) : false;
        $base = is_string($ascii) && $ascii !== '' ? $ascii : $base;
        $base = preg_replace('/[^A-Za-z0-9_-]+/', '_', $base) ?? '';
        $base = trim($base, '._-');
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

    /**
     * 「加载更多」控件 HTML(替代分页)。JS 负责自动/手动加载。
     */
    public static function loadMore(int $current, int $total, int $perPage, string $baseUrl): string
    {
        $pages = (int) ceil($total / max(1, $perPage));
        if ($total <= 0) {
            return '';
        }
        if ($pages <= 1) {
            return '<div class="load-more is-end"><div class="load-more-end"><i class="fa-regular fa-circle-check"></i> 没有更多内容</div></div>';
        }
        $current = max(1, min($current, $pages));
        // 用相对路径,避免 ajax 跨域(host 不一致时)
        $baseUrl = preg_replace('#^https?://[^/]+#i', '', $baseUrl) ?: $baseUrl;
        return '<div class="load-more" data-base="' . htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') . '" data-page="' . $current . '" data-pages="' . $pages . '">'
            . '<button type="button" class="load-more-btn" hidden>加载更多</button>'
            . '<div class="load-more-loading" hidden><span class="load-more-spinner"></span><span>加载中…</span></div>'
            . '<div class="load-more-end" hidden><i class="fa-regular fa-circle-check"></i> 没有更多内容</div>'
            . '</div>';
    }

    /**
     * 把扁平评论列表组织为「顶层评论 + 其下所有回复(按时间)」。
     * @return array<int, array{comment: object, replies: array}>
     */
    public static function nestComments(array $comments): array
    {
        $byId = [];
        foreach ($comments as $c) {
            $byId[(int) $c->id] = $c;
        }
        $childrenOf = [];
        $roots = [];
        foreach ($comments as $c) {
            $pid = (int) ($c->parent_id ?? 0);
            if ($pid > 0 && isset($byId[$pid])) {
                $childrenOf[$pid][] = $c;
            } else {
                $roots[] = $c;
            }
        }
        $result = [];
        foreach ($roots as $root) {
            $replies = [];
            $stack = [(int) $root->id];
            while ($stack) {
                $pid = array_shift($stack);
                foreach ($childrenOf[$pid] ?? [] as $child) {
                    $child->reply_to_name = (string) ($byId[$pid]->nickname ?? '');
                    $replies[] = $child;
                    $stack[] = (int) $child->id;
                }
            }
            usort($replies, fn($a, $b) => (int) $a->id <=> (int) $b->id);
            $result[] = ['comment' => $root, 'replies' => $replies];
        }
        return $result;
    }
}
