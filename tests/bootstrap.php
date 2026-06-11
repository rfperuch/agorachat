<?php

declare(strict_types=1);

define('BASE_DIR', dirname(__DIR__));

// Stub bootstrap helpers so class files can be required without the full app bootstrap.
// CsrfGuard::verify() calls json_error() on failure — we turn that into a RuntimeException
// so tests can catch it with $this->expectException().
if (!function_exists('json_error')) {
    function json_error(string $message, int $status = 400): never
    {
        throw new \RuntimeException("HTTP {$status}: {$message}");
    }
}

require_once BASE_DIR . '/src/Security/Headers.php';
require_once BASE_DIR . '/src/Security/CsrfGuard.php';
require_once BASE_DIR . '/src/Security/RateLimiter.php';
require_once BASE_DIR . '/src/Auth/TokenValidator.php';
require_once BASE_DIR . '/src/Chat/MessageRepository.php';
require_once BASE_DIR . '/src/Chat/UserRepository.php';
