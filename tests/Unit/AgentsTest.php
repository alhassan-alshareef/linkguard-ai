<?php

namespace Tests\Unit;

use LinkGuard\Services\Agents\PhishingPatternAgent;
use LinkGuard\Services\Agents\ReputationAgent;
use LinkGuard\Services\Agents\UrlStructureAgent;
use LinkGuard\Services\Reputation\MockReputationProvider;
use LinkGuard\Services\Reputation\UnavailableReputationProvider;
use PHPUnit\Framework\TestCase;

final class AgentsTest extends TestCase
{
    public function testStructureAgentReturnsWeightedEvidence(): void
    {
        $result = (new UrlStructureAgent())->analyze([
            'url' => 'http://xn--pple-43d.example.com:8080/' . str_repeat('a', 190),
            'scheme' => 'http',
            'host' => 'xn--pple-43d.example.com',
            'port' => 8080,
            'path' => '/',
            'query' => '',
        ]);
        $codes = array_column($result->findings, 'code');
        self::assertContains('punycode', $codes);
        self::assertContains('long_url_high', $codes);
        self::assertContains('non_standard_port', $codes);
        self::assertContains('http', $codes);
    }

    public function testPhishingAgentFindsKeywordsAndBrandImpersonation(): void
    {
        $result = (new PhishingPatternAgent())->analyze([
            'url' => 'https://paypal-secure.example.com/verify-account',
            'scheme' => 'https',
            'host' => 'paypal-secure.example.com',
            'port' => null,
            'path' => '/verify-account',
            'query' => '',
        ]);
        self::assertSame(['phishing_keyword', 'brand_impersonation'], array_column($result->findings, 'code'));
    }

    public function testMockProviderIsClearlyLabeled(): void
    {
        $agent = new ReputationAgent(new MockReputationProvider());
        $result = $agent->analyze($this->url('https://known-risk.example.com/'));
        self::assertSame('reputation_malicious', $result->findings[0]['code']);
        self::assertTrue($agent->providerDetails([])['mock']);
    }

    public function testUnavailableProviderDoesNotFabricateACleanVerdict(): void
    {
        $agent = new ReputationAgent(new UnavailableReputationProvider());
        $result = $agent->analyze($this->url('https://example.com/'));
        self::assertSame('unavailable', $result->status);
        self::assertSame([], $result->findings);
        self::assertNotEmpty($result->limitations);
    }

    public function testUnknownMockUrlIsUnavailableRatherThanClean(): void
    {
        $agent = new ReputationAgent(new MockReputationProvider());
        $result = $agent->analyze($this->url('https://unlisted.test-domain.example/'));

        self::assertSame('unavailable', $result->status);
        self::assertSame('unknown', $agent->providerDetails([])['verdict']);
        self::assertNotEmpty($result->limitations);
    }

    public function testItDetectsZainImpersonationOnSharedHosting(): void
    {
        $result = (new PhishingPatternAgent())->analyze(
            $this->url('https://offers-eshop-zain.netlify.app/')
        );

        self::assertContains('brand_impersonation', array_column($result->findings, 'code'));
    }

    public function testItDetectsWebookLookalikeDomain(): void
    {
        $result = (new PhishingPatternAgent())->analyze(
            $this->url('https://weboak.com/')
        );

        self::assertContains('brand_impersonation', array_column($result->findings, 'code'));
    }

    private function url(string $url): array
    {
        return [
            'url' => $url,
            'scheme' => 'https',
            'host' => parse_url($url, PHP_URL_HOST),
            'port' => null,
            'path' => parse_url($url, PHP_URL_PATH) ?: '/',
            'query' => '',
        ];
    }
}
