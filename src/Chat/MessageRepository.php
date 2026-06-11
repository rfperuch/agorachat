<?php

declare(strict_types=1);

class MessageRepository
{
    public function __construct(private PDO $db) {}

    public function insert(string $siteId, int $senderId, string $content): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO messages (site_id, sender_id, content) VALUES (?, ?, ?)'
        );
        $stmt->execute([$siteId, $senderId, $content]);
        return (int) $this->db->lastInsertId();
    }

    /** Returns last $limit messages for initial load (oldest-first). */
    public function history(string $siteId, int $limit): array
    {
        $stmt = $this->db->prepare(
            'SELECT m.id, m.content, m.created_at,
                    u.id AS user_id, u.display_name, u.avatar_url
             FROM (
               SELECT * FROM messages WHERE site_id = ?
               ORDER BY id DESC LIMIT ?
             ) m
             JOIN chat_users u ON u.id = m.sender_id
             ORDER BY m.id ASC'
        );
        $stmt->execute([$siteId, $limit]);
        return $stmt->fetchAll();
    }

    /** Returns messages with id > $afterId for long poll. */
    public function since(string $siteId, int $afterId): array
    {
        $stmt = $this->db->prepare(
            'SELECT m.id, m.content, m.created_at,
                    u.id AS user_id, u.display_name, u.avatar_url
             FROM messages m
             JOIN chat_users u ON u.id = m.sender_id
             WHERE m.site_id = ? AND m.id > ?
             ORDER BY m.id ASC'
        );
        $stmt->execute([$siteId, $afterId]);
        return $stmt->fetchAll();
    }

    public function deleteById(string $siteId, int $messageId): int
    {
        $stmt = $this->db->prepare(
            'DELETE FROM messages WHERE id = ? AND site_id = ?'
        );
        $stmt->execute([$messageId, $siteId]);
        $count = $stmt->rowCount();
        if ($count > 0) {
            $this->recordDeletion($siteId, $messageId);
        }
        return $count;
    }

    public function deleteByUser(string $siteId, int $userId): int
    {
        $this->db->beginTransaction();
        try {
            $sel = $this->db->prepare(
                'SELECT id FROM messages WHERE sender_id = ? AND site_id = ? FOR UPDATE'
            );
            $sel->execute([$userId, $siteId]);
            $ids = $sel->fetchAll(PDO::FETCH_COLUMN);

            if (empty($ids)) {
                $this->db->commit();
                return 0;
            }

            $this->db->prepare(
                'DELETE FROM messages WHERE sender_id = ? AND site_id = ?'
            )->execute([$userId, $siteId]);

            foreach ($ids as $id) {
                $this->recordDeletion($siteId, (int) $id);
            }

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return count($ids);
    }

    public function deleteExpired(string $siteId, int $ttlSeconds): void
    {
        try {
            $this->db->prepare(
                'DELETE FROM messages WHERE site_id = ? AND created_at < NOW() - INTERVAL ? SECOND'
            )->execute([$siteId, $ttlSeconds]);

            $this->db->prepare(
                'DELETE FROM message_deletions
                 WHERE site_id = ? AND deleted_at < NOW() - INTERVAL ? SECOND'
            )->execute([$siteId, $ttlSeconds * 2]);
        } catch (PDOException) {
            // Cleanup is best-effort; never fail the caller (poll.php)
        }
    }

    public function deletionsSince(string $siteId, int $afterDeletionId): array
    {
        $stmt = $this->db->prepare(
            'SELECT id AS deletion_id, message_id
             FROM message_deletions
             WHERE site_id = ? AND id > ?
             ORDER BY id ASC'
        );
        $stmt->execute([$siteId, $afterDeletionId]);
        return $stmt->fetchAll();
    }

    private function recordDeletion(string $siteId, int $messageId): void
    {
        $this->db->prepare(
            'INSERT INTO message_deletions (site_id, message_id) VALUES (?, ?)'
        )->execute([$siteId, $messageId]);
    }
}
