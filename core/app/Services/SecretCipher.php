<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;

/**
 * 使用 APP_KEY 对称加密敏感凭据（OAuth token、S3 secret 等）。
 */
final class SecretCipher
{
    private const PREFIX = 'ln1:';

    public static function encrypt(string $plain): string
    {
        $plain = trim($plain);
        if ($plain === '') {
            return '';
        }
        if (str_starts_with($plain, self::PREFIX)) {
            return $plain;
        }

        $key = self::deriveKey();
        if (function_exists('sodium_crypto_secretbox')) {
            $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $cipher = sodium_crypto_secretbox($plain, $nonce, $key);
            return self::PREFIX . base64_encode($nonce . $cipher);
        }

        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false) {
            throw new \RuntimeException('凭据加密失败');
        }
        return self::PREFIX . base64_encode($iv . $tag . $cipher);
    }

    public static function decrypt(string $stored): string
    {
        $stored = trim($stored);
        if ($stored === '') {
            return '';
        }
        if (!str_starts_with($stored, self::PREFIX)) {
            return $stored;
        }

        $raw = base64_decode(substr($stored, strlen(self::PREFIX)), true);
        if ($raw === false || $raw === '') {
            return '';
        }

        $key = self::deriveKey();
        if (function_exists('sodium_crypto_secretbox_open')) {
            if (strlen($raw) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES) {
                return '';
            }
            $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $cipher = substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $plain = sodium_crypto_secretbox_open($cipher, $nonce, $key);
            return $plain === false ? '' : (string) $plain;
        }

        if (strlen($raw) < 28) {
            return '';
        }
        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $cipher = substr($raw, 28);
        $plain = openssl_decrypt($cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        return $plain === false ? '' : (string) $plain;
    }

    private static function deriveKey(): string
    {
        return hash('sha256', (string) Config::get('app.key', ''), true);
    }
}
