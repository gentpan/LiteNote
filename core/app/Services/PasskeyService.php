<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

class PasskeyService
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->ensureTable();
    }

    private function ensureTable(): void
    {
        $this->db->query(<<<SQL
        CREATE TABLE IF NOT EXISTS webauthn_credentials (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL DEFAULT 1,
            credential_id TEXT NOT NULL UNIQUE,
            public_key TEXT NOT NULL,
            counter INTEGER DEFAULT 0,
            device_name VARCHAR(100),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            last_used_at DATETIME
        )
        SQL);

        $this->db->query('CREATE INDEX IF NOT EXISTS idx_webauthn_user ON webauthn_credentials(user_id)');
    }

    public function hasCredential(int $userId = 1): bool
    {
        return (int) $this->db->fetchColumn(
            'SELECT COUNT(*) FROM webauthn_credentials WHERE user_id = ?',
            [$userId]
        ) > 0;
    }

    public function hasAnyCredential(): bool
    {
        return (int) $this->db->fetchColumn('SELECT COUNT(*) FROM webauthn_credentials') > 0;
    }

    public function allCredentials(): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM webauthn_credentials ORDER BY last_used_at DESC, created_at DESC'
        );
    }

    public function credentialsForUser(int $userId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM webauthn_credentials WHERE user_id = ? ORDER BY last_used_at DESC, created_at DESC',
            [$userId]
        );
    }

    public function saveCredential(array $data): bool
    {
        $credentialId = (string) $data['credential_id'];
        $payload = [
            'user_id' => $data['user_id'] ?? 1,
            'public_key' => $data['public_key'],
            'counter' => $data['counter'] ?? 0,
            'device_name' => $data['device_name'] ?? '',
        ];

        if ($this->getCredentialById($credentialId)) {
            $this->db->update(
                'webauthn_credentials',
                $payload,
                'credential_id = :credential_id',
                ['credential_id' => $credentialId]
            );
            return true;
        }

        $payload['credential_id'] = $credentialId;
        $payload['created_at'] = date('Y-m-d H:i:s');
        $this->db->insert('webauthn_credentials', $payload);
        return true;
    }

    public function getCredentialById(string $credentialId): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM webauthn_credentials WHERE credential_id = ?',
            [$credentialId]
        );
    }

    public function updateCounter(string $credentialId, int $newCounter): bool
    {
        $this->db->update(
            'webauthn_credentials',
            [
                'counter' => $newCounter,
                'last_used_at' => date('Y-m-d H:i:s'),
            ],
            'credential_id = :credential_id',
            ['credential_id' => $credentialId]
        );
        return true;
    }

    public function deleteCredential(int $id, int $userId): bool
    {
        if ($id <= 0 || $userId <= 0) {
            return false;
        }

        $row = $this->db->fetchOne(
            'SELECT id FROM webauthn_credentials WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );
        if (!$row) {
            return false;
        }

        $this->db->delete('webauthn_credentials', 'id = :id AND user_id = :user_id', [
            'id' => $id,
            'user_id' => $userId,
        ]);
        return true;
    }
}
