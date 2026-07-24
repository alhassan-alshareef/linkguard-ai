<?php

namespace LinkGuard\Services\Sandbox;

use RuntimeException;

final class HttpContentSandbox implements ContentSandbox
{
    public function __construct(
        private readonly string $endpoint,
        private readonly string $token,
        private readonly int $timeoutSeconds = 8,
        private readonly int $maxResponseBytes = 65536,
    ) {
        $parts = parse_url($this->endpoint);
        $host = strtolower((string) ($parts['host'] ?? ''));
        if (($parts['scheme'] ?? '') !== 'http' || !in_array($host, ['127.0.0.1', 'localhost', '::1'], true)) {
            throw new RuntimeException('The content sandbox endpoint must use loopback HTTP.');
        }
        if (strlen($this->token) < 24) {
            throw new RuntimeException('The content sandbox token is missing or too short.');
        }
    }

    public function inspect(string $url): array
    {
        if (!function_exists('curl_init')) {
            return $this->unavailable('PHP cURL is unavailable.');
        }

        $payload = json_encode(['url' => $url], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $responseBody = '';
        $tooLarge = false;
        $handle = curl_init(rtrim($this->endpoint, '/') . '/inspect');
        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->token,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_TIMEOUT => max(3, $this->timeoutSeconds),
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP,
            CURLOPT_REDIR_PROTOCOLS => 0,
            CURLOPT_WRITEFUNCTION => function ($curl, string $chunk) use (&$responseBody, &$tooLarge): int {
                if (strlen($responseBody) + strlen($chunk) > $this->maxResponseBytes) {
                    $tooLarge = true;
                    return 0;
                }
                $responseBody .= $chunk;
                return strlen($chunk);
            },
        ]);

        $success = curl_exec($handle);
        $httpStatus = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);
        curl_close($handle);

        if ($success === false || $tooLarge) {
            return $this->unavailable($tooLarge ? 'Sandbox response exceeded its limit.' : 'Sandbox request failed: ' . $error);
        }

        $decoded = json_decode($responseBody, true);
        if (!is_array($decoded) || !isset($decoded['status'])) {
            return $this->unavailable('Sandbox returned an invalid response.');
        }
        if ($httpStatus !== 200 || $decoded['status'] !== 'available') {
            return $this->unavailable('Sandbox could not inspect this page.', (string) ($decoded['code'] ?? 'unavailable'));
        }

        return $this->sanitize($decoded);
    }

    private function sanitize(array $result): array
    {
        $metadata = is_array($result['metadata'] ?? null) ? $result['metadata'] : null;
        if ($metadata !== null) {
            $metadata = [
                'title' => mb_substr((string) ($metadata['title'] ?? ''), 0, 200),
                'title_status' => ($metadata['title_status'] ?? '') === 'present' ? 'present' : 'missing',
                'description' => mb_substr((string) ($metadata['description'] ?? ''), 0, 300),
                'forms' => max(0, (int) ($metadata['forms'] ?? 0)),
                'password_fields' => max(0, (int) ($metadata['password_fields'] ?? 0)),
                'external_form_actions' => max(0, (int) ($metadata['external_form_actions'] ?? 0)),
                'scripts' => max(0, (int) ($metadata['scripts'] ?? 0)),
                'iframes' => max(0, (int) ($metadata['iframes'] ?? 0)),
                'meta_refresh' => (bool) ($metadata['meta_refresh'] ?? false),
                'phishing_terms' => array_slice(array_values(array_filter(
                    (array) ($metadata['phishing_terms'] ?? []),
                    static fn ($term): bool => is_string($term)
                )), 0, 10),
                'response_security' => is_array($metadata['response_security'] ?? null)
                    ? $metadata['response_security']
                    : [],
            ];
        }

        return [
            'status' => 'available',
            'fetch_status' => (string) ($result['fetch_status'] ?? 'unknown'),
            'http_status' => (int) ($result['http_status'] ?? 0),
            'redirect_followed' => false,
            'bytes_read' => max(0, (int) ($result['bytes_read'] ?? 0)),
            'metadata' => $metadata,
            'message' => 'Metadata-only inspection completed in the isolated sandbox.',
        ];
    }

    private function unavailable(string $message, string $code = 'unavailable'): array
    {
        return [
            'status' => 'unavailable',
            'fetch_status' => $code,
            'metadata' => null,
            'message' => $message,
        ];
    }
}
