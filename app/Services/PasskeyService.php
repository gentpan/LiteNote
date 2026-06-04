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
    }

    public function hasCredential(int $userId = 1): bool
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM webauthn_credentials WHERE user_id = ?");
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function saveCredential(array $data): bool
    {
        $stmt = $this->db->prepare("
            INSERT INTO webauthn_credentials 
            (user_id, credential_id, public_key, counter, device_name, created_at) 
            VALUES (?, ?, ?, ?, ?, datetime('now'))
        ");
        
        return $stmt->execute([
            $data['user_id'] ?? 1,
            $data['credential_id'],
            $data['public_key'],
            $data['counter'] ?? 0,
            $data['device_name'] ?? '未知设备'
        ]);
    }

    public function getCredentialById(string $credentialId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM webauthn_credentials WHERE credential_id = ?");
        $stmt->execute([$credentialId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public function updateCounter(string $credentialId, int $newCounter): bool
    {
        $stmt = $this->db->prepare("UPDATE webauthn_credentials SET counter = ?, last_used_at = datetime('now') WHERE credential_id = ?");
        return $stmt->execute([$newCounter, $credentialId]);
    }
}