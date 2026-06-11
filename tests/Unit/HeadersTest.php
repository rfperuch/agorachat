<?php

declare(strict_types=1);

namespace Tests\Unit;

use Headers;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class HeadersTest extends TestCase
{
    #[DataProvider('originProvider')]
    public function testOriginAllowed(string $origin, array $allowed, bool $expected): void
    {
        $this->assertSame($expected, Headers::originAllowed($origin, $allowed));
    }

    public static function originProvider(): array
    {
        return [
            'exact match'               => ['http://localhost:8888', ['http://localhost:8888'], true],
            'https scheme'              => ['https://example.com',   ['https://example.com'],   true],
            'wrong scheme'              => ['https://example.com',   ['http://example.com'],    false],
            'wrong host'                => ['http://evil.com',       ['http://example.com'],    false],
            'non-standard port'         => ['http://example.com:8080', ['http://example.com:8080'], true],
            'port mismatch'             => ['http://example.com:8080', ['http://example.com'],  false],
            'trailing slash in allowed' => ['http://example.com',   ['http://example.com/'],   true],
            'uppercase scheme normalised' => ['HTTP://example.com', ['http://example.com'],    true],
            'empty origin blocked'      => ['',                     ['http://example.com'],    false],
            'second entry matches'      => ['http://b.com', ['http://a.com', 'http://b.com'],  true],
            'no entry matches'          => ['http://c.com', ['http://a.com', 'http://b.com'],  false],
        ];
    }
}
