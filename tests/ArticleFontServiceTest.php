<?php
declare(strict_types=1);

use App\Services\ArticleFontService;
use PHPUnit\Framework\TestCase;

final class ArticleFontServiceTest extends TestCase
{
    protected function setUp(): void
    {
        ArticleFontService::useCatalogForTests([
            'source-han-serif-cn' => [
                'label' => '思源宋体',
                'description' => 'test',
                'family' => '"Source Han Serif CN", sans-serif',
                'css' => 'https://static.bluecdn.com/fonts/source-han-serif-cn.css',
            ],
            'kuaikanshijieti' => [
                'label' => '快看世界体',
                'description' => 'test',
                'family' => '"快看世界体", sans-serif',
                'css' => 'https://static.bluecdn.com/fonts/kuaikanshijieti.css',
            ],
        ]);
    }

    public function testCanonicalSlugAlias(): void
    {
        ArticleFontService::useCatalogForTests([
            'source-han-serif-cn' => [
                'label' => '思源宋体',
                'description' => 'test',
                'family' => '"Source Han Serif CN", sans-serif',
                'css' => 'https://static.bluecdn.com/fonts/source-han-serif-cn.css',
            ],
        ]);

        $this->assertSame(
            '"Source Han Serif CN", sans-serif',
            ArticleFontService::family('source-han-serif')
        );
    }

    public function testCssUrlUsesCanonicalSlug(): void
    {
        $this->assertSame(
            'https://static.bluecdn.com/fonts/kuaikanshijieti.css',
            ArticleFontService::cssUrl('kuaikan')
        );
    }

    public function testHeadHtmlIncludesCurrentFonts(): void
    {
        ArticleFontService::useCatalogForTests([
            'noto-sans-sc' => [
                'label' => 'Noto Sans SC',
                'description' => 'test',
                'family' => '"Noto Sans SC", sans-serif',
                'css' => 'https://static.bluecdn.com/fonts/noto-sans-sc.css',
            ],
            'luo' => [
                'label' => 'Luo',
                'description' => 'test',
                'family' => '"Luo", sans-serif',
                'css' => 'https://static.bluecdn.com/fonts/luo.css',
            ],
        ]);

        \App\Models\Setting::set(ArticleFontService::BODY_KEY, 'noto-sans-sc');
        \App\Models\Setting::set(ArticleFontService::TITLE_KEY, 'luo');

        $html = ArticleFontService::headHtml(true);
        $this->assertStringContainsString('noto-sans-sc.css', $html);
        $this->assertStringContainsString('luo.css', $html);
        $this->assertStringContainsString('--article-font-family:"Noto Sans SC", sans-serif', $html);
        $this->assertStringContainsString('--post-hero-title-font-family:"Luo", sans-serif', $html);
    }
}
