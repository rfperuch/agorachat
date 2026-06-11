<?php
/**
 * AgoraChat — Invision Power Board (IPS Community Suite 4.x / 5.x) integration
 *
 * Renders the chat iframe for logged-in forum members.
 * Guests see nothing — add a login prompt below if desired.
 *
 * Setup:
 *  1. Copy sdk/ChatEmbed.php to your IPS server and update AGORACHAT_SDK below.
 *  2. Fill in AGORACHAT_SITE_ID, AGORACHAT_SECRET_KEY and AGORACHAT_URL.
 *  3. Add your forum URL to allowed_origins in AgoraChat's config/sites.php:
 *       'allowed_origins' => ['https://yourforum.com'],
 *  4. Include this file in an IPS template or custom PHP widget block:
 *       {php}require '/path/to/ipb_embed.php';{/php}
 */

// ── AgoraChat configuration ───────────────────────────────────────────────
define('AGORACHAT_SDK',        '/path/to/agorachat/sdk/ChatEmbed.php');
define('AGORACHAT_SITE_ID',    'my_site');
define('AGORACHAT_SECRET_KEY', 'your-secret-key');        // copy from config/sites.php
define('AGORACHAT_URL',        'https://yourserver.com/agorachat/public/embed.php');
// ─────────────────────────────────────────────────────────────────────────

require_once AGORACHAT_SDK;

$member = \IPS\Member::loggedIn();

if ( $member->member_id <= 0 ) {
    return; // guest — render nothing
}

// $member->photo returns an \IPS\Http\Url object — cast to string for the URL.
// It always resolves to something (letter avatar, gravatar, or uploaded photo).
$avatarUrl = (string) $member->photo ?: null;

// Superuser flag: IPB admins become AgoraChat moderators by default.
// To grant moderation to a specific group instead, replace with:
//   $isSuper = \IPS\Member::loggedIn()->inGroup( YOUR_GROUP_ID );
$isSuper = $member->isAdmin();

$chat = new ChatEmbed( AGORACHAT_SITE_ID, AGORACHAT_SECRET_KEY );

echo $chat->iframeTag(
    [
        'user_id'      => $member->member_id,
        'display_name' => $member->name,
        'avatar_url'   => $avatarUrl,
        'is_super'     => $isSuper,
    ],
    AGORACHAT_URL
);
