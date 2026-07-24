<?php

namespace Tests\Unit;

use LinkGuard\Services\Agents\PageContentAgent;
use LinkGuard\Services\Sandbox\ContentSandbox;
use PHPUnit\Framework\TestCase;

final class PageContentAgentTest extends TestCase
{
    public function testPageSignalsBecomeDeterministicFindings(): void
    {
        $agent = new PageContentAgent($this->sandbox([
            'status' => 'available',
            'fetch_status' => 'inspected',
            'metadata' => [
                'title' => '',
                'title_status' => 'missing',
                'password_fields' => 1,
                'external_form_actions' => 1,
                'scripts' => 30,
                'meta_refresh' => true,
                'phishing_terms' => ['verify your account'],
            ],
        ]));

        $result = $agent->analyze(['url' => 'https://example.com']);
        self::assertSame('complete', $result->status);
        self::assertSame([
            'page_title_missing',
            'password_form',
            'external_form_action',
            'meta_refresh',
            'content_phishing_language',
            'excessive_scripts',
        ], array_column($result->findings, 'code'));
    }

    public function testUnavailableSandboxIsNeverTreatedAsClean(): void
    {
        $agent = new PageContentAgent($this->sandbox([
            'status' => 'unavailable',
            'fetch_status' => 'timeout',
            'metadata' => null,
            'message' => 'Sandbox timed out.',
        ]));

        $result = $agent->analyze(['url' => 'https://example.com']);
        self::assertSame('unavailable', $result->status);
        self::assertSame([], $result->findings);
        self::assertNotEmpty($result->limitations);
        self::assertSame(0.0, $result->confidence);
    }

    public function testBlockedRedirectProducesPartialCoverage(): void
    {
        $agent = new PageContentAgent($this->sandbox([
            'status' => 'available',
            'fetch_status' => 'redirect_blocked',
            'metadata' => null,
        ]));

        $result = $agent->analyze(['url' => 'https://example.com']);
        self::assertSame('partial', $result->status);
        self::assertStringContainsString('not followed', $result->limitations[0]);
    }

    private function sandbox(array $response): ContentSandbox
    {
        return new class($response) implements ContentSandbox {
            public function __construct(private readonly array $response)
            {
            }

            public function inspect(string $url): array
            {
                return $this->response;
            }
        };
    }
}
