<?php
declare(strict_types=1);

namespace App\Core;

/**
 * 简易 Markdown 解析器
 * 支持：标题、粗体、斜体、链接、图片、代码块、行内代码、列表、引用、水平线、表格
 * 性能足够个人博客使用，避免引入 Parsedown 等第三方依赖
 */
final class Markdown
{
    public static function parse(string $text): string
    {
        $htmlPlaceholders = [];
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = self::extractCodeBlocks($text, $htmlPlaceholders);
        $text = self::extractInlineCode($text, $htmlPlaceholders);
        $text = self::escape($text);

        // 标题
        $text = preg_replace('/^###### (.*?)$/m', '<h6>$1</h6>', $text);
        $text = preg_replace('/^##### (.*?)$/m', '<h5>$1</h5>', $text);
        $text = preg_replace('/^#### (.*?)$/m', '<h4>$1</h4>', $text);
        $text = preg_replace('/^### (.*?)$/m', '<h3>$1</h3>', $text);
        $text = preg_replace('/^## (.*?)$/m', '<h2>$1</h2>', $text);
        $text = preg_replace('/^# (.*?)$/m', '<h1>$1</h1>', $text);

        // 水平线
        $text = preg_replace('/^\s*---\s*$/m', '<hr>', $text);
        $text = preg_replace('/^\s*\*\*\*\s*$/m', '<hr>', $text);

        // 图片与链接先转成 HTML 占位符,避免 URL 中的 _、* 被强调语法破坏。
        $text = self::extractImages($text, $htmlPlaceholders);
        $text = self::extractLinks($text, $htmlPlaceholders);

        // 粗体 + 斜体
        $text = preg_replace('/\*\*\*(.+?)\*\*\*/s', '<strong><em>$1</em></strong>', $text);
        $text = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $text);
        $text = preg_replace('/\*(.+?)\*/s', '<em>$1</em>', $text);
        $text = preg_replace('/___(.+?)___/s', '<strong><em>$1</em></strong>', $text);
        $text = preg_replace('/__(.+?)__/s', '<strong>$1</strong>', $text);
        $text = preg_replace('/_(.+?)_/s', '<em>$1</em>', $text);

        // 删除线
        $text = preg_replace('/~~(.+?)~~/s', '<del>$1</del>', $text);

        // 引用
        $text = preg_replace_callback(
            '/(^|\n)((?:&gt; .*(?:\n|$))+)/',
            function ($m) {
                $prefix = $m[1];
                $body = $m[2];
                $body = preg_replace('/^&gt; /m', '', $body);
                return $prefix . '<blockquote>' . trim($body) . '</blockquote>';
            },
            $text
        );

        // 无序列表
        $text = preg_replace_callback(
            '/((?:^[\*\-] .*(?:\n|$))+)/m',
            function ($m) {
                $items = preg_replace('/^[\*\-] (.*)$/m', '<li>$1</li>', $m[1]);
                return '<ul>' . $items . '</ul>';
            },
            $text
        );

        // 有序列表
        $text = preg_replace_callback(
            '/((?:^\d+\. .*(?:\n|$))+)/m',
            function ($m) {
                $items = preg_replace('/^\d+\. (.*)$/m', '<li>$1</li>', $m[1]);
                return '<ol>' . $items . '</ol>';
            },
            $text
        );

        // 表格
        $text = self::parseTable($text);
        $text = self::restoreHtmlPlaceholders($text, $htmlPlaceholders);

        // 段落（双换行分割）
        $lines = explode("\n\n", $text);
        $result = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') continue;
            // 不在 block 元素里就包 p
            if (!preg_match('/^<(h\d|ul|ol|blockquote|pre|hr|table)/', $line)) {
                // 单换行转 <br>
                $line = nl2br($line);
                $line = '<p>' . $line . '</p>';
            }
            $result[] = $line;
        }
        return implode("\n", $result);
    }

    private static function stashHtml(string $html, array &$placeholders): string
    {
        $key = '@@MDHTML' . count($placeholders) . '@@';
        $placeholders[$key] = $html;
        return $key;
    }

    private static function restoreHtmlPlaceholders(string $text, array $placeholders): string
    {
        return $placeholders === [] ? $text : strtr($text, $placeholders);
    }

    private static function extractCodeBlocks(string $text, array &$placeholders): string
    {
        return preg_replace_callback(
            '/```([a-zA-Z0-9_-]*)\n(.*?)```/s',
            static function (array $m) use (&$placeholders): string {
                $lang = htmlspecialchars($m[1] ?? '', ENT_QUOTES, 'UTF-8');
                $code = htmlspecialchars($m[2] ?? '', ENT_QUOTES, 'UTF-8');
                return "\n" . self::stashHtml('<pre><code class="language-' . $lang . '">' . $code . '</code></pre>', $placeholders) . "\n";
            },
            $text
        ) ?? $text;
    }

    private static function extractInlineCode(string $text, array &$placeholders): string
    {
        return preg_replace_callback(
            '/`([^`\n]+?)`/',
            static function (array $m) use (&$placeholders): string {
                return self::stashHtml('<code>' . htmlspecialchars($m[1], ENT_QUOTES, 'UTF-8') . '</code>', $placeholders);
            },
            $text
        ) ?? $text;
    }

    private static function extractImages(string $text, array &$placeholders): string
    {
        return preg_replace_callback(
            '/!\[(.*?)\]\((.*?)\)/',
            static function (array $m) use (&$placeholders): string {
                [$src, $title] = self::splitLinkTarget($m[2]);
                if (!preg_match('/^(https?:|\/)/', $src)) {
                    $src = '/' . ltrim($src, '/');
                }
                $alt = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $titleAttr = $title !== '' ? ' title="' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '"' : '';
                $html = '<img src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars($alt, ENT_QUOTES, 'UTF-8') . '"' . $titleAttr . ' loading="lazy" decoding="async">';
                return self::stashHtml($html, $placeholders);
            },
            $text
        ) ?? $text;
    }

    private static function extractLinks(string $text, array &$placeholders): string
    {
        return preg_replace_callback(
            '/(?<!!)\[(.*?)\]\((.*?)\)/',
            static function (array $m) use (&$placeholders): string {
                [$url, $title] = self::splitLinkTarget($m[2]);
                if (!preg_match('/^https?:\/\//', $url) && !str_starts_with($url, '/') && !str_starts_with($url, '#')) {
                    $url = '/' . ltrim($url, '/');
                }
                $label = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $titleAttr = $title !== '' ? ' title="' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '"' : '';
                $html = '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"' . $titleAttr . ' target="_blank" rel="nofollow noopener">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a>';
                return self::stashHtml($html, $placeholders);
            },
            $text
        ) ?? $text;
    }

    /**
     * @return array{0:string,1:string}
     */
    private static function splitLinkTarget(string $raw): array
    {
        $raw = trim(html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if (preg_match('/^(\S+)\s+["\'](.+)["\']$/', $raw, $m)) {
            return [$m[1], $m[2]];
        }
        return [$raw, ''];
    }

    private static function parseTable(string $text): string
    {
        return preg_replace_callback(
            '/((?:\|.*\n)+)\|[\s\-:|]+\|?\n((?:\|.*\n?)+)/',
            function ($m) {
                $header = $m[1];
                $body = $m[2];
                $headers = array_filter(array_map('trim', explode('|', trim($header))));
                $headerHtml = '<tr>' . implode('', array_map(fn($h) => '<th>' . trim($h) . '</th>', $headers)) . '</tr>';
                $rows = array_filter(explode("\n", trim($body)));
                $bodyHtml = '';
                foreach ($rows as $row) {
                    $cells = array_filter(array_map('trim', explode('|', trim($row))));
                    $bodyHtml .= '<tr>' . implode('', array_map(fn($c) => '<td>' . $c . '</td>', $cells)) . '</tr>';
                }
                return '<table>' . $headerHtml . $bodyHtml . '</table>';
            },
            $text
        );
    }

    /**
     * 转义 Markdown 文本中的 HTML 特殊字符，但保留代码块
     */
    private static function escape(string $text): string
    {
        $placeholders = [];
        // 先把代码块提取出来
        $text = preg_replace_callback(
            '/```.*?```/s',
            function ($m) use (&$placeholders) {
                $key = "\x00CODE" . count($placeholders) . "\x00";
                $placeholders[$key] = $m[0];
                return $key;
            },
            $text
        );
        $text = preg_replace_callback(
            '/`[^`]+?`/',
            function ($m) use (&$placeholders) {
                $key = "\x00INLINE" . count($placeholders) . "\x00";
                $placeholders[$key] = $m[0];
                return $key;
            },
            $text
        );

        // 转义 < > & （不转义引号，markdown 链接括号还在）
        $text = str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $text);

        // 还原代码块（代码块内不转义）
        foreach ($placeholders as $key => $val) {
            $text = str_replace($key, $val, $text);
        }
        return $text;
    }
}
