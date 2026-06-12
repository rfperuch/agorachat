<?php

declare(strict_types=1);

namespace Tests\Unit;

use PDO;
use PDOException;
use PDOStatement;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use TokenValidator;

final class TokenValidatorTest extends TestCase
{
    private const SECRET  = 'test-secret-key-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx';
    private const SITE_ID = 'test_site';

    private PDO&MockObject $pdo;
    private PDOStatement&MockObject $stmt;
    private TokenValidator $validator;

    protected function setUp(): void
    {
        $this->stmt      = $this->createMock(PDOStatement::class);
        $this->pdo       = $this->createMock(PDO::class);
        $this->pdo->method('prepare')->willReturn($this->stmt);
        $this->validator = new TokenValidator($this->pdo);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function b64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function buildToken(array $payload, string $secret = self::SECRET): string
    {
        $h = $this->b64url(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $p = $this->b64url(json_encode($payload));
        $s = $this->b64url(hash_hmac('sha256', "$h.$p", $secret, true));
        return "$h.$p.$s";
    }

    private function siteConfig(): array
    {
        return ['id' => self::SITE_ID, 'secret_key' => self::SECRET];
    }

    /** Returns a payload that passes all validations. */
    private function validPayload(array $overrides = []): array
    {
        return $overrides + [
            'site' => self::SITE_ID,
            'sub'  => 'user-123',
            'iat'  => time(),
            'exp'  => time() + 600,
            'jti'  => bin2hex(random_bytes(8)),
        ];
    }

    // ── Happy path ────────────────────────────────────────────────────────────

    public function testValidTokenReturnsPayload(): void
    {
        $this->stmt->method('execute')->willReturn(true);

        $payload = $this->validator->validate(
            $this->buildToken($this->validPayload()),
            $this->siteConfig()
        );

        $this->assertSame(self::SITE_ID, $payload['site']);
        $this->assertSame('user-123', $payload['sub']);
    }

    // ── Format / structure ────────────────────────────────────────────────────

    public function testTwoPartTokenThrows(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid token format');
        $this->validator->validate('only.two', $this->siteConfig());
    }

    // ── Signature ─────────────────────────────────────────────────────────────

    public function testWrongSecretThrows(): void
    {
        $token = $this->buildToken($this->validPayload(), 'wrong-secret');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid token signature');
        $this->validator->validate($token, $this->siteConfig());
    }

    // ── Time claims ───────────────────────────────────────────────────────────

    public function testExpiredTokenThrows(): void
    {
        $token = $this->buildToken($this->validPayload(['exp' => time() - 1]));
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Token expired');
        $this->validator->validate($token, $this->siteConfig());
    }

    public function testFutureIatThrows(): void
    {
        $token = $this->buildToken($this->validPayload(['iat' => time() + 120]));
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Token issued in the future');
        $this->validator->validate($token, $this->siteConfig());
    }

    // ── Required claims ───────────────────────────────────────────────────────

    #[DataProvider('missingClaimProvider')]
    public function testMissingRequiredClaimThrows(string $claim): void
    {
        $payload = $this->validPayload();
        unset($payload[$claim]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Token missing required claims');
        $this->validator->validate($this->buildToken($payload), $this->siteConfig());
    }

    public static function missingClaimProvider(): array
    {
        return [['jti'], ['sub'], ['site']];
    }

    // ── Strict base64 ────────────────────────────────────────────────────────

    public function testInvalidBase64PayloadThrows(): void
    {
        // Construct a token with invalid base64 in the payload but a valid signature
        // (so the signature check passes and we reach the payload decode step).
        $header         = $this->b64url(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $invalidPayload = 'not!!!valid!!base64'; // !! chars are outside base64url alphabet
        $sig            = $this->b64url(hash_hmac('sha256', "$header.$invalidPayload", self::SECRET, true));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid token payload');
        $this->validator->validate("$header.$invalidPayload.$sig", $this->siteConfig());
    }

    // ── Site binding ──────────────────────────────────────────────────────────

    public function testWrongSiteThrows(): void
    {
        $token = $this->buildToken($this->validPayload(['site' => 'other_site']));
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Token site mismatch');
        $this->validator->validate($token, $this->siteConfig());
    }

    // ── Replay prevention ─────────────────────────────────────────────────────

    public function testReplayedJtiThrows(): void
    {
        // Simulate PDO throwing a duplicate-key exception (SQLSTATE 23000).
        // Exception::getCode() is final in PHP 8.5+, so we set the protected
        // $code property via Reflection to make getCode() return '23000'.
        $ex   = new PDOException('Duplicate entry');
        $prop = new \ReflectionProperty('Exception', 'code');
        $prop->setValue($ex, '23000');
        $this->stmt->method('execute')->willThrowException($ex);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Token already used');
        $this->validator->validate(
            $this->buildToken($this->validPayload()),
            $this->siteConfig()
        );
    }
}
