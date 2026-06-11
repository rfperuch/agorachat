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
            self::resumeFromHeader();
            session_start();
            session_write_close();
        }
    }

    /**
     * Allows cross-site iframes to resume a session via the X-Session-Token header
     * instead of a cookie. Browsers block third-party cookies in cross-site iframes
     * (Safari ITP, Chrome Privacy Sandbox), so the session ID is embedded in the
     * page HTML by embed.php and sent as a header by chat.js on every API call.
     */
    private static function resumeFromHeader(): void
    {
        $token = $_SERVER['HTTP_X_SESSION_TOKEN'] ?? '';
        if ($token !== '' && preg_match('/^[a-zA-Z0-9\-,]{20,128}$/', $token)) {
            session_id($token);
        }
    }

    private static function applyCookieParams(): void
    {
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (($_SERVER['SERVER_PORT'] ?? 80) == 443)
                || (strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

        // Distinct name avoids collision with host-site PHPSESSID (IPB, WordPress…)
        // even when AgoraChat and the host site share the same domain.
        session_name('agorachat_session');

        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => $isHttps ? 'None' : 'Lax',
        ]);

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
