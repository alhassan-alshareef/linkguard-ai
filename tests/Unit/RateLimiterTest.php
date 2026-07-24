<?php

namespace Tests\Unit;

use LinkGuard\Support\RateLimiter;
use PHPUnit\Framework\TestCase;

final class RateLimiterTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/linkguard-rate-' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0775, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*.json') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->directory);
    }

    public function testItAllowsRequestsUntilTheConfiguredLimit(): void
    {
        $limiter = new RateLimiter($this->directory, 2, 60);

        self::assertTrue($limiter->attempt('client'));
        self::assertTrue($limiter->attempt('client'));
        self::assertFalse($limiter->attempt('client'));
    }

    public function testItDropsExpiredAttempts(): void
    {
        $key = 'client';
        $file = $this->directory . '/' . hash('sha256', $key) . '.json';
        file_put_contents($file, json_encode([time() - 120], JSON_THROW_ON_ERROR));

        self::assertTrue((new RateLimiter($this->directory, 1, 60))->attempt($key));
    }
}
