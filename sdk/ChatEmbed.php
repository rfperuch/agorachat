<?php

declare(strict_types=1);

/**
 * Drop this file into your host site and use it to generate
 * signed tokens and iframe tags for the chat system.
 *
 * Usage:
 *   $chat = new ChatEmbed('my_site_id', 'my_shared_secret');
 *   echo $chat->iframeTag(
 *       ['user_id' => $user->id, 'display_name' => $user->name, 'is_super' => false],
 *       'https://chat.example.com/embed.php'
 *   );
 */
class ChatEmbed
{
    public function __construct(
        private string $siteId,
        private string $secret
    ) {}

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

    public function iframeTag(array $user, string $chatUrl, array $attrs = []): string
    {
        $token = $this->generateToken($user);
        $src   = $chatUrl . '?' . http_build_query([
            'site'  => $this->siteId,
            'token' => $token,
        ]);

        $defaults = [
            'width'            => '100%',
            'height'           => '500',
            'frameborder'      => '0',
            'allow'            => 'clipboard-write',
            'referrerpolicy'   => 'strict-origin-when-cross-origin',
        ];

        $merged = array_merge($defaults, $attrs);

        $attrStr = '';
        foreach ($merged as $k => $v) {
            $attrStr .= ' ' . htmlspecialchars($k, ENT_QUOTES) . '="' . htmlspecialchars((string) $v, ENT_QUOTES) . '"';
        }

        $escapedSrc = htmlspecialchars($src, ENT_QUOTES);

        return "<iframe src=\"{$escapedSrc}\"{$attrStr}></iframe>";
    }

    private function b64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
