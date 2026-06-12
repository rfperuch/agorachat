<?php

declare(strict_types=1);

class UserRepository
{
    public function __construct(private PDO $db) {}

    public function upsert(
        string $siteId,
        string $externalId,
        string $displayName,
        ?string $avatarUrl,
        bool $isSuper
    ): int {
        $displayName = mb_substr($displayName, 0, 255);
        if ($avatarUrl !== null) {
            $avatarUrl = substr($avatarUrl, 0, 512);
        }

        $stmt = $this->db->prepare(
            'INSERT INTO chat_users (site_id, external_id, display_name, avatar_url, is_super)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
               display_name = ?,
               avatar_url   = ?,
               is_super     = ?,
               last_seen    = CURRENT_TIMESTAMP'
        );
        $stmt->execute([$siteId, $externalId, $displayName, $avatarUrl, (int) $isSuper,
                        $displayName, $avatarUrl, (int) $isSuper]);

        if ($stmt->rowCount() === 1) {
            return (int) $this->db->lastInsertId();
        }

        // Row was updated — fetch the id
        $row = $this->db->prepare(
            'SELECT id FROM chat_users WHERE site_id = ? AND external_id = ?'
        );
        $row->execute([$siteId, $externalId]);
        return (int) $row->fetchColumn();
    }



}
