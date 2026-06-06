<?php
declare(strict_types=1);

namespace App\Models;

final class MailLog extends Model
{
    protected static string $table = 'mail_logs';
    protected static array $sortable = ['id', 'created_at', 'status', 'mail_type'];
    private static bool $schemaReady = false;

    public static function ensureTable(): void
    {
        if (self::$schemaReady) {
            return;
        }
        self::$schemaReady = true;
        self::db()->query(<<<SQL
        CREATE TABLE IF NOT EXISTS mail_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            provider VARCHAR(40),
            mail_type VARCHAR(60),
            recipient VARCHAR(160),
            subject VARCHAR(255),
            status VARCHAR(24),
            error TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
        SQL);
        try {
            self::db()->query('CREATE INDEX IF NOT EXISTS idx_mail_logs_created ON mail_logs(created_at)');
            self::db()->query('CREATE INDEX IF NOT EXISTS idx_mail_logs_status ON mail_logs(status)');
        } catch (\Throwable) {
            // ignore
        }
    }

    public static function record(string $provider, string $type, string $recipient, string $subject, string $status, string $error = ''): void
    {
        self::ensureTable();
        self::db()->insert('mail_logs', [
            'provider' => $provider,
            'mail_type' => $type,
            'recipient' => $recipient,
            'subject' => mb_substr($subject, 0, 250),
            'status' => $status,
            'error' => $error,
        ]);
    }

    public static function recent(int $limit = 30): array
    {
        self::ensureTable();
        $limit = max(1, min(100, $limit));
        return self::query("SELECT * FROM mail_logs ORDER BY id DESC LIMIT {$limit}");
    }

    public static function stats(): array
    {
        self::ensureTable();
        $rows = self::db()->fetchAll('SELECT status, COUNT(*) AS total FROM mail_logs GROUP BY status');
        $result = ['sent' => 0, 'failed' => 0, 'skipped' => 0];
        foreach ($rows as $row) {
            $result[(string)$row['status']] = (int)$row['total'];
        }
        return $result;
    }
}
