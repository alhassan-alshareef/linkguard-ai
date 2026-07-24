<?php

namespace LinkGuard\Services\Reputation;

final class UnavailableReputationProvider implements ReputationProvider
{
    public function __construct(private readonly string $reason = 'No provider is configured.')
    {
    }

    public function name(): string
    {
        return 'External reputation service';
    }

    public function check(string $url, string $host): array
    {
        return [
            'status' => 'unavailable',
            'verdict' => 'unknown',
            'source' => $this->name(),
            'mock' => false,
            'message' => $this->reason,
        ];
    }
}
