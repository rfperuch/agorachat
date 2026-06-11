<?php

declare(strict_types=1);

class TokenValidator
{
    public function __construct(private PDO $db) {}

    public function validate(string $token, array $siteConfig): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new RuntimeException('Invalid token format');
        }

        [$headerB64, $payloadB64, $sigB64] = $parts;

        $expectedSig = $this->b64url(
            hash_hmac('sha256', "$headerB64.$payloadB64", $siteConfig['secret_key'], true)
        );

        if (!hash_equals($expectedSig, $sigB64)) {
            throw new RuntimeException('Invalid token signature');
        }

        $payload = json_decode($this->b64urlDecode($payloadB64), true);

        if (!is_array($payload)) {
            throw new RuntimeException('Invalid token payload');
        }

        $now = time();

        if (empty($payload['exp']) || $payload['exp'] < $now) {
            throw new RuntimeException('Token expired');
        }

        if (empty($payload['iat']) || $payload['iat'] > $now + 60) {
            throw new RuntimeException('Token issued in the future');
        }

        if (empty($payload['jti']) || empty($payload['sub']) || empty($payload['site'])) {
            throw new RuntimeException('Token missing required claims');
        }

        if ($payload['site'] !== $siteConfig['id']) {
            throw new RuntimeException('Token site mismatch');
        }

        $this->consumeJti($payload['jti'], $payload['exp']);

        return $payload;
    }

    private function consumeJti(string $jti, int $exp): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO used_tokens (jti, expires_at) VALUES (?, FROM_UNIXTIME(?))'
        );

        try {
            $stmt->execute([$jti, $exp]);
        } catch (PDOException $e) {
            // Duplicate key = token already used
            if ($e->getCode() === '23000') {
                throw new RuntimeException('Token already used');
            }
            throw $e;
        }

        // Opportunistic cleanup — must not affect the caller on failure
        if (random_int(1, 100) === 1) {
            try {
                $this->db->exec("DELETE FROM used_tokens WHERE expires_at < NOW()");
            } catch (PDOException) {}
        }
    }

    private function b64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function b64urlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
