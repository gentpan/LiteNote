<?php
declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

final class Php86CompatibilityTest extends TestCase
{
    public function testRuntimeIsInsideSupportedPhpRange(): void
    {
        self::assertTrue(version_compare(PHP_VERSION, '8.5.0', '>='));
        self::assertTrue(version_compare(PHP_VERSION, '9.0.0', '<'));
    }

    public function testSessionUsesPhp86SecureDefaultsExplicitly(): void
    {
        self::assertSame('1', ini_get('session.use_strict_mode'));
        self::assertSame('1', ini_get('session.cookie_httponly'));
        self::assertSame('Lax', ini_get('session.cookie_samesite'));

        $params = session_get_cookie_params();
        self::assertTrue($params['httponly']);
        self::assertSame('Lax', $params['samesite']);
    }
}
