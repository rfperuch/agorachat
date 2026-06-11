<?php

declare(strict_types=1);

class Headers
{
    public static function send(array $allowedOrigins): void
    {
        $frameAncestors = "'self'";
        foreach ($allowedOrigins as $origin) {
            $frameAncestors .= ' ' . $origin;
        }

        header('Content-Type: text/html; charset=utf-8');
        header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; frame-ancestors {$frameAncestors}");
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Cache-Control: no-store');
    }

    public static function sendApi(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store');
    }

    public static function originAllowed(string $origin, array $allowed): bool
    {
        if (empty($origin)) {
            return false;
        }

        // Normalize: scheme + host + port (if non-standard)
        $scheme = parse_url($origin, PHP_URL_SCHEME) ?? '';
        $host   = parse_url($origin, PHP_URL_HOST) ?? '';
        $port   = parse_url($origin, PHP_URL_PORT);
        $check  = strtolower($scheme . '://' . $host . ($port ? ':' . $port : ''));

        foreach ($allowed as $a) {
            $a = rtrim(strtolower($a), '/');
            if ($check === $a) {
                return true;
            }
        }

        return false;
    }
}
