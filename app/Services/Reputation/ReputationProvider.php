<?php

namespace LinkGuard\Services\Reputation;

interface ReputationProvider
{
    public function name(): string;

    public function check(string $url, string $host): array;
}
