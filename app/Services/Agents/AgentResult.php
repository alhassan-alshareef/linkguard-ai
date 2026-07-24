<?php

namespace LinkGuard\Services\Agents;

final class AgentResult
{
    public function __construct(
        public readonly string $agent,
        public readonly string $status,
        public readonly array $findings = [],
        public readonly array $limitations = [],
        public readonly float $confidence = 1.0,
        public readonly array $metadata = [],
    ) {
    }

    public function toArray(): array
    {
        return [
            'agent' => $this->agent,
            'status' => $this->status,
            'findings' => $this->findings,
            'limitations' => $this->limitations,
            'confidence' => $this->confidence,
            'metadata' => $this->metadata,
        ];
    }
}
