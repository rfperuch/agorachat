<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

Session::start();

$siteId = trim($_GET['site'] ?? '');
$token  = trim($_GET['token'] ?? '');

$siteConfig = $siteId ? site_config($siteId) : null;

// If session is active for this site, only re-check origin (not token).
// Exception: if a token for a *different* user arrives, re-authenticate below.
if (Session::isAuthenticated() && Session::siteId() === $siteId && $siteConfig !== null) {
    $tokenSub = '';
    if ($token) {
        $parts = explode('.', $token);
        if (count($parts) === 3) {
            $peeked   = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
            $tokenSub = (string) ($peeked['sub'] ?? '');
        }
    }

    if ($tokenSub === '' || $tokenSub === Session::externalId()) {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        if ($origin && !Headers::originAllowed($origin, $siteConfig['allowed_origins'])) {
            Headers::send($siteConfig['allowed_origins']);
            http_response_code(403);
            echo 'Origin not allowed.';
            exit;
        }
        Headers::send($siteConfig['allowed_origins']);
        $csrfToken    = CsrfGuard::token();
        $sessionToken = session_id();
        session_write_close();
        renderChat($siteId, $siteConfig, $csrfToken, $sessionToken);
        exit;
    }

    // Different user in token — re-authenticate
    Session::destroy();
    Session::start();
}

// --- Fresh authentication required ---

if (!$siteConfig) {
    http_response_code(403);
    echo 'Unknown site.';
    exit;
}

$origin = $_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? '';
if (!Headers::originAllowed($origin, $siteConfig['allowed_origins'])) {
    app_log('warning', 'Blocked embed: origin not allowed', ['origin' => $origin, 'site' => $siteId]);
    http_response_code(403);
    echo 'Origin not allowed.';
    exit;
}

Headers::send($siteConfig['allowed_origins']);

if (!$token) {
    http_response_code(403);
    echo 'Authentication required.';
    exit;
}

try {
    $validator = new TokenValidator(db());
    $payload   = $validator->validate($token, $siteConfig);
} catch (RuntimeException $e) {
    app_log('warning', 'Token validation failed', ['reason' => $e->getMessage(), 'site' => $siteId]);
    http_response_code(403);
    echo 'Invalid or expired token.';
    exit;
}

$userRepo = new UserRepository(db());
$userId   = $userRepo->upsert(
    $siteId,
    $payload['sub'],
    $payload['name'] ?? 'User',
    $payload['avatar'] ?? null,
    (bool) ($payload['super'] ?? false)
);

Session::create($userId, $siteId, (bool) ($payload['super'] ?? false), $payload['sub']);
$csrfToken    = CsrfGuard::token();
$sessionToken = session_id();
session_write_close();

renderChat($siteId, $siteConfig, $csrfToken, $sessionToken);

// ---------------------------------------------------------------------------

function buildStrings(array $cfg): array
{
    $defaults = [
        'lang'             => 'pt-BR',
        'placeholder'      => 'Digite uma mensagem...',
        'send'             => 'Enviar',
        'sessionExpired'   => 'Sessão expirada. Recarregue a página.',
        'cooldown'         => 'Aguarde {n}s…',
        'errorSend'        => 'Erro ao enviar mensagem.',
        'errorModerate'    => 'Erro ao moderar.',
        'errorConnection'  => 'Falha de conexão.',
        'confirm'          => 'Confirmar?',
        'deleteMsg'        => 'Apagar',
        'deleteUser'       => 'Apagar tudo do usuário',
    ];
    return array_merge($defaults, $cfg['strings'] ?? []);
}

function buildTheme(): array
{
    $defaults = [
        'primary'    => '#4f46e5',
        'primary_fg' => '#ffffff',
        'bg'         => '#ffffff',
        'msg_bg'     => '#f0f0f0',
        'msg_fg'     => '#222222',
        'meta'       => '#888888',
        'border'     => '#e5e7eb',
    ];
    $out = [];
    foreach ($defaults as $key => $default) {
        $val = $_GET[$key] ?? $default;
        $out[$key] = preg_match('/^#[0-9a-fA-F]{3}(?:[0-9a-fA-F]{3})?$/', (string) $val)
            ? $val
            : $default;
    }
    return $out;
}

function renderChat(string $siteId, array $cfg, string $csrfToken, string $sessionToken): void
{
    $historyLimit = (int) ($cfg['history_limit'] ?? 50);
    $cooldown     = (int) ($cfg['message_cooldown'] ?? 0);
    $maxLen       = (int) ($cfg['max_message_length'] ?? 200);
    $theme        = buildTheme();
    $strings      = buildStrings($cfg);
    $lang         = htmlspecialchars($strings['lang'], ENT_QUOTES);
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
<meta name="chat-config" content="<?= htmlspecialchars(json_encode([
    'siteId'       => $siteId,
    'historyLimit' => $historyLimit,
    'cooldown'     => $cooldown,
    'maxLen'       => $maxLen,
    'isSuper'      => Session::isSuper(),
    'userId'       => Session::userId(),
    'sessionToken' => $sessionToken,
    'strings'      => $strings,
]), ENT_QUOTES) ?>">
<title>AgoraChat</title>
<link rel="stylesheet" href="assets/chat.css?v=<?= filemtime(dirname(__DIR__) . '/public/assets/chat.css') ?>">
<style>:root{--primary:<?= $theme['primary'] ?>;--primary-fg:<?= $theme['primary_fg'] ?>;--bg:<?= $theme['bg'] ?>;--msg-bg:<?= $theme['msg_bg'] ?>;--msg-fg:<?= $theme['msg_fg'] ?>;--meta:<?= $theme['meta'] ?>;--border:<?= $theme['border'] ?>;}</style>
</head>
<body>
<div id="chat">
  <div id="chat-messages" aria-live="polite"></div>
  <div id="chat-footer">
    <input id="chat-input" type="text" placeholder="<?= htmlspecialchars($strings['placeholder'], ENT_QUOTES) ?>" maxlength="<?= $maxLen ?>" autocomplete="off">
    <button id="chat-send"><?= htmlspecialchars($strings['send'], ENT_QUOTES) ?></button>
    <div id="chat-cooldown" hidden></div>
  </div>
</div>
<script src="assets/chat.js?v=<?= filemtime(dirname(__DIR__) . '/public/assets/chat.js') ?>"></script>
</body>
</html>
<?php
}
