<?php
declare(strict_types=1);

use App\Core\Config;
use App\Services\RemoteUrlValidator;
use App\Services\SecretCipher;
use PHPUnit\Framework\TestCase;

final class SecurityServicesTest extends TestCase
{
    protected function setUp(): void
    {
        Config::load('config');
        Config::set('app.key', 'test-secret-key-with-32-bytes-min!!');
    }

    public function testSecretCipherRoundTrip(): void
    {
        $plain = 'spotify-access-token-12345';
        $encrypted = SecretCipher::encrypt($plain);
        $this->assertNotSame($plain, $encrypted);
        $this->assertSame($plain, SecretCipher::decrypt($encrypted));
    }

    public function testSecretCipherLegacyPlaintextPassthrough(): void
    {
        $this->assertSame('legacy-token', SecretCipher::decrypt('legacy-token'));
    }

    public function testRemoteUrlValidatorBlocksPrivateHosts(): void
    {
        $this->assertFalse(RemoteUrlValidator::isSafePublicUrl('http://127.0.0.1/rss.xml'));
        $this->assertFalse(RemoteUrlValidator::isSafePublicUrl('ftp://example.com/file'));
    }

    public function testRemoteUrlValidatorAllowsHttpsPublicHost(): void
    {
        $this->assertTrue(RemoteUrlValidator::isSafePublicUrl('https://example.com/feed.xml'));
    }
}
