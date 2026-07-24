<?php

namespace LinkGuard\Services\Sandbox;

final class UnavailableContentSandbox implements ContentSandbox
{
    public function __construct(private readonly string $reason = 'Page inspection is disabled.')
    {
    }

    public function inspect(string $url): array
    {
        return [
            'status' => 'unavailable',
            'fetch_status' => 'disabled',
            'metadata' => null,
            'message' => $this->reason,
        ];
    }
}
