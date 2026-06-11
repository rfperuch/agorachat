<?php

declare(strict_types=1);

/**
 * Stores PHP sessions in MySQL instead of files.
 * All locking happens inside InnoDB — no OS file locks.
 *
 *   read()  → SELECT (brief shared lock, released after fetch)
 *   write() → INSERT … ON DUPLICATE KEY UPDATE (~1ms exclusive row lock)
 *   gc()    → DELETE WHERE expired (infrequent, called by PHP's GC)
 *
 * startReadOnly() uses session_start() + immediate session_write_close(),
 * which calls write() to update last_active, then releases the lock.
 */
class MySqlSessionHandler implements SessionHandlerInterface
{
    public function __construct(private PDO $db) {}

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string
    {
        $lifetime = (int) ini_get('session.gc_maxlifetime') ?: 1440;
        $stmt = $this->db->prepare(
            'SELECT data FROM php_sessions WHERE session_id = ? AND last_active > ?'
        );
        $stmt->execute([$id, time() - $lifetime]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row['data'] : '';
    }

    public function write(string $id, string $data): bool
    {
        $this->db->prepare(
            'INSERT INTO php_sessions (session_id, data, last_active)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE data = VALUES(data), last_active = VALUES(last_active)'
        )->execute([$id, $data, time()]);
        return true;
    }

    public function destroy(string $id): bool
    {
        $this->db->prepare('DELETE FROM php_sessions WHERE session_id = ?')
                 ->execute([$id]);
        return true;
    }

    public function gc(int $max_lifetime): int|false
    {
        $stmt = $this->db->prepare(
            'DELETE FROM php_sessions WHERE last_active < ?'
        );
        $stmt->execute([time() - $max_lifetime]);
        return $stmt->rowCount();
    }
}
