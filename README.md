# AgoraChat

[![Tests](https://github.com/rfperuch/agorachat/actions/workflows/tests.yml/badge.svg)](https://github.com/rfperuch/agorachat/actions/workflows/tests.yml)

Embeddable public chat widget for PHP applications. Each tenant gets a shared public room delivered as a signed `<iframe>` — no WebSocket infrastructure required.

## Features

- **Multi-tenant** — isolated chat rooms per site, each protected by its own shared secret
- **HMAC-JWT auth** — tokens signed by the host site; single-use JTI prevents replay attacks
- **Iframe embed** — one call to `iframeTag()`, fully sandboxed from the host page
- **Cross-site sessions** — works in Safari and Chrome with third-party cookie blocking via `X-Session-Token` header
- **Per-embed customisation** — height, width and theme colors are set independently on each `iframeTag()` call
- **Moderation** — superusers can delete individual messages or clear all messages from a user; actions require a two-click confirmation (first click arms the button, second executes)
- **i18n** — all UI strings are overrideable per tenant; ships with Portuguese defaults
- **No extra infrastructure** — MySQL only; sessions, rate limiting, and cleanup all run in the same database

## Requirements

- PHP 8.2+
- MySQL 5.7+ / MariaDB 10.3+
- Apache or Nginx

## Quick start

### 1. Create the database and run the schema

```sql
CREATE DATABASE agorachat CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

```bash
mysql -u root agorachat < db/schema.sql
```

### 2. Configure

```bash
cp config/database.example.php config/database.php
cp config/sites.example.php    config/sites.php
```

Edit `config/database.php` with your credentials, then open `config/sites.php` and add your first tenant:

```php
'my_site' => [
    'secret_key'         => bin2hex(random_bytes(32)), // run once, store the result
    'allowed_origins'    => ['https://mysite.com'],
    'message_cooldown'   => 3,
    'message_ttl'        => 86400,
    'max_message_length' => 200,
    'history_limit'      => 50,
],
```

### 3. Embed on your site

Copy `sdk/ChatEmbed.php` into your host application:

```php
$chat = new ChatEmbed('my_site', 'your_secret_key');

echo $chat->iframeTag(
    user:    ['user_id' => $user->id, 'display_name' => $user->name, 'is_super' => $user->isAdmin()],
    chatUrl: 'https://chat.example.com/public/embed.php',
);
```

## Sizing the widget

Height and width are configured per `iframeTag()` call — each embed on the page can have a different size.

**Priority (highest to lowest):**
1. `height` parameter in `iframeTag()` — explicit value
2. Built-in fallback: `500px`

The widget reports its height to the parent page via `postMessage`, so the iframe always resizes to the correct value after load.

```php
// Uses built-in default (500px)
echo $chat->iframeTag(user: $user, chatUrl: $url);

// Explicit height
echo $chat->iframeTag(user: $user, chatUrl: $url, height: 700);

// Compact sidebar widget
echo $chat->iframeTag(user: $user, chatUrl: $url, height: 400, width: '320px');
```

## Customizing the appearance

Theme colors are set per `iframeTag()` call. Keys not provided fall back to the built-in defaults:

```php
echo $chat->iframeTag(user: $user, chatUrl: $url, theme: ['primary' => '#e11d48']);
echo $chat->iframeTag(user: $user, chatUrl: $url, theme: ['bg' => '#0f172a', 'primary' => '#7c3aed']);
```

| Key | Default | Role |
|---|---|---|
| `primary` | `#4f46e5` | Accent — send button and own message bubbles |
| `primary_fg` | `#ffffff` | Text on primary-colored elements |
| `bg` | `#ffffff` | Widget background |
| `msg_bg` | `#f0f0f0` | Other users' bubble background |
| `msg_fg` | `#222222` | Other users' bubble text and input text |
| `meta` | `#888888` | Sender names, timestamps, and cooldown text |
| `border` | `#e5e7eb` | Footer and input borders |

> **Live preview** — see [Development preview](#development-preview) for setup instructions, then open `examples/test_embed.php` in a browser to pick colors interactively before passing them to `iframeTag()`.

## Translating the widget

All user-facing strings are defined in `config/sites.php` under the `strings` key. Override only the keys you need — unset keys use the Portuguese defaults.

```php
'my_site' => [
    'strings' => [
        'lang'             => 'en',
        'placeholder'      => 'Type a message...',
        'send'             => 'Send',
        'sessionExpired'   => 'Session expired. Please reload the page.',
        'cooldown'         => 'Please wait {n}s…',
        'errorSend'        => 'Failed to send message.',
        'errorModerate'    => 'Moderation action failed.',
        'errorConnection'  => 'Connection error.',
        'confirm'          => 'Confirm?',
        'deleteMsg'        => 'Delete',
        'deleteUser'       => 'Delete all from user',
    ],
],
```

The `lang` value is set as the `<html lang>` attribute of the widget iframe. The `{n}` placeholder in `cooldown` is replaced at runtime with the remaining seconds. The `confirm` value is the label shown on moderation buttons after the first click (two-step confirmation).

## Scheduled cleanup

Expired messages and consumed JWT IDs accumulate until pruned. Add a cron job to run the cleanup script periodically:

```
0 * * * *  php /path/to/agorachat/bin/cleanup.php
```

## Running tests

```bash
composer install
vendor/bin/phpunit
```

The test suite is pure unit tests — no database required.

## Development preview

`examples/test_embed.php` simulates a host site embedding the widget. Open it in a browser to switch between test users, verify authentication, and pick theme colors interactively before passing them to `iframeTag()`.

Configure the three constants at the top of the file:

```php
const SITE_ID    = 'my_site';
const SECRET_KEY = 'your-64-char-hex-key';
const CHAT_URL   = 'https://yourserver.com/agorachat/public/embed.php';
```

**`SITE_ID`** — must match a tenant key defined in `config/sites.php` exactly:

```php
// config/sites.php
return [
    'my_site' => [ // ← this value is the SITE_ID
        'secret_key' => '...',
        ...
    ],
];
```

**`SECRET_KEY`** — copy the `secret_key` value from that same tenant entry. The example generates signed JWT tokens with this secret; the AgoraChat server uses the same value to verify them. They must be identical or authentication will fail with 403.

```php
'secret_key' => '3b00d8dc9973398cca327a9be99605755746fc0ec3a6c3a61884ef76c0fa896f', // ← paste here
```

**`CHAT_URL`** — full URL to `public/embed.php` on the AgoraChat server. The path always ends with `/public/embed.php` regardless of where the project is installed:

```
https://yourserver.com/agorachat/public/embed.php
                        └─ installation path ──┘└─ always this file ─┘
```

Leave `CHAT_URL` as an empty string (`''`) to auto-detect the URL from the current request — useful when the example file is served from the same server as AgoraChat.

## Configuration reference

| Key | Default | Description |
|---|---|---|
| `secret_key` | — | 32-byte hex string shared with the host site. Generate with `bin2hex(random_bytes(32))`. |
| `allowed_origins` | — | Origins permitted to embed the widget (`scheme://host:port`). |
| `message_cooldown` | `0` | Minimum seconds between messages per user. `0` disables the limit. |
| `message_ttl` | `0` | Seconds after which messages are deleted. `0` keeps them forever. |
| `max_message_length` | `200` | Maximum characters per message. |
| `history_limit` | `50` | Number of messages loaded when the widget opens. |
| `strings` | see above | UI string overrides for translation. |

## License

[MIT](LICENSE)
