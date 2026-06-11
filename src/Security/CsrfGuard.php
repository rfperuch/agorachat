<?php

declare(strict_types=1);

class CsrfGuard
{
    private const SESSION_KEY = '_csrf';

    public static function token(): string
    {
        if (empty($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::SESSION_KEY];
    }

    public static function verify(): void
    {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN']
              ?? ($_POST['_csrf'] ?? '');

        if (empty($token) || !hash_equals(self::token(), $token)) {
            json_error('Invalid CSRF token', 403);
        }
    }
}
