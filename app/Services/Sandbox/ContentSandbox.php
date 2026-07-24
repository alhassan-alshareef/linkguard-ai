<?php

namespace LinkGuard\Services\Sandbox;

interface ContentSandbox
{
    public function inspect(string $url): array;
}
