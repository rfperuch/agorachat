<?php

declare(strict_types=1);

class RateLimiter
{
    public function __construct(private PDO $db) {}

    /**
     * Returns true if the action is allowed.
     * Uses a single atomic MySQL statement — no PHP-level locks.
     *
     * ON DUPLICATE KEY UPDATE with IF:
     *   - hit_count < max  → increments → ROW_COUNT = 2 → allowed
     *   - hit_count >= max → no-op      → ROW_COUNT = 0 → blocked
     *   - new window       → inserts    → ROW_COUNT = 1 → allowed
     */
    public function allow(string $key, int $max, int $window): bool
    {
        $keyHash     = hash('sha256', $key);
        $windowStart = (int) floor(time() / $window);

        $stmt = $this->db->prepare(
            'INSERT INTO rate_limits (key_hash, window_start, hit_count)
             VALUES (?, ?, 1)
             ON DUPLICATE KEY UPDATE
               hit_count = IF(hit_count < ?, hit_count + 1, hit_count)'
        );
        $stmt->execute([$keyHash, $windowStart, $max]);
        $allowed = $stmt->rowCount() > 0; // capture before any other statement

        // Opportunistic cleanup — must not affect the allow/block decision on failure
        if (random_int(1, 100) === 1) {
            try {
                $this->db->prepare('DELETE FROM rate_limits WHERE window_start < ?')
                         ->execute([$windowStart - 1]);
            } catch (PDOException) {}
        }

        return $allowed;
    }
}
