<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/bootstrap.php';

Session::startReadOnly();
Headers::sendApi();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_error('Method not allowed', 405);
}

if (!Session::isAuthenticated()) {
    json_error('Unauthorized', 401);
}

$siteId          = Session::siteId();
$afterId         = max(0, (int) ($_GET['after_id'] ?? 0));
$afterDeletionId = max(0, (int) ($_GET['after_deletion_id'] ?? 0));

$msgRepo = new MessageRepository(db());
$cfg     = site_config($siteId);
$ttl     = (int) ($cfg['message_ttl'] ?? 0);

// Opportunistic TTL cleanup (~5% of polls)
if ($ttl > 0 && random_int(1, 20) === 1) {
    $msgRepo->deleteExpired($siteId, $ttl);
}

$messages  = $msgRepo->since($siteId, $afterId);
$deletions = $msgRepo->deletionsSince($siteId, $afterDeletionId);

$deletedIds     = array_column($deletions, 'message_id');
$lastDeletionId = !empty($deletions) ? (int) end($deletions)['deletion_id'] : $afterDeletionId;

json_response([
    'messages'         => format_messages($messages),
    'deleted_ids'      => array_map('intval', $deletedIds),
    'last_deletion_id' => $lastDeletionId,
]);
