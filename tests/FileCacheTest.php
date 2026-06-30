<?php
declare(strict_types=1);

namespace Tests;

use App\Core\FileCache;
use PHPUnit\Framework\TestCase;

final class FileCacheTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/litenote-cache-test-' . bin2hex(random_bytes(4));
        mkdir($this->dir, 0775, true);
    }

    protected function tearDown(): void
    {
        $files = glob($this->dir . '/*') ?: [];
        foreach ($files as $file) {
            @unlink($file);
        }
        @rmdir($this->dir);
    }

    public function testRememberStoresAndReturnsValue(): void
    {
        $cache = new FileCache($this->dir);
        $calls = 0;
        $value = $cache->remember('demo.key', 60, function () use (&$calls): string {
            $calls++;
            return 'cached-value';
        });

        $this->assertSame('cached-value', $value);
        $this->assertSame(1, $calls);
        $this->assertSame('cached-value', $cache->get('demo.key'));
    }

    public function testForgetRemovesValue(): void
    {
        $cache = new FileCache($this->dir);
        $cache->set('gone', 'value');
        $cache->forget('gone');
        $this->assertNull($cache->get('gone'));
    }
}
