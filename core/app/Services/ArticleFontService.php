<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\FileCache;
use App\Models\Setting;

final class ArticleFontService
{
    public const MANIFEST_URL = 'https://static.bluecdn.com/manifest.json';
    public const BODY_KEY = 'post_article_font';
    public const TITLE_KEY = 'post_title_font';
    public const BODY_DEFAULT = 'source-han-serif-cn';
    public const TITLE_DEFAULT = 'kuaikanshijieti';

    private const CACHE_KEY = 'article-fonts.bluecdn-manifest';
    private const CACHE_TTL = 86400;

    /** @var list<string> */
    private const FEATURED_SLUGS = [
        'source-han-serif-cn',
        'noto-sans-sc',
        'lxgw-wenkai',
        'kuaikanshijieti',
        'luo',
    ];

    /** @var array<string, string> */
    private const SLUG_ALIASES = [
        'source-han-serif' => 'source-han-serif-cn',
        'kuaikan' => 'kuaikanshijieti',
    ];

    /** @var array<string, array{label:string,description:string,family:string,css:string,preview?:string}>|null */
    private static ?array $catalogCache = null;

    public static function ensureDefaults(): void
    {
        Setting::ensureDefaults([
            [
                'k' => self::BODY_KEY,
                'v' => self::BODY_DEFAULT,
                'type' => 'select',
                'label' => '文章正文字体',
                'group_name' => 'reading',
                'sort' => 90,
            ],
            [
                'k' => self::TITLE_KEY,
                'v' => self::TITLE_DEFAULT,
                'type' => 'select',
                'label' => '文章标题字体',
                'group_name' => 'reading',
                'sort' => 91,
            ],
        ]);
    }

    /**
     * @return array<string, array{label:string,description:string,family:string,preview?:string}>
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::catalog() as $key => $option) {
            $options[$key] = [
                'label' => $option['label'],
                'description' => $option['description'],
                'family' => $option['family'],
                'preview' => $option['preview'] ?? '',
                'css' => $option['css'],
            ];
        }
        return $options;
    }

    public static function current(): string
    {
        return self::normalizeKey((string)Setting::get(self::BODY_KEY, self::BODY_DEFAULT), self::BODY_DEFAULT);
    }

    public static function currentTitle(): string
    {
        return self::normalizeKey((string)Setting::get(self::TITLE_KEY, self::TITLE_DEFAULT), self::TITLE_DEFAULT);
    }

    public static function saveSettings(string $bodyFont, string $titleFont): void
    {
        self::ensureDefaults();
        Setting::set(self::BODY_KEY, self::normalizeKey($bodyFont, self::BODY_DEFAULT));
        Setting::set(self::TITLE_KEY, self::normalizeKey($titleFont, self::TITLE_DEFAULT));
    }

    public static function family(?string $key = null): string
    {
        $key = self::normalizeKey($key ?? self::current(), self::BODY_DEFAULT);
        return self::catalog()[$key]['family'] ?? self::fallbackCatalog()[self::BODY_DEFAULT]['family'];
    }

    public static function titleFamily(?string $key = null): string
    {
        $key = self::normalizeKey($key ?? self::currentTitle(), self::TITLE_DEFAULT);
        return self::catalog()[$key]['family'] ?? self::fallbackCatalog()[self::TITLE_DEFAULT]['family'];
    }

    public static function cssUrl(?string $key = null): string
    {
        $key = self::normalizeKey($key ?? self::current(), self::BODY_DEFAULT);
        return self::catalog()[$key]['css'] ?? self::fallbackCatalog()[self::BODY_DEFAULT]['css'];
    }

    /** @return list<string> */
    public static function cssUrlsForCurrent(): array
    {
        $urls = [
            self::cssUrl(self::current()) => true,
            self::cssUrl(self::currentTitle()) => true,
        ];
        return array_keys($urls);
    }

    public static function headHtml(bool $loadFontFiles): string
    {
        $html = '';
        if ($loadFontFiles) {
            foreach (self::cssUrlsForCurrent() as $css) {
                $html .= '<link rel="stylesheet" href="' . htmlspecialchars($css, ENT_QUOTES) . '">' . "\n";
            }
        }
        $html .= '<style>:root{'
            . '--article-font-family:' . self::family() . ';'
            . '--post-hero-title-font-family:' . self::titleFamily() . ';'
            . '}</style>';
        return $html;
    }

    /**
     * @return array<string, array{label:string,description:string,family:string,css:string,preview?:string}>
     */
    public static function catalog(): array
    {
        if (self::$catalogCache !== null) {
            return self::$catalogCache;
        }

        self::$catalogCache = (new FileCache())->remember(self::CACHE_KEY, self::CACHE_TTL, function (): array {
            $catalog = self::loadCatalogFromManifest();
            return $catalog !== [] ? $catalog : self::fallbackCatalog();
        });

        return self::$catalogCache;
    }

    public static function clearCatalogCache(): void
    {
        self::$catalogCache = null;
        (new FileCache())->forget(self::CACHE_KEY);
    }

    /**
     * @param array<string, array{label:string,description:string,family:string,css:string,preview?:string}> $catalog
     */
    public static function useCatalogForTests(array $catalog): void
    {
        self::$catalogCache = $catalog;
    }

    /**
     * @return array<string, array{label:string,description:string,family:string,css:string,preview?:string}>
     */
    private static function loadCatalogFromManifest(): array
    {
        $manifest = self::fetchManifest();
        $items = is_array($manifest['fonts']['items'] ?? null) ? $manifest['fonts']['items'] : [];
        if ($items === []) {
            return [];
        }

        $catalog = [];
        foreach ($items as $item) {
            if (!is_array($item) || ($item['category'] ?? '') !== 'cjk') {
                continue;
            }
            $slug = trim((string)($item['slug'] ?? ''));
            $family = trim((string)($item['family'] ?? ''));
            $cssUrl = trim((string)($item['cssUrl'] ?? ''));
            if ($slug === '' || $family === '' || $cssUrl === '') {
                continue;
            }
            $weights = array_filter(array_map('strval', $item['weights'] ?? ['400']));
            $catalog[$slug] = [
                'label' => trim((string)($item['name'] ?? $family)) ?: $family,
                'description' => '中文 Web 字体 · 字重 ' . implode(', ', $weights ?: ['400']),
                'family' => self::familyStack($family),
                'css' => $cssUrl,
                'preview' => trim((string)($item['previewUrl'] ?? '')),
            ];
        }

        return self::sortCatalog($catalog);
    }

    /**
     * @param array<string, array{label:string,description:string,family:string,css:string,preview?:string}> $catalog
     * @return array<string, array{label:string,description:string,family:string,css:string,preview?:string}>
     */
    private static function sortCatalog(array $catalog): array
    {
        $featured = [];
        foreach (self::FEATURED_SLUGS as $slug) {
            if (isset($catalog[$slug])) {
                $featured[$slug] = $catalog[$slug];
                unset($catalog[$slug]);
            }
        }

        uasort($catalog, static fn(array $a, array $b): int => strcmp($a['label'], $b['label']));

        return $featured + $catalog;
    }

    /**
     * @return array<string, array{label:string,description:string,family:string,css:string,preview?:string}>
     */
    private static function fallbackCatalog(): array
    {
        return [
            'source-han-serif-cn' => [
                'label' => '思源宋体',
                'description' => '适合长文阅读，偏正式的宋体风格。',
                'family' => self::familyStack('Source Han Serif CN'),
                'css' => 'https://static.bluecdn.com/fonts/source-han-serif-cn.css',
            ],
            'noto-sans-sc' => [
                'label' => 'Noto Sans SC',
                'description' => '现代无衬线，正文更清爽。',
                'family' => self::familyStack('Noto Sans SC'),
                'css' => 'https://static.bluecdn.com/fonts/noto-sans-sc.css',
            ],
            'lxgw-wenkai' => [
                'label' => '霞鹜文楷',
                'description' => '更柔和的文楷风格，适合随笔类内容。',
                'family' => self::familyStack('LXGW WenKai'),
                'css' => 'https://static.bluecdn.com/fonts/lxgw-wenkai.css',
            ],
            'kuaikanshijieti' => [
                'label' => '快看世界体',
                'description' => '展示感更强，适合轻松内容。',
                'family' => self::familyStack('快看世界体'),
                'css' => 'https://static.bluecdn.com/fonts/kuaikanshijieti.css',
            ],
            'luo' => [
                'label' => 'Luo 字体',
                'description' => 'Luo 字体，适合做个性化正文尝试。',
                'family' => self::familyStack('Luo'),
                'css' => 'https://static.bluecdn.com/fonts/luo.css',
            ],
        ];
    }

    private static function fetchManifest(): array
    {
        $context = stream_context_create([
            'http' => [
                'timeout' => 8,
                'header' => "Accept: application/json\r\n",
            ],
        ]);
        $raw = @file_get_contents(self::MANIFEST_URL, false, $context);
        if (!is_string($raw) || $raw === '') {
            return [];
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    private static function familyStack(string $family): string
    {
        $primary = trim(str_replace('"', '', $family));
        if ($primary === '') {
            return '"Noto Sans SC", "PingFang SC", "Hiragino Sans GB", "Microsoft YaHei", sans-serif';
        }

        return '"' . $primary . '", "PingFang SC", "Hiragino Sans GB", "Microsoft YaHei", sans-serif';
    }

    private static function canonicalSlug(string $slug): string
    {
        $slug = trim($slug);
        return self::SLUG_ALIASES[$slug] ?? $slug;
    }

    private static function normalizeKey(string $font, string $fallback): string
    {
        self::ensureDefaults();
        $font = self::canonicalSlug($font);
        $catalog = self::catalog();
        if (array_key_exists($font, $catalog)) {
            return $font;
        }
        $fallback = self::canonicalSlug($fallback);
        if (array_key_exists($fallback, $catalog)) {
            return $fallback;
        }
        $first = array_key_first($catalog);
        return is_string($first) ? $first : self::BODY_DEFAULT;
    }
}
