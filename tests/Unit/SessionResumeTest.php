<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Session;

/**
 * Tests Session::resumeFromHeader() — the cross-site session mechanism that
 * lets chat.js resume a PHP session via X-Session-Token instead of a cookie.
 *
 * The method is private so we invoke it via ReflectionMethod. We verify its
 * effect by checking session_id(), which returns the ID that will be used on
 * the next session_start() call.
 */
final class SessionResumeTest extends TestCase
{
    private static string $originalId = '';

    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        self::$originalId = session_id();
        unset($_SERVER['HTTP_X_SESSION_TOKEN']);
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        session_id(self::$originalId);
        unset($_SERVER['HTTP_X_SESSION_TOKEN']);
    }

    private function resume(): void
    {
        (new \ReflectionMethod(Session::class, 'resumeFromHeader'))->invoke(null);
    }

    // ── Accepted tokens ───────────────────────────────────────────────────────

    public function testTypicalAlphanumericTokenIsAccepted(): void
    {
        $id = 'a1b2c3d4e5f6a1b2c3d4e5f6'; // 24 chars — typical PHP session ID
        $_SERVER['HTTP_X_SESSION_TOKEN'] = $id;
        $this->resume();
        $this->assertSame($id, session_id());
    }

    public function testMinimumLengthTokenIsAccepted(): void
    {
        $id = str_repeat('a', 20); // min = 20
        $_SERVER['HTTP_X_SESSION_TOKEN'] = $id;
        $this->resume();
        $this->assertSame($id, session_id());
    }

    public function testMaximumLengthTokenIsAccepted(): void
    {
        $id = str_repeat('z', 128); // max = 128
        $_SERVER['HTTP_X_SESSION_TOKEN'] = $id;
        $this->resume();
        $this->assertSame($id, session_id());
    }

    public function testTokenWithHyphenAndCommaIsAccepted(): void
    {
        $id = str_repeat('a', 18) . '-,'; // PHP allows - and , in session IDs
        $_SERVER['HTTP_X_SESSION_TOKEN'] = $id;
        $this->resume();
        $this->assertSame($id, session_id());
    }

    // ── Rejected tokens — session_id must not change ──────────────────────────

    /** Sets a known sentinel first, then verifies it is unchanged after rejection. */
    private function assertIdUnchangedAfterResume(string $invalidToken): void
    {
        $sentinel = str_repeat('s', 26); // known value, valid length/format
        session_id($sentinel);
        $_SERVER['HTTP_X_SESSION_TOKEN'] = $invalidToken;
        $this->resume();
        $this->assertSame($sentinel, session_id());
    }

    public function testTooShortTokenIsRejected(): void
    {
        $this->assertIdUnchangedAfterResume(str_repeat('x', 19)); // one under min
    }

    public function testTooLongTokenIsRejected(): void
    {
        $this->assertIdUnchangedAfterResume(str_repeat('x', 129)); // one over max
    }

    public function testTokenWithSlashIsRejected(): void
    {
        $this->assertIdUnchangedAfterResume(str_repeat('a', 20) . '/path');
    }

    public function testTokenWithDotIsRejected(): void
    {
        $this->assertIdUnchangedAfterResume(str_repeat('a', 20) . '...');
    }

    public function testTokenWithSpaceIsRejected(): void
    {
        $this->assertIdUnchangedAfterResume(str_repeat('a', 20) . ' ');
    }

    public function testTokenWithNullByteIsRejected(): void
    {
        $this->assertIdUnchangedAfterResume(str_repeat('a', 20) . "\0");
    }

    public function testMissingHeaderLeavesIdUnchanged(): void
    {
        $sentinel = str_repeat('m', 26);
        session_id($sentinel);
        unset($_SERVER['HTTP_X_SESSION_TOKEN']);
        $this->resume();
        $this->assertSame($sentinel, session_id());
    }

    public function testEmptyHeaderLeavesIdUnchanged(): void
    {
        $this->assertIdUnchangedAfterResume('');
    }
}
