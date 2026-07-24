<?php

namespace LinkGuard\Services\Reputation;

final class MockReputationProvider implements ReputationProvider
{
    public function name(): string
    {
        return 'Local demonstration dataset';
    }

    public function check(string $url, string $host): array
    {
        $value = strtolower($url);
        $verdict = 'unknown';
        $status = 'unavailable';
        $message = 'No matching fixture exists in the local mock dataset; no external reputation service was queried.';
        if (str_contains($value, 'known-risk') || str_contains($value, 'malicious-demo')) {
            $verdict = 'malicious';
            $status = 'available';
            $message = 'Matched a malicious demonstration fixture in the local mock dataset.';
        } elseif (str_contains($value, 'suspicious-demo') || str_contains($value, 'verify-account')) {
            $verdict = 'suspicious';
            $status = 'available';
            $message = 'Matched a suspicious demonstration fixture in the local mock dataset.';
        } elseif ($host === 'example.com' || str_ends_with($host, '.example.com')) {
            $verdict = 'clean';
            $status = 'available';
            $message = 'Matched the explicitly safe example.com demonstration fixture.';
        }

        return [
            'status' => $status,
            'verdict' => $verdict,
            'source' => $this->name(),
            'mock' => true,
            'message' => $message,
        ];
    }
}
