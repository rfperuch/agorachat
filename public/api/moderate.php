<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/bootstrap.php';

Session::startReadOnly();
Headers::sendApi();

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    json_error('Method not allowed', 405);
}

if (!Session::isAuthenticated()) {
    json_error('Unauthorized', 401);
}

if (!Session::isSuper()) {
    json_error('Forbidden', 403);
}

CsrfGuard::verify();

$siteId = Session::siteId();
$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$repo   = new MessageRepository(db());

if (isset($body['message_id'])) {
    json_response(['deleted' => $repo->deleteById($siteId, (int) $body['message_id'])]);
}

if (isset($body['target_user_id'])) {
    json_response(['deleted' => $repo->deleteByUser($siteId, (int) $body['target_user_id'])]);
}

json_error('Provide message_id or target_user_id');
