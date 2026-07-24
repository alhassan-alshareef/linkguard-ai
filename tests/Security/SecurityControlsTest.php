<?php

namespace Tests\Security;

use InvalidArgumentException;
use LinkGuard\Models\AnalysisRepository;
use LinkGuard\Services\Agents\AgentResult;
use LinkGuard\Services\Agents\ExplanationAgent;
use LinkGuard\Services\Agents\RiskScoringAgent;
use LinkGuard\Services\Reputation\MockReputationProvider;
use LinkGuard\Support\Escaper;
use LinkGuard\Support\UrlValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SecurityControlsTest extends TestCase
{
    #[DataProvider('ssrfPayloads')]
    public function testSsrfVariantsAreRejected(string $payload): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new UrlValidator())->validate($payload, false);
    }

    public static function ssrfPayloads(): array
    {
        return [
            ['http://0.0.0.0/'],
            ['http://127.1/'],
            ['http://2130706433/'],
            ['http://0x7f000001/'],
            ['http://0177.0.0.1/'],
            ['http://[::ffff:127.0.0.1]/'],
            ['http://192.0.2.1/'],
            ['http://198.51.100.1/'],
            ['http://203.0.113.1/'],
        ];
    }

    public function testHtmlInjectionIsRenderedAsText(): void
    {
        $payload = '<img src=x onerror=alert(1)>';
        self::assertSame(
            '&lt;img src=x onerror=alert(1)&gt;',
            Escaper::html($payload)
        );
    }

    public function testUnknownMockReputationIsNeverClean(): void
    {
        $result = (new MockReputationProvider())->check(
            'https://unlisted-domain.test/path',
            'unlisted-domain.test'
        );
        self::assertSame('unavailable', $result['status']);
        self::assertSame('unknown', $result['verdict']);
    }

    public function testUnavailableEvidenceProducesIncompleteSummary(): void
    {
        $explanation = (new ExplanationAgent())->explain(
            [],
            ['Reputation data is unavailable.'],
            ['score' => 0, 'level' => 'Low Risk'],
            ['status' => 'Limited']
        );
        self::assertStringContainsString('assessment is incomplete', $explanation['summary']);
        self::assertStringContainsString('Do not treat this as proof of safety', $explanation['summary']);
    }

    public function testRiskScoreCannotBeOverriddenByUntrustedFields(): void
    {
        $result = (new RiskScoringAgent(['phishing_keyword' => 10]))->analyze([
            new AgentResult('Untrusted Provider', 'complete', [[
                'code' => 'phishing_keyword',
                'title' => 'Keyword',
                'score' => 0,
                'override_score' => 0,
            ]]),
        ]);
        self::assertSame(10, $result['score']);
    }

    public function testSqlInjectionTextDoesNotExpandHistoryResults(): void
    {
        $repository = new AnalysisRepository(':memory:');
        self::assertSame([], $repository->search("' OR 1=1 --"));
    }

    public function testSecretsAreNotPresentInTrackedSourceFiles(): void
    {
        $paths = [
            BASE_PATH . '/app',
            BASE_PATH . '/config',
            BASE_PATH . '/public',
        ];
        foreach ($paths as $path) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path));
            foreach ($iterator as $file) {
                if (!$file->isFile()) {
                    continue;
                }
                $contents = file_get_contents($file->getPathname());
                self::assertDoesNotMatchRegularExpression('/(?:sk-proj-|AIza[0-9A-Za-z_-]{20,})/', (string) $contents);
            }
        }
    }
}
