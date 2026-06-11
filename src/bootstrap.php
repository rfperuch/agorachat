<?php

declare(strict_types=1);

define('BASE_DIR', dirname(__DIR__));

require_once BASE_DIR . '/src/Security/Headers.php';
require_once BASE_DIR . '/src/Security/RateLimiter.php';
require_once BASE_DIR . '/src/Security/CsrfGuard.php';
require_once BASE_DIR . '/src/Auth/TokenValidator.php';
require_once BASE_DIR . '/src/Auth/MySqlSessionHandler.php';
require_once BASE_DIR . '/src/Auth/Session.php';
require_once BASE_DIR . '/src/Chat/UserRepository.php';
require_once BASE_DIR . '/src/Chat/MessageRepository.php';

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $cfg = require BASE_DIR . '/config/database.php';
        $pdo = new PDO($cfg['dsn'], $cfg['username'], $cfg['password'], $cfg['options']);
    }
    return $pdo;
}

function site_config(string $siteId): ?array
{
    static $sites = null;
    if ($sites === null) {
        $sites = require BASE_DIR . '/config/sites.php';
    }
    if (!isset($sites[$siteId])) return null;
    return ['id' => $siteId] + $sites[$siteId];
}

function app_log(string $level, string $message, array $context = []): void
{
    $line = sprintf(
        "[%s] %s: %s %s\n",
        date('Y-m-d H:i:s'),
        strtoupper($level),
        $message,
        $context ? json_encode($context) : ''
    );
    error_log($line, 3, BASE_DIR . '/logs/error.log');
}

function json_response(mixed $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

function json_error(string $message, int $status = 400): never
{
    json_response(['error' => $message], $status);
}

function format_messages(array $rows): array
{
    return array_map(fn($r) => [
        'id'      => (int) $r['id'],
        'content' => $r['content'],
        'ts'      => (int) strtotime($r['created_at']),
        'user_id' => (int) $r['user_id'],
        'sender'  => $r['display_name'],
        'avatar'  => $r['avatar_url'],
    ], $rows);
}
