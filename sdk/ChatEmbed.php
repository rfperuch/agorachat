<?php

declare(strict_types=1);

/**
 * Drop this file into your host site and use it to generate
 * signed tokens and iframe tags for the chat system.
 *
 * Usage:
 *   $chat = new ChatEmbed('my_site_id', 'my_shared_secret');
 *
 *   // Basic embed — height comes from config/sites.php (default 500)
 *   echo $chat->iframeTag($user, 'https://chat.example.com/public/embed.php');
 *
 *   // Override height and theme per embed — each call is fully independent
 *   echo $chat->iframeTag($user, $url, height: 400, theme: ['primary' => '#e11d48']);
 *   echo $chat->iframeTag($user, $url, height: 600, theme: ['bg' => '#0f172a', 'primary' => '#7c3aed']);
 *
 * Height resolution (highest priority first):
 *   1. $height parameter in this call  (client override)
 *   2. widget_height in config/sites.php on the server
 *   3. Built-in default: 500px
 */
class ChatEmbed
{
    private const DEFAULT_HEIGHT = 500;

    public function __construct(
        private string $siteId,
        private string $secret
    ) {}

    /**
     * Generates the iframe HTML tag plus a postMessage listener script.
     *
     * The listener script resizes the iframe once the widget reports its
     * configured height via postMessage — this is how server-side widget_height
     * takes effect without a round-trip.
     *
     * @param array  $user    User data: user_id (required), display_name, avatar_url, is_super
     * @param string $chatUrl Full URL to embed.php on the AgoraChat server
     * @param int    $height  Override iframe height in pixels. 0 = use server default (widget_height in sites.php)
     * @param string $width   Iframe width — any CSS value (default '100%')
     * @param array  $theme   Per-embed theme overrides. Keys: primary, primary_fg,
     *                        bg, msg_bg, msg_fg, meta, border. All hex colors.
     * @param array  $attrs   Any additional HTML attributes for the <iframe> tag
     */
    public function iframeTag(
        array  $user,
        string $chatUrl,
        int    $height = 0,
        string $width  = '100%',
        array  $theme  = [],
        array  $attrs  = []
    ): string {
        $params = ['site' => $this->siteId, 'token' => $this->generateToken($user)];

        // Client height override is passed as ?h=N so the server can resolve the
        // final value and include it in the widget config for postMessage.
        if ($height > 0) {
            $params['h'] = $height;
        }

        // Theme overrides are merged server-side in buildTheme().
        foreach (['primary', 'primary_fg', 'bg', 'msg_bg', 'msg_fg', 'meta', 'border'] as $key) {
            if (isset($theme[$key])) {
                $params[$key] = $theme[$key];
            }
        }

        $src      = $chatUrl . '?' . http_build_query($params);
        $iframeId = 'agora-' . bin2hex(random_bytes(4));

        // Initial height: use the explicit value; fall back to DEFAULT_HEIGHT so the
        // page doesn't jump from 0 before the postMessage fires.
        $initialHeight = $height > 0 ? $height : self::DEFAULT_HEIGHT;

        $defaults = [
            'width'          => $width,
            'height'         => $initialHeight,
            'style'          => 'border:none;display:block',
            'allow'          => 'clipboard-write',
            'referrerpolicy' => 'strict-origin-when-cross-origin',
        ];

        $attrStr = ' id="' . htmlspecialchars($iframeId, ENT_QUOTES) . '"';
        foreach (array_merge($defaults, $attrs) as $k => $v) {
            $attrStr .= ' ' . htmlspecialchars($k, ENT_QUOTES)
                      . '="' . htmlspecialchars((string) $v, ENT_QUOTES) . '"';
        }

        $iframe = '<iframe src="' . htmlspecialchars($src, ENT_QUOTES) . '"' . $attrStr . '></iframe>';

        // Inline listener: when the widget posts agorachat:resize, resize the iframe.
        // Checks e.source to ensure the message comes from *this* iframe only.
        $id     = json_encode($iframeId);
        $script = '<script>(function(){var f=document.getElementById(' . $id . ');'
                . 'window.addEventListener("message",function(e){'
                . 'if(f&&e.data&&e.data.type==="agorachat:resize"&&e.source===f.contentWindow){'
                . 'f.style.height=e.data.height+"px";}});})();</script>';

        return $iframe . "\n" . $script;
    }

    public function generateToken(array $user): string
    {
        $header  = $this->b64url(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = $this->b64url(json_encode(array_filter([
            'site'   => $this->siteId,
            'sub'    => (string) $user['user_id'],
            'name'   => $user['display_name'] ?? 'User',
            'avatar' => $user['avatar_url'] ?? null,
            'super'  => !empty($user['is_super']),
            'iat'    => time(),
            'exp'    => time() + 600,
            'jti'    => bin2hex(random_bytes(16)),
        ], fn($v) => $v !== null)));

        $sig = $this->b64url(hash_hmac('sha256', "$header.$payload", $this->secret, true));

        return "$header.$payload.$sig";
    }

    private function b64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
