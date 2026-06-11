#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

$sites = require BASE_DIR . '/config/sites.php';

foreach ($sites as $siteId => $cfg) {
    $ttl = (int) ($cfg['message_ttl'] ?? 0);
    if ($ttl <= 0) continue;

    $repo = new MessageRepository(db());
    $repo->deleteExpired($siteId, $ttl);
    echo "[{$siteId}] Expired messages deleted.\n";
}

// Clean up expired JTIs
db()->exec("DELETE FROM used_tokens WHERE expires_at < NOW()");
echo "Expired tokens cleaned.\n";
