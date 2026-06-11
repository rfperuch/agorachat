'use strict';

(function () {
  const cfgEl   = document.querySelector('meta[name="chat-config"]');
  const csrfEl  = document.querySelector('meta[name="csrf-token"]');
  if (!cfgEl || !csrfEl) return;

  const cfg  = JSON.parse(cfgEl.content);
  const csrf = csrfEl.content;
  const s    = cfg.strings;

  const BASE = 'api';

  const POLL_INTERVAL = 2000; // ms between polls — server responds immediately

  let lastMessageId  = 0;
  let lastDeletionId = 0;
  let pollTimer      = null; // setTimeout handle — null means polling stopped
  let cooldownTimer  = null;

  const $messages  = document.getElementById('chat-messages');
  const $input     = document.getElementById('chat-input');
  const $send      = document.getElementById('chat-send');
  const $cooldown  = document.getElementById('chat-cooldown');

  // ── Helpers ──────────────────────────────────────────────────────────────

  function escHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function safeAvatarUrl(url) {
    try {
      const p = new URL(url).protocol;
      return (p === 'https:' || p === 'http:') ? url : null;
    } catch { return null; }
  }

  function initials(name) {
    return String(name).trim().split(/\s+/).map(w => w[0]).join('').slice(0, 2).toUpperCase();
  }

  function scrollBottom() {
    $messages.scrollTop = $messages.scrollHeight;
  }

  function isNearBottom() {
    return $messages.scrollHeight - $messages.scrollTop - $messages.clientHeight < 80;
  }

  // ── Render a single message ───────────────────────────────────────────────

  function renderMessage(msg) {
    const isOwn   = msg.user_id === cfg.userId;
    const safeAvatar = msg.avatar ? safeAvatarUrl(msg.avatar) : null;
    const avatarHtml = safeAvatar
      ? `<img src="${escHtml(safeAvatar)}" alt="">`
      : escHtml(initials(msg.sender));

    const modActions = cfg.isSuper
      ? `<div class="msg-actions">
           <button data-action="del-msg" data-id="${msg.id}">${escHtml(s.deleteMsg)}</button>
           <button data-action="del-user" data-uid="${msg.user_id}">${escHtml(s.deleteUser)}</button>
         </div>`
      : '';

    const div = document.createElement('div');
    div.className = 'msg' + (isOwn ? ' own' : '');
    div.dataset.id = msg.id;
    div.innerHTML = `
      <div class="msg-avatar">${avatarHtml}</div>
      <div class="msg-body">
        <div class="msg-name">${escHtml(msg.sender)}</div>
        <div class="msg-bubble">${escHtml(msg.content)}</div>
        ${modActions}
      </div>`;
    return div;
  }

  function appendMessages(msgs) {
    if (!msgs.length) return;
    const near = isNearBottom();
    msgs.forEach(msg => {
      if (document.querySelector(`.msg[data-id="${msg.id}"]`)) return;
      $messages.appendChild(renderMessage(msg));
      lastMessageId = Math.max(lastMessageId, msg.id);
    });
    if (near) scrollBottom();
  }

  function removeMessages(ids) {
    ids.forEach(id => {
      document.querySelector(`.msg[data-id="${id}"]`)?.remove();
    });
  }

  // ── API calls ─────────────────────────────────────────────────────────────

  async function loadHistory() {
    try {
      const res = await fetch(`${BASE}/history.php`, {
        credentials: 'include',
        headers: { 'X-Session-Token': cfg.sessionToken },
      });

      if (res.status === 401) {
        $messages.innerHTML = `<p style="padding:12px;color:#888">${escHtml(s.sessionExpired)}</p>`;
        return false; // signal to caller: don't start polling
      }

      const data = await res.json();
      if (data.messages) {
        appendMessages(data.messages);
        scrollBottom();
      }
    } catch (e) {
      console.error('History load failed', e);
    }
    return true;
  }

  async function poll() {
    try {
      const url = `${BASE}/poll.php?after_id=${lastMessageId}&after_deletion_id=${lastDeletionId}`;
      const res = await fetch(url, {
        credentials: 'include',
        headers: { 'X-Session-Token': cfg.sessionToken },
      });

      if (res.status === 401) {
        $messages.innerHTML = `<p style="padding:12px;color:#888">${escHtml(s.sessionExpired)}</p>`;
        return; // pollTimer not rescheduled — loop stops
      }

      const data = await res.json();

      if (data.messages?.length)    appendMessages(data.messages);
      if (data.deleted_ids?.length) removeMessages(data.deleted_ids);
      if (data.last_deletion_id)    lastDeletionId = data.last_deletion_id;
    } catch (e) {
      // network error — next tick still scheduled below
    }

    // Schedule next poll regardless of success/error (except 401 above)
    pollTimer = setTimeout(poll, POLL_INTERVAL);
  }

  function startPolling() {
    if (pollTimer !== null) return;
    pollTimer = setTimeout(poll, POLL_INTERVAL);
  }

  async function sendMessage() {
    const content = $input.value.trim();
    if (!content) return;

    $send.disabled = true;
    try {
      const res  = await fetch(`${BASE}/send.php`, {
        method:      'POST',
        credentials: 'include',
        headers:     { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf, 'X-Session-Token': cfg.sessionToken },
        body:        JSON.stringify({ content }),
      });
      const data = await res.json();

      if (res.status === 403 && data.error?.includes('CSRF')) { // stale session
        window.location.reload(); // stale session — reload to get fresh CSRF token
        return;
      }

      if (res.status === 429 && data.wait) {
        startCooldown(data.wait);
        return;
      }

      if (res.ok) {
        $input.value = '';
      } else {
        alert(data.error ?? s.errorSend);
      }
    } catch (e) {
      alert(s.errorConnection);
    } finally {
      $send.disabled = false;
    }
  }

  // ── Moderation ────────────────────────────────────────────────────────────

  async function moderate(body) {
    try {
      const res  = await fetch(`${BASE}/moderate.php`, {
        method:      'DELETE',
        credentials: 'include',
        headers:     { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf, 'X-Session-Token': cfg.sessionToken },
        body:        JSON.stringify(body),
      });
      const data = await res.json();
      if (!res.ok) alert(data.error ?? s.errorModerate);
    } catch (e) {
      alert(s.errorConnection);
    }
  }

  // ── Cooldown UI ───────────────────────────────────────────────────────────

  function startCooldown(seconds) {
    $send.disabled = true;
    clearInterval(cooldownTimer);
    let remaining = seconds;
    $cooldown.textContent = s.cooldown.replace('{n}', remaining);
    $cooldown.hidden = false;
    cooldownTimer = setInterval(() => {
      remaining--;
      if (remaining <= 0) {
        clearInterval(cooldownTimer);
        $cooldown.hidden = true;
        $send.disabled   = false;
      } else {
        $cooldown.textContent = s.cooldown.replace('{n}', remaining);
      }
    }, 1000);
  }

  // ── Events ────────────────────────────────────────────────────────────────

  $send.addEventListener('click', sendMessage);

  $input.addEventListener('keydown', e => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      sendMessage();
    }
  });

  $messages.addEventListener('click', e => {
    const btn = e.target.closest('[data-action]');
    if (!btn) return;
    const action = btn.dataset.action;
    if (action === 'del-msg') {
      if (confirm(s.confirmDelete)) {
        moderate({ message_id: parseInt(btn.dataset.id, 10) });
      }
    } else if (action === 'del-user') {
      if (confirm(s.confirmDeleteAll)) {
        moderate({ target_user_id: parseInt(btn.dataset.uid, 10) });
      }
    }
  });

  // ── Init ──────────────────────────────────────────────────────────────────

  loadHistory().then(ok => { if (ok) startPolling(); });
})();
