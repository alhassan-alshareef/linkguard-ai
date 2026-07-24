<?php

namespace LinkGuard\Services;

use LinkGuard\Models\AnalysisRepository;
use LinkGuard\Services\Agents\ExplanationAgent;
use LinkGuard\Services\Agents\PhishingPatternAgent;
use LinkGuard\Services\Agents\PageContentAgent;
use LinkGuard\Services\Agents\ReputationAgent;
use LinkGuard\Services\Agents\RiskScoringAgent;
use LinkGuard\Services\Agents\UrlStructureAgent;
use LinkGuard\Support\UrlValidator;

final class AnalysisOrchestrator
{
    public function __construct(
        private readonly UrlValidator $validator,
        private readonly UrlStructureAgent $structureAgent,
        private readonly ReputationAgent $reputationAgent,
        private readonly PhishingPatternAgent $phishingAgent,
        private readonly PageContentAgent $pageContentAgent,
        private readonly RiskScoringAgent $riskAgent,
        private readonly ExplanationAgent $explanationAgent,
        private readonly AnalysisRepository $repository,
    ) {
    }

    public function analyze(string $submittedUrl): array
    {
        $url = $this->validator->validate($submittedUrl);
        $structure = $this->structureAgent->analyze($url);
        $reputation = $this->reputationAgent->analyze($url);
        $phishing = $this->phishingAgent->analyze($url);
        $pageContent = $this->pageContentAgent->analyze($url);
        $agents = [$structure, $reputation, $phishing, $pageContent];
        $risk = $this->riskAgent->analyze($agents);

        $findings = [];
        $limitations = [];
        foreach ($agents as $result) {
            array_push($findings, ...$result->findings);
            array_push($limitations, ...$result->limitations);
        }
        $provider = $this->reputationAgent->providerDetails($url);
        $coverage = $this->coverage($provider, $pageContent);
        $explanation = $this->explanationAgent->explain($findings, $limitations, $risk, $coverage);
        $caseId = 'LG-' . gmdate('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));

        $report = [
            'case_id' => $caseId,
            'submitted_url' => trim($submittedUrl),
            'url' => $url,
            'created_at' => date(DATE_ATOM),
            'agents' => array_map(static fn ($result): array => $result->toArray(), $agents),
            'risk' => $risk,
            'coverage' => $coverage,
            'findings' => $findings,
            'explanation' => $explanation,
            'reputation' => $provider,
            'page' => $pageContent->metadata,
            'disclaimer' => 'This report is a risk assessment based on available indicators, not a guarantee that the link is safe or harmful.',
        ];

        $this->repository->save($report);
        return $report;
    }

    private function coverage(array $provider, \LinkGuard\Services\Agents\AgentResult $pageContent): array
    {
        $liveReputation = ($provider['status'] ?? '') === 'available' && !(bool) ($provider['mock'] ?? false);
        $mockReputation = ($provider['status'] ?? '') === 'available' && (bool) ($provider['mock'] ?? false);
        $pageInspected = $pageContent->status === 'complete'
            && ($pageContent->metadata['fetch_status'] ?? '') === 'inspected';
        $status = match (true) {
            $liveReputation && $pageInspected => 'Comprehensive',
            $mockReputation && $pageInspected => 'Demonstration',
            $liveReputation || $pageInspected => 'Extended',
            default => 'Limited',
        };
        $coveragePercent = min(100, 50 + ($liveReputation ? 25 : 0) + ($mockReputation ? 10 : 0) + ($pageInspected ? 25 : 0));
        $metadata = is_array($pageContent->metadata['metadata'] ?? null) ? $pageContent->metadata['metadata'] : [];
        $pageTitle = $pageInspected
            ? (($metadata['title_status'] ?? '') === 'present' ? (string) $metadata['title'] : 'Missing')
            : 'Not inspected';

        return [
            'status' => $status,
            'coverage_percent' => $coveragePercent,
            'url_structure' => 'Inspected',
            'phishing_patterns' => 'Inspected from URL text',
            'reputation' => ($provider['status'] ?? '') === 'available'
                ? ((bool) ($provider['mock'] ?? false) ? 'Mock data only' : 'Live provider checked')
                : 'Unavailable',
            'dns' => 'Validated only when records were returned',
            'page_title' => $pageTitle,
            'page_content' => $pageInspected
                ? 'Metadata-only HTML inspection completed'
                : ucfirst(str_replace('_', ' ', (string) ($pageContent->metadata['fetch_status'] ?? 'not inspected'))),
            'reason' => $pageInspected
                ? 'HTML metadata was fetched in an isolated service; scripts, redirects, and downloads were blocked.'
                : 'The isolated content sandbox did not return inspectable page metadata.',
        ];
    }
}
