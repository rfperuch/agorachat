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

$siteId = Session::siteId();
$cfg    = site_config($siteId);
$limit  = (int) ($cfg['history_limit'] ?? 50);

$messages = (new MessageRepository(db()))->history($siteId, $limit);

json_response(['messages' => format_messages($messages)]);
