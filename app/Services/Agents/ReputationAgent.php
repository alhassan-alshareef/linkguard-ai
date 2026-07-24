<?php

namespace LinkGuard\Services\Agents;

use LinkGuard\Services\Reputation\ReputationProvider;

final class ReputationAgent
{
    private ?array $lastDetails = null;

    public function __construct(private readonly ReputationProvider $provider)
    {
    }

    public function analyze(array $url): AgentResult
    {
        $result = $this->provider->check($url['url'], $url['host']);
        $this->lastDetails = $result;
        $findings = [];
        if ($result['verdict'] === 'malicious') {
            $findings[] = [
                'code' => 'reputation_malicious',
                'title' => 'Malicious reputation verdict',
                'explanation' => 'The configured reputation source reports harmful detections for this URL.',
                'severity' => 'critical',
            ];
        } elseif ($result['verdict'] === 'suspicious') {
            $findings[] = [
                'code' => 'reputation_suspicious',
                'title' => 'Suspicious reputation verdict',
                'explanation' => 'The configured reputation source reports suspicious signals for this URL.',
                'severity' => 'high',
            ];
        }

        $limitations = $result['status'] === 'unavailable'
            ? ['Reputation data is unavailable; this must not be interpreted as a clean result.']
            : [];

        return new AgentResult(
            'Reputation Agent',
            $result['status'],
            $findings,
            $limitations,
            $result['status'] === 'available' ? 0.9 : 0.0
        );
    }

    public function providerDetails(array $url): array
    {
        return $this->lastDetails ?? $this->provider->check($url['url'], $url['host']);
    }
}
