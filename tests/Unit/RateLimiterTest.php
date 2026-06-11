<?php

declare(strict_types=1);

namespace Tests\Unit;

use PDO;
use PDOStatement;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RateLimiter;

final class RateLimiterTest extends TestCase
{
    private PDO&MockObject $pdo;
    private PDOStatement&MockObject $stmt;
    private RateLimiter $limiter;

    protected function setUp(): void
    {
        $this->stmt    = $this->createMock(PDOStatement::class);
        $this->pdo     = $this->createMock(PDO::class);
        $this->pdo->method('prepare')->willReturn($this->stmt);
        $this->limiter = new RateLimiter($this->pdo);
    }

    public function testAllowsWhenRowCountPositive(): void
    {
        // INSERT succeeded (new row) or UPDATE incremented (row changed) → rowCount > 0
        $this->stmt->method('execute')->willReturn(true);
        $this->stmt->method('rowCount')->willReturn(1);

        $this->assertTrue($this->limiter->allow('key', 10, 60));
    }

    public function testBlocksWhenRowCountZero(): void
    {
        // ON DUPLICATE KEY no-op (hit_count already at max) → rowCount = 0
        $this->stmt->method('execute')->willReturn(true);
        $this->stmt->method('rowCount')->willReturn(0);

        $this->assertFalse($this->limiter->allow('key', 10, 60));
    }

    public function testDifferentKeysAreIndependent(): void
    {
        $this->stmt->method('execute')->willReturn(true);
        // First call allowed (rowCount=1), second call allowed (rowCount=2 from ON DUPLICATE UPDATE)
        $this->stmt->method('rowCount')->willReturnOnConsecutiveCalls(1, 2);

        $this->assertTrue($this->limiter->allow('key-a', 10, 60));
        $this->assertTrue($this->limiter->allow('key-b', 10, 60));
    }
}
