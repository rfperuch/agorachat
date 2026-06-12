<?php
/**
 * AgoraChat — host site integration example
 *
 * Simulates a host site embedding the chat widget via iframe.
 * Set the three constants below to match your config/sites.php entry.
 */

header('Content-Type: text/html; charset=utf-8');
date_default_timezone_set('America/Sao_Paulo');

require_once __DIR__ . '/../sdk/ChatEmbed.php';

// ── Host-site configuration ───────────────────────────────────────────────
const SITE_ID    = 'demo_site';
const SECRET_KEY = 'paste-your-secret-key-here';  // copy from config/sites.php
const CHAT_URL   = '';  // leave empty to auto-detect from the current host
// ─────────────────────────────────────────────────────────────────────────

$scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$origin  = $scheme . '://' . $_SERVER['HTTP_HOST'];
$base    = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/');
$chatUrl = CHAT_URL ?: $origin . $base . '/public/embed.php';

$users = [
    1 => ['display_name' => 'Alice',        'is_super' => false],
    2 => ['display_name' => 'Bob',           'is_super' => false],
    3 => ['display_name' => 'Admin (super)', 'is_super' => true],
];

$userId = (int) ($_GET['user'] ?? 1);
if (!isset($users[$userId])) $userId = 1;
$currentUser = $users[$userId];

// Theme preview: URL params override these defaults.
// Defaults match the AgoraChat theme — edit to match your config/sites.php theme.
function validHex(string $val, string $default): string {
    return preg_match('/^#[0-9a-fA-F]{3}(?:[0-9a-fA-F]{3})?$/', $val) ? $val : $default;
}
$themePrimary   = validHex($_GET['primary']    ?? '', '#4f46e5');
$themePrimaryFg = validHex($_GET['primary_fg'] ?? '', '#ffffff');
$themeBg        = validHex($_GET['bg']         ?? '', '#ffffff');
$themeMsgBg     = validHex($_GET['msg_bg']     ?? '', '#f0f0f0');
$themeMsgFg     = validHex($_GET['msg_fg']     ?? '', '#222222');
$themeMeta      = validHex($_GET['meta']       ?? '', '#888888');
$themeBorder    = validHex($_GET['border']     ?? '', '#e5e7eb');

$chat  = new ChatEmbed(SITE_ID, SECRET_KEY);

$theme = [
    'primary'    => $themePrimary,
    'primary_fg' => $themePrimaryFg,
    'bg'         => $themeBg,
    'msg_bg'     => $themeMsgBg,
    'msg_fg'     => $themeMsgFg,
    'meta'       => $themeMeta,
    'border'     => $themeBorder,
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>AgoraChat — Teste de Embed</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: system-ui, sans-serif; background: #f3f4f6; padding: 32px; }
  h1 { font-size: 18px; color: #111; margin-bottom: 4px; }
  .subtitle { font-size: 13px; color: #6b7280; margin-bottom: 24px; }
  .layout { display: flex; gap: 24px; align-items: flex-start; flex-wrap: wrap; }
  .sidebar { display: flex; flex-direction: column; gap: 16px; width: 260px; }
  .panel { background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 18px; }
  .panel h2 { font-size: 11px; font-weight: 700; color: #6b7280; margin-bottom: 12px; text-transform: uppercase; letter-spacing: .06em; }
  .user-btn {
    display: block; width: 100%; text-align: left; padding: 8px 11px;
    border: 1px solid #e5e7eb; border-radius: 7px; background: #fff;
    font-size: 14px; cursor: pointer; margin-bottom: 7px; text-decoration: none; color: #111;
  }
  .user-btn:hover { background: #f9fafb; border-color: #4f46e5; }
  .user-btn.active { background: #eef2ff; border-color: #4f46e5; color: #4f46e5; font-weight: 600; }
  .badge { font-size: 10px; background: #fef3c7; color: #92400e; border-radius: 4px; padding: 1px 5px; margin-left: 5px; font-weight: 600; }
  .hint { font-size: 11px; color: #9ca3af; margin-top: 10px; line-height: 1.5; }
  .chat-frame { border: 1px solid #e5e7eb; border-radius: 10px; overflow: hidden; }

  /* Theme picker */
  .color-row { display: flex; align-items: center; gap: 8px; margin-bottom: 10px; }
  .color-row label { font-size: 12px; color: #374151; flex: 1; }
  .color-row input[type=color] { width: 36px; height: 28px; border: 1px solid #d1d5db; border-radius: 5px; cursor: pointer; padding: 2px; }
  .color-row code { font-size: 10px; color: #6b7280; min-width: 56px; }
  .apply-btn {
    width: 100%; padding: 7px; background: #4f46e5; color: #fff;
    border: none; border-radius: 7px; font-size: 13px; cursor: pointer; margin-top: 4px;
  }
  .apply-btn:hover { background: #4338ca; }
  .reset-link { display: block; text-align: center; font-size: 11px; color: #9ca3af; margin-top: 8px; text-decoration: none; }
  .reset-link:hover { color: #6b7280; }
</style>
</head>
<body>

<h1>AgoraChat — Teste de Embed</h1>
<p class="subtitle">Simula um site host incorporando o chat via iframe com usuários autenticados.</p>

<div class="layout">
  <div class="sidebar">

    <div class="panel">
      <h2>Usuário</h2>
      <?php foreach ($users as $id => $u): ?>
        <a class="user-btn <?= $userId === $id ? 'active' : '' ?>"
           href="?<?= http_build_query(['user' => $id, 'primary' => $themePrimary, 'primary_fg' => $themePrimaryFg, 'bg' => $themeBg, 'msg_bg' => $themeMsgBg, 'msg_fg' => $themeMsgFg, 'meta' => $themeMeta, 'border' => $themeBorder]) ?>">
          <?= htmlspecialchars($u['display_name']) ?>
          <?php if ($u['is_super']): ?><span class="badge">super</span><?php endif ?>
        </a>
      <?php endforeach ?>
      <p class="hint">Trocar de usuário na mesma aba força nova autenticação.<br>Abas separadas para chat simultâneo.</p>
    </div>

    <div class="panel">
      <h2>Tema</h2>
      <form method="get" id="theme-form">
        <input type="hidden" name="user" value="<?= $userId ?>">

        <div class="color-row">
          <label>Principal</label>
          <input type="color" name="primary" value="<?= htmlspecialchars($themePrimary) ?>"
                 oninput="this.nextElementSibling.textContent=this.value">
          <code><?= htmlspecialchars($themePrimary) ?></code>
        </div>
        <div class="color-row">
          <label>Texto s/ principal</label>
          <input type="color" name="primary_fg" value="<?= htmlspecialchars($themePrimaryFg) ?>"
                 oninput="this.nextElementSibling.textContent=this.value">
          <code><?= htmlspecialchars($themePrimaryFg) ?></code>
        </div>
        <div class="color-row">
          <label>Fundo</label>
          <input type="color" name="bg" value="<?= htmlspecialchars($themeBg) ?>"
                 oninput="this.nextElementSibling.textContent=this.value">
          <code><?= htmlspecialchars($themeBg) ?></code>
        </div>
        <div class="color-row">
          <label>Bubble outros</label>
          <input type="color" name="msg_bg" value="<?= htmlspecialchars($themeMsgBg) ?>"
                 oninput="this.nextElementSibling.textContent=this.value">
          <code><?= htmlspecialchars($themeMsgBg) ?></code>
        </div>
        <div class="color-row">
          <label>Texto outros</label>
          <input type="color" name="msg_fg" value="<?= htmlspecialchars($themeMsgFg) ?>"
                 oninput="this.nextElementSibling.textContent=this.value">
          <code><?= htmlspecialchars($themeMsgFg) ?></code>
        </div>
        <div class="color-row">
          <label>Meta (nomes)</label>
          <input type="color" name="meta" value="<?= htmlspecialchars($themeMeta) ?>"
                 oninput="this.nextElementSibling.textContent=this.value">
          <code><?= htmlspecialchars($themeMeta) ?></code>
        </div>
        <div class="color-row">
          <label>Bordas</label>
          <input type="color" name="border" value="<?= htmlspecialchars($themeBorder) ?>"
                 oninput="this.nextElementSibling.textContent=this.value">
          <code><?= htmlspecialchars($themeBorder) ?></code>
        </div>

        <button type="submit" class="apply-btn">Aplicar</button>
      </form>
      <a href="?user=<?= $userId ?>" class="reset-link">Resetar para padrão</a>
      <p class="hint" style="margin-top:8px;">Para usar estas cores, passe-as via <code>theme: [...]</code> no <code>iframeTag()</code>.</p>
    </div>

  </div>

  <div>
    <div class="chat-frame">
      <?= $chat->iframeTag(
          ['user_id' => $userId] + $currentUser,
          $chatUrl,
          height: 580,
          width:  '400',
          theme:  $theme,
      ) ?>
    </div>
  </div>
</div>

</body>
</html>
