<?php

declare(strict_types=1);

class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            self::applyCookieParams();
            session_start();
        }
    }

    /**
     * Starts session, writes it back immediately (updating last_active in MySQL),
     * then releases the lock. Use this on all read-only API endpoints.
     * With the MySQL handler the "lock" is a row lock held only during write (~1ms).
     */
    public static function startReadOnly(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            self::applyCookieParams();
            session_start();
            session_write_close(); // flush + release MySQL row lock immediately
        }
    }

    private static function applyCookieParams(): void
    {
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (($_SERVER['SERVER_PORT'] ?? 80) == 443);
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => $isHttps ? 'None' : 'Lax',
        ]);
        // Replace file-based session handler with MySQL — no OS file locks
        session_set_save_handler(new MySqlSessionHandler(db()), true);
    }

    public static function isAuthenticated(): bool
    {
        return isset($_SESSION['user_id'], $_SESSION['site_id']);
    }

    public static function create(int $userId, string $siteId, bool $isSuper, string $externalId = ''): void
    {
        $_SESSION['user_id']     = $userId;
        $_SESSION['site_id']     = $siteId;
        $_SESSION['is_super']    = $isSuper;
        $_SESSION['external_id'] = $externalId;
    }

    public static function externalId(): string
    {
        return (string) ($_SESSION['external_id'] ?? '');
    }

    public static function userId(): int
    {
        return (int) ($_SESSION['user_id'] ?? 0);
    }

    public static function siteId(): string
    {
        return (string) ($_SESSION['site_id'] ?? '');
    }

    public static function isSuper(): bool
    {
        return (bool) ($_SESSION['is_super'] ?? false);
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        session_destroy();
    }
}
