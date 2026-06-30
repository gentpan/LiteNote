<?php
declare(strict_types=1);

namespace Tests;

use App\Core\Config;
use App\Services\PublishedAsset;
use PHPUnit\Framework\TestCase;

final class PublishedAssetTest extends TestCase
{
    protected function tearDown(): void
    {
        Config::set('app.debug', false);
    }

    public function testProductionUsesMinifiedJs(): void
    {
        Config::set('app.debug', false);
        $url = PublishedAsset::url('/themes/ember/assets/main.js');
        $this->assertSame('/themes/ember/assets/main.min.js', $url);
    }

    public function testDebugUsesSourceJs(): void
    {
        Config::set('app.debug', true);
        $url = PublishedAsset::url('/themes/ember/assets/main.js');
        $this->assertSame('/themes/ember/assets/main.js', $url);
    }

    public function testProductionUsesMinifiedCss(): void
    {
        Config::set('app.debug', false);
        $url = PublishedAsset::url('/themes/ember/assets/home.css');
        $this->assertSame('/themes/ember/assets/home.min.css', $url);
    }

    public function testBlocksUncompressedWhenMinExists(): void
    {
        Config::set('app.debug', false);
        $base = dirname(__DIR__);
        $public = '/themes/ember/assets/main.js';
        $absolute = $base . '/themes/ember/assets/main.js';
        $this->assertTrue(PublishedAsset::isUncompressedBlocked($public, $absolute));
        $this->assertFalse(PublishedAsset::isUncompressedBlocked('/themes/ember/assets/main.min.js', $base . '/themes/ember/assets/main.min.js'));
    }
}
