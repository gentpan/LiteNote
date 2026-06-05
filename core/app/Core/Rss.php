<?php
declare(strict_types=1);

namespace App\Core;

/**
 * 简易 RSS 2.0 生成器
 */
final class Rss
{
    /**
     * 生成 RSS XML
     * @param array $channel [title, link, description, language, atom_link]
     * @param array $items [['title','link','description','content','pubDate','author','category','guid'],...]
     */
    public static function feed(array $channel, array $items): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:content="http://purl.org/rss/1.0/modules/content/">' . "\n";
        $xml .= '<channel>' . "\n";
        $xml .= '<title>' . self::cdata($channel['title'] ?? '') . '</title>' . "\n";
        $xml .= '<link>' . self::cdata($channel['link'] ?? '') . '</link>' . "\n";
        $xml .= '<description>' . self::cdata($channel['description'] ?? '') . '</description>' . "\n";
        $xml .= '<language>' . self::cdata($channel['language'] ?? 'zh-CN') . '</language>' . "\n";
        $xml .= '<lastBuildDate>' . date('r') . '</lastBuildDate>' . "\n";
        $xml .= '<generator>LiteNote PHP 8.5</generator>' . "\n";
        if (!empty($channel['atom_link'])) {
            $xml .= '<atom:link href="' . self::attr($channel['atom_link']) . '" rel="self" type="application/rss+xml"/>' . "\n";
        }
        foreach ($items as $item) {
            $xml .= '<item>' . "\n";
            $xml .= '<title>' . self::cdata($item['title'] ?? '') . '</title>' . "\n";
            $xml .= '<link>' . self::cdata($item['link'] ?? '') . '</link>' . "\n";
            $xml .= '<guid isPermaLink="true">' . self::cdata($item['link'] ?? '') . '</guid>' . "\n";
            $xml .= '<pubDate>' . self::formatDate($item['pubDate'] ?? time()) . '</pubDate>' . "\n";
            if (!empty($item['author'])) {
                $xml .= '<dc:creator xmlns:dc="http://purl.org/dc/elements/1.1/">' . self::cdata($item['author']) . '</dc:creator>' . "\n";
            }
            if (!empty($item['category'])) {
                $xml .= '<category>' . self::cdata($item['category']) . '</category>' . "\n";
            }
            $desc = $item['description'] ?? '';
            $content = $item['content'] ?? $desc;
            $xml .= '<description>' . self::cdata($desc) . '</description>' . "\n";
            $xml .= '<content:encoded>' . self::cdata('<![CDATA[' . $content . ']]>') . '</content:encoded>' . "\n";
            $xml .= '</item>' . "\n";
        }
        $xml .= '</channel>' . "\n";
        $xml .= '</rss>';
        return $xml;
    }

    private static function cdata(string $text): string
    {
        // 去掉 <![CDATA[ ]]> 包裹后转义
        $text = preg_replace('/<!\[CDATA\[|\]\]>/', '', $text) ?? $text;
        return '<![CDATA[' . $text . ']]>';
    }

    private static function attr(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }

    private static function formatDate(string|int $date): string
    {
        $ts = is_int($date) ? $date : strtotime($date);
        if ($ts === false) $ts = time();
        return date('r', $ts);
    }
}
