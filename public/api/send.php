<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/bootstrap.php';

Session::startReadOnly();
Headers::sendApi();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}

if (!Session::isAuthenticated()) {
    json_error('Unauthorized', 401);
}

CsrfGuard::verify();

$siteId = Session::siteId();
$userId = Session::userId();
$cfg    = site_config($siteId);

// Rate limit by IP — atomic MySQL, no PHP locks
if (!(new RateLimiter(db()))->allow("send:{$siteId}:" . ($_SERVER['REMOTE_ADDR'] ?? ''), 30, 60)) {
    json_error('Too many requests', 429);
}

$body    = json_decode(file_get_contents('php://input'), true) ?? [];
$content = trim($body['content'] ?? '');

if ($content === '') {
    json_error('Message cannot be empty');
}

$maxLen = (int) ($cfg['max_message_length'] ?? 200);
if (mb_strlen($content) > $maxLen) {
    json_error("Message exceeds {$maxLen} characters");
}

// Atomic cooldown: a single UPDATE only succeeds when the cooldown has elapsed,
// eliminating the race condition of a separate SELECT + UPDATE pair.
$cooldown = (int) ($cfg['message_cooldown'] ?? 0);
if ($cooldown > 0) {
    $stmt = db()->prepare(
        'UPDATE chat_users SET last_message_at = NOW()
         WHERE id = ?
           AND (last_message_at IS NULL
                OR TIMESTAMPDIFF(SECOND, last_message_at, NOW()) >= ?)'
    );
    $stmt->execute([$userId, $cooldown]);

    if ($stmt->rowCount() === 0) {
        $row = db()->prepare('SELECT last_message_at FROM chat_users WHERE id = ?');
        $row->execute([$userId]);
        $lastAt = $row->fetchColumn();
        $wait   = $lastAt ? max(1, $cooldown - (int)(time() - strtotime($lastAt))) : 1;
        json_response(['wait' => $wait], 429);
    }
}

$msgRepo   = new MessageRepository(db());
$messageId = $msgRepo->insert($siteId, $userId, $content);

json_response(['id' => $messageId], 201);
