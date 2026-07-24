<?php

namespace Tests\Feature;

use LinkGuard\Models\AnalysisRepository;
use LinkGuard\Services\AnalysisOrchestrator;
use LinkGuard\Services\Agents\ExplanationAgent;
use LinkGuard\Services\Agents\PhishingPatternAgent;
use LinkGuard\Services\Agents\PageContentAgent;
use LinkGuard\Services\Agents\ReputationAgent;
use LinkGuard\Services\Agents\RiskScoringAgent;
use LinkGuard\Services\Agents\UrlStructureAgent;
use LinkGuard\Services\Reputation\MockReputationProvider;
use LinkGuard\Services\Sandbox\ContentSandbox;
use LinkGuard\Support\UrlValidator;
use PHPUnit\Framework\TestCase;

final class RepositoryAndOrchestratorTest extends TestCase
{
    private AnalysisRepository $repository;

    protected function setUp(): void
    {
        $this->repository = new AnalysisRepository(':memory:');
    }

    public function testOrchestratorBuildsAndPersistsACompleteReport(): void
    {
        $orchestrator = new AnalysisOrchestrator(
            new UrlValidator(),
            new UrlStructureAgent(),
            new ReputationAgent(new MockReputationProvider()),
            new PhishingPatternAgent(),
            new PageContentAgent(new class implements ContentSandbox {
                public function inspect(string $url): array
                {
                    return [
                        'status' => 'available',
                        'fetch_status' => 'inspected',
                        'http_status' => 200,
                        'metadata' => [
                            'title' => 'Example',
                            'title_status' => 'present',
                            'forms' => 0,
                            'password_fields' => 0,
                            'external_form_actions' => 0,
                            'scripts' => 0,
                            'iframes' => 0,
                            'meta_refresh' => false,
                            'phishing_terms' => [],
                        ],
                    ];
                }
            }),
            new RiskScoringAgent((array) config('risk')),
            new ExplanationAgent(),
            $this->repository,
        );
        $report = $orchestrator->analyze('https://paypal-secure.example.com/verify-account');
        self::assertSame(60, $report['risk']['score']);
        self::assertSame('High Risk', $report['risk']['level']);
        self::assertCount(4, $report['agents']);
        self::assertSame('Demonstration', $report['coverage']['status']);
        self::assertSame('Example', $report['coverage']['page_title']);
        self::assertSame(85, $report['coverage']['coverage_percent']);
        self::assertSame(1, $this->repository->count());
        self::assertSame($report['case_id'], $this->repository->find($report['case_id'])['case_id']);
    }

    public function testRepositorySearchAndDeleteUseBoundParameters(): void
    {
        $report = $this->sampleReport("https://example.com/?q=' OR 1=1 --");
        $this->repository->save($report);
        self::assertCount(1, $this->repository->search("' OR 1=1 --"));
        self::assertTrue($this->repository->delete($report['case_id']));
        self::assertSame(0, $this->repository->count());
    }

    private function sampleReport(string $url): array
    {
        return [
            'case_id' => 'LG-TEST-000001',
            'submitted_url' => $url,
            'url' => ['url' => $url, 'host' => 'example.com', 'scheme' => 'https'],
            'created_at' => date(DATE_ATOM),
            'risk' => ['score' => 0, 'level' => 'Low Risk'],
            'report' => [],
        ];
    }
}
