<?php
declare(strict_types=1);

namespace Tests;

use App\Core\Config;
use App\Core\FileCache;
use App\Services\ActionRateLimiter;
use PHPUnit\Framework\TestCase;

final class ActionRateLimiterTest extends TestCase
{
    private string $cacheDir;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/litenote-rate-test-' . bin2hex(random_bytes(4));
        mkdir($this->cacheDir, 0775, true);
        Config::set('cache.path', $this->cacheDir);
    }

    protected function tearDown(): void
    {
        $files = glob($this->cacheDir . '/*') ?: [];
        foreach ($files as $file) {
            @unlink($file);
        }
        @rmdir($this->cacheDir);
    }

    public function testBlocksAfterMaxAttempts(): void
    {
        $scope = 'test_scope_' . bin2hex(random_bytes(3));
        $ip = '203.0.113.10';

        $this->assertFalse(ActionRateLimiter::tooMany($scope, $ip, 2, 60));
        ActionRateLimiter::hit($scope, $ip, 2, 60);
        $this->assertFalse(ActionRateLimiter::tooMany($scope, $ip, 2, 60));
        ActionRateLimiter::hit($scope, $ip, 2, 60);
        $this->assertTrue(ActionRateLimiter::tooMany($scope, $ip, 2, 60));
    }
}
