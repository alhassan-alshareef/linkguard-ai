<?php

namespace LinkGuard\Support;

final class RateLimiter
{
    public function __construct(
        private readonly string $directory,
        private readonly int $maxAttempts,
        private readonly int $windowSeconds,
    ) {
    }

    public function attempt(string $key): bool
    {
        $file = $this->directory . '/' . hash('sha256', $key) . '.json';
        $now = time();
        $handle = fopen($file, 'c+');
        if ($handle === false) {
            return false;
        }
        try {
            if (!flock($handle, LOCK_EX)) {
                return false;
            }
            $raw = stream_get_contents($handle);
            $entries = $raw ? json_decode($raw, true) : [];
            $cutoff = $now - $this->windowSeconds;
            $entries = array_values(array_filter(
                is_array($entries) ? $entries : [],
                static fn (int $time): bool => $time > $cutoff
            ));
            if (count($entries) >= $this->maxAttempts) {
                return false;
            }
            $entries[] = $now;
            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, json_encode($entries, JSON_THROW_ON_ERROR));
            fflush($handle);
            return true;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
