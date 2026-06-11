<?php

declare(strict_types=1);

namespace Tests\Unit;

use CsrfGuard;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class CsrfGuardTest extends TestCase
{
    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        unset($_SESSION['_csrf']);
        unset($_SERVER['HTTP_X_CSRF_TOKEN'], $_POST['_csrf']);
    }

    protected function tearDown(): void
    {
        unset($_SESSION['_csrf']);
        unset($_SERVER['HTTP_X_CSRF_TOKEN'], $_POST['_csrf']);
    }

    public function testTokenIsA64CharHexString(): void
    {
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', CsrfGuard::token());
    }

    public function testTokenIsStableAcrossCalls(): void
    {
        $this->assertSame(CsrfGuard::token(), CsrfGuard::token());
    }

    public function testVerifyPassesWithCorrectHeader(): void
    {
        $_SERVER['HTTP_X_CSRF_TOKEN'] = CsrfGuard::token();
        CsrfGuard::verify();
        $this->addToAssertionCount(1); // reached without exception
    }

    public function testVerifyPassesWithCorrectPostParam(): void
    {
        $_POST['_csrf'] = CsrfGuard::token();
        CsrfGuard::verify();
        $this->addToAssertionCount(1);
    }

    public function testVerifyFailsWithWrongToken(): void
    {
        CsrfGuard::token();
        $_SERVER['HTTP_X_CSRF_TOKEN'] = 'wrong';
        $this->expectException(RuntimeException::class);
        CsrfGuard::verify();
    }

    public function testVerifyFailsWithNoToken(): void
    {
        CsrfGuard::token();
        $this->expectException(RuntimeException::class);
        CsrfGuard::verify();
    }
}
