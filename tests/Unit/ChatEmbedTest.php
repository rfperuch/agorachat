<?php

declare(strict_types=1);

namespace Tests\Unit;

use ChatEmbed;
use PHPUnit\Framework\TestCase;

final class ChatEmbedTest extends TestCase
{
    private ChatEmbed $chat;
    private array $user;
    private string $url;

    protected function setUp(): void
    {
        $this->chat = new ChatEmbed('demo_site', 'testsecret-32-bytes-xxxxxxxxxxxxxxxxxxxx');
        $this->user = ['user_id' => 42, 'display_name' => 'Tester'];
        $this->url  = 'https://chat.example.com/embed.php';
    }

    // ── Output structure ──────────────────────────────────────────────────────

    public function testOutputContainsIframeAndListenerScript(): void
    {
        $out = $this->chat->iframeTag($this->user, $this->url);
        $this->assertStringContainsString('<iframe ', $out);
        $this->assertStringContainsString('<script>', $out);
    }

    public function testSiteIdIsInSrc(): void
    {
        $out = $this->chat->iframeTag($this->user, $this->url);
        $this->assertStringContainsString('site=demo_site', $out);
    }

    public function testTokenIsInSrc(): void
    {
        $out = $this->chat->iframeTag($this->user, $this->url);
        $this->assertStringContainsString('token=', $out);
    }

    // ── Height ────────────────────────────────────────────────────────────────

    public function testDefaultHeightAttributeIs500(): void
    {
        $out = $this->chat->iframeTag($this->user, $this->url);
        $this->assertStringContainsString('height="500"', $out);
    }

    public function testDefaultHeightIsNotPassedAsUrlParam(): void
    {
        $out = $this->chat->iframeTag($this->user, $this->url);
        // When height=0 (use built-in default), ?h= must NOT appear in the URL
        $this->assertStringNotContainsString('&amp;h=', $out);
        $this->assertDoesNotMatchRegularExpression('/[?&]h=/', parse_url($out, PHP_URL_QUERY) ?? '');
    }

    public function testExplicitHeightSetsAttribute(): void
    {
        $out = $this->chat->iframeTag($this->user, $this->url, height: 700);
        $this->assertStringContainsString('height="700"', $out);
    }

    public function testExplicitHeightIsPassedToServerAsUrlParam(): void
    {
        $out = $this->chat->iframeTag($this->user, $this->url, height: 700);
        $this->assertStringContainsString('h=700', $out);
    }

    // ── Width ─────────────────────────────────────────────────────────────────

    public function testDefaultWidthIs100Percent(): void
    {
        $out = $this->chat->iframeTag($this->user, $this->url);
        $this->assertStringContainsString('width="100%"', $out);
    }

    public function testCustomWidthIsApplied(): void
    {
        $out = $this->chat->iframeTag($this->user, $this->url, width: '320px');
        $this->assertStringContainsString('width="320px"', $out);
    }

    // ── Theme ─────────────────────────────────────────────────────────────────

    public function testKnownThemeKeyIsAddedToUrl(): void
    {
        $out = $this->chat->iframeTag($this->user, $this->url, theme: ['primary' => '#e11d48']);
        $this->assertStringContainsString('primary=', $out);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('themeKeyProvider')]
    public function testAllKnownThemeKeysArePassedThrough(string $key): void
    {
        $out = $this->chat->iframeTag($this->user, $this->url, theme: [$key => '#aabbcc']);
        $this->assertStringContainsString($key . '=', $out);
    }

    public static function themeKeyProvider(): array
    {
        return array_map(
            fn($k) => [$k],
            ['primary', 'primary_fg', 'bg', 'msg_bg', 'msg_fg', 'meta', 'border']
        );
    }

    public function testUnknownThemeKeyIsNotPassedToUrl(): void
    {
        $out = $this->chat->iframeTag($this->user, $this->url, theme: ['__evil__' => 'val']);
        $this->assertStringNotContainsString('__evil__', $out);
    }

    // ── postMessage listener ──────────────────────────────────────────────────

    public function testListenerContainsResizeEventType(): void
    {
        $out = $this->chat->iframeTag($this->user, $this->url);
        $this->assertStringContainsString('agorachat:resize', $out);
    }

    public function testListenerChecksContentWindow(): void
    {
        $out = $this->chat->iframeTag($this->user, $this->url);
        $this->assertStringContainsString('contentWindow', $out);
    }

    public function testIframeIdMatchesListenerTargetId(): void
    {
        $out = $this->chat->iframeTag($this->user, $this->url);
        preg_match('/id="(agora-[a-f0-9]+)"/', $out, $m);
        $id = $m[1] ?? '';
        $this->assertNotEmpty($id);
        // The script must reference the same ID
        $this->assertStringContainsString(json_encode($id), $out);
    }

    public function testTwoCallsProduceDifferentIframeIds(): void
    {
        $out1 = $this->chat->iframeTag($this->user, $this->url);
        $out2 = $this->chat->iframeTag($this->user, $this->url);
        preg_match('/id="(agora-[a-f0-9]+)"/', $out1, $m1);
        preg_match('/id="(agora-[a-f0-9]+)"/', $out2, $m2);
        $this->assertNotSame($m1[1] ?? 'a', $m2[1] ?? 'b');
    }

    // ── Extra attrs ───────────────────────────────────────────────────────────

    public function testAdditionalAttrsAreIncluded(): void
    {
        $out = $this->chat->iframeTag($this->user, $this->url, attrs: ['class' => 'sidebar-chat']);
        $this->assertStringContainsString('class="sidebar-chat"', $out);
    }

    public function testAttrsOverrideDefaults(): void
    {
        $out = $this->chat->iframeTag($this->user, $this->url, attrs: ['width' => '50%']);
        $this->assertStringContainsString('width="50%"', $out);
        $this->assertStringNotContainsString('width="100%"', $out);
    }

    // ── Token generation ──────────────────────────────────────────────────────

    public function testGenerateTokenHasThreeParts(): void
    {
        $token = $this->chat->generateToken($this->user);
        $this->assertCount(3, explode('.', $token));
    }

    public function testGenerateTokenIsDifferentEachCall(): void
    {
        // JTI is random — two calls must produce different tokens
        $this->assertNotSame(
            $this->chat->generateToken($this->user),
            $this->chat->generateToken($this->user)
        );
    }
}
