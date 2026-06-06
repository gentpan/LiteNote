<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Config;
use App\Core\Helper;

final class MailUnsubscribe extends Model
{
    protected static string $table = 'mail_unsubscribes';
    protected static array $sortable = ['id', 'created_at', 'mail_type'];
    private static bool $schemaReady = false;

    public static function ensureTable(): void
    {
        if (self::$schemaReady) {
            return;
        }
        self::$schemaReady = true;
        self::db()->query(<<<SQL
        CREATE TABLE IF NOT EXISTS mail_unsubscribes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email VARCHAR(160) NOT NULL,
            mail_type VARCHAR(60) NOT NULL DEFAULT 'all',
            token VARCHAR(80) NOT NULL,
            ip VARCHAR(45),
            ua VARCHAR(255),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(email, mail_type)
        )
        SQL);
        try {
            self::db()->query('CREATE INDEX IF NOT EXISTS idx_mail_unsub_email ON mail_unsubscribes(email)');
        } catch (\Throwable) {
            // ignore
        }
    }

    public static function url(string $email, string $type = 'all'): string
    {
        $email = self::normalizeEmail($email);
        if ($email === '') {
            return '';
        }
        $type = self::normalizeType($type);
        return Helper::url('/mail/unsubscribe?email=' . rawurlencode($email)
            . '&type=' . rawurlencode($type)
            . '&token=' . rawurlencode(self::token($email, $type)));
    }

    public static function verify(string $email, string $type, string $token): bool
    {
        $email = self::normalizeEmail($email);
        $type = self::normalizeType($type);
        return $email !== '' && hash_equals(self::token($email, $type), $token);
    }

    public static function unsubscribe(string $email, string $type = 'all', string $ip = '', string $ua = ''): void
    {
        self::ensureTable();
        $email = self::normalizeEmail($email);
        if ($email === '') {
            return;
        }
        $type = self::normalizeType($type);
        $token = self::token($email, $type);
        $existing = self::db()->fetchOne('SELECT id FROM mail_unsubscribes WHERE email = ? AND mail_type = ? LIMIT 1', [$email, $type]);
        if ($existing) {
            self::db()->update('mail_unsubscribes', [
                'token' => $token,
                'ip' => $ip,
                'ua' => mb_substr($ua, 0, 250),
                'created_at' => date('Y-m-d H:i:s'),
            ], 'id = :id', [':id' => (int)$existing['id']]);
            return;
        }

        self::db()->insert('mail_unsubscribes', [
            'email' => $email,
            'mail_type' => $type,
            'token' => $token,
            'ip' => $ip,
            'ua' => mb_substr($ua, 0, 250),
        ]);
    }

    public static function isUnsubscribed(string $email, string $type = 'all'): bool
    {
        self::ensureTable();
        $email = self::normalizeEmail($email);
        if ($email === '') {
            return true;
        }
        $type = self::normalizeType($type);
        return (int)self::db()->fetchColumn(
            'SELECT COUNT(*) FROM mail_unsubscribes WHERE email = ? AND mail_type IN (?, ?)',
            [$email, $type, 'all']
        ) > 0;
    }

    private static function token(string $email, string $type): string
    {
        $key = (string)Config::get('app.key', 'LiteNote');
        return hash_hmac('sha256', $email . '|' . self::normalizeType($type), $key);
    }

    private static function normalizeEmail(string $email): string
    {
        $email = strtolower(trim($email));
        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
    }

    private static function normalizeType(string $type): string
    {
        $type = strtolower(trim($type));
        return preg_match('/^[a-z0-9_:-]{1,60}$/', $type) ? $type : 'all';
    }
}
