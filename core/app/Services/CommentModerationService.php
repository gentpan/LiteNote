<?php
declare(strict_types=1);

namespace App\Services;

use App\Enums\CommentStatus;
use App\Models\Comment;

final class CommentModerationService
{
    private static bool $schemaChecked = false;

    public static function ensureSchema(): void
    {
        if (self::$schemaChecked) {
            return;
        }
        self::$schemaChecked = true;

        $db = Comment::db();
        $db->query(<<<SQL
        CREATE TABLE IF NOT EXISTS comment_spam_identities (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            type VARCHAR(20) NOT NULL,
            value VARCHAR(255) NOT NULL,
            source_comment_id INTEGER DEFAULT 0,
            hits INTEGER DEFAULT 0,
            last_seen_at DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(type, value)
        )
        SQL);
        $db->query('CREATE INDEX IF NOT EXISTS idx_comment_spam_identity ON comment_spam_identities(type, value)');
    }

    public static function markFromComment(Comment $comment): void
    {
        self::ensureSchema();
        foreach (self::identityValues((string)($comment->email ?? ''), (string)($comment->ip ?? '')) as $type => $value) {
            self::upsert($type, $value, (int)($comment->id ?? 0));
        }
    }

    public static function removeFromComment(Comment $comment): void
    {
        self::ensureSchema();
        $db = Comment::db();
        foreach (self::identityValues((string)($comment->email ?? ''), (string)($comment->ip ?? '')) as $type => $value) {
            $db->delete('comment_spam_identities', 'type = ? AND value = ?', [$type, $value]);
        }
    }

    public static function statusFor(string $email, string $ip, string $defaultStatus): string
    {
        self::ensureSchema();
        $matched = false;
        foreach (self::identityValues($email, $ip) as $type => $value) {
            if (self::exists($type, $value)) {
                self::recordHit($type, $value);
                $matched = true;
            }
        }

        return $matched ? CommentStatus::Spam->value : $defaultStatus;
    }

    private static function upsert(string $type, string $value, int $sourceCommentId): void
    {
        $db = Comment::db();
        $now = date('Y-m-d H:i:s');
        try {
            $db->insert('comment_spam_identities', [
                'type' => $type,
                'value' => $value,
                'source_comment_id' => $sourceCommentId,
                'hits' => 0,
                'last_seen_at' => $now,
                'created_at' => $now,
            ]);
        } catch (\Throwable) {
            $db->query(
                'UPDATE comment_spam_identities SET source_comment_id = ?, last_seen_at = ? WHERE type = ? AND value = ?',
                [$sourceCommentId, $now, $type, $value]
            );
        }
    }

    private static function exists(string $type, string $value): bool
    {
        return (bool)Comment::db()->fetchOne(
            'SELECT id FROM comment_spam_identities WHERE type = ? AND value = ? LIMIT 1',
            [$type, $value]
        );
    }

    private static function recordHit(string $type, string $value): void
    {
        Comment::db()->query(
            'UPDATE comment_spam_identities SET hits = COALESCE(hits, 0) + 1, last_seen_at = ? WHERE type = ? AND value = ?',
            [date('Y-m-d H:i:s'), $type, $value]
        );
    }

    /**
     * @return array{email?: string, ip?: string}
     */
    private static function identityValues(string $email, string $ip): array
    {
        $values = [];
        $email = self::normalizeEmail($email);
        $ip = self::normalizeIp($ip);
        if ($email !== '') {
            $values['email'] = $email;
        }
        if ($ip !== '') {
            $values['ip'] = $ip;
        }
        return $values;
    }

    private static function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    private static function normalizeIp(string $ip): string
    {
        return trim($ip);
    }
}
