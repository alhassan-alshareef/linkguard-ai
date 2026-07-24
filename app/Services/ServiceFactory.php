<?php

namespace LinkGuard\Services;

use LinkGuard\Models\AnalysisRepository;
use LinkGuard\Services\Agents\ExplanationAgent;
use LinkGuard\Services\Agents\PhishingPatternAgent;
use LinkGuard\Services\Agents\PageContentAgent;
use LinkGuard\Services\Agents\ReputationAgent;
use LinkGuard\Services\Agents\RiskScoringAgent;
use LinkGuard\Services\Agents\UrlStructureAgent;
use LinkGuard\Services\Reputation\MockReputationProvider;
use LinkGuard\Services\Reputation\ReputationProvider;
use LinkGuard\Services\Reputation\UnavailableReputationProvider;
use LinkGuard\Services\Reputation\VirusTotalProvider;
use LinkGuard\Services\Sandbox\ContentSandbox;
use LinkGuard\Services\Sandbox\HttpContentSandbox;
use LinkGuard\Services\Sandbox\UnavailableContentSandbox;
use LinkGuard\Support\UrlValidator;

final class ServiceFactory
{
    public static function repository(): AnalysisRepository
    {
        return new AnalysisRepository();
    }

    public static function orchestrator(?AnalysisRepository $repository = null): AnalysisOrchestrator
    {
        $provider = self::reputationProvider();
        return new AnalysisOrchestrator(
            new UrlValidator(),
            new UrlStructureAgent(),
            new ReputationAgent($provider),
            new PhishingPatternAgent(),
            new PageContentAgent(self::contentSandbox()),
            new RiskScoringAgent((array) config('risk')),
            new ExplanationAgent(),
            $repository ?? self::repository(),
        );
    }

    public static function contentSandbox(): ContentSandbox
    {
        if (strtolower((string) config('app.content_sandbox_mode')) !== 'enabled') {
            return new UnavailableContentSandbox();
        }

        try {
            return new HttpContentSandbox(
                (string) config('app.content_sandbox_url'),
                (string) config('app.content_sandbox_token'),
                (int) config('app.content_sandbox_timeout'),
                (int) config('app.content_sandbox_max_response'),
            );
        } catch (\RuntimeException $exception) {
            return new UnavailableContentSandbox($exception->getMessage());
        }
    }

    public static function reputationProvider(): ReputationProvider
    {
        return match (strtolower((string) config('app.reputation_mode'))) {
            'mock' => new MockReputationProvider(),
            'virustotal' => new VirusTotalProvider(
                (string) config('app.virustotal_key'),
                (int) config('app.reputation_timeout')
            ),
            default => new UnavailableReputationProvider('Reputation mode is disabled or unrecognized.'),
        };
    }
}
