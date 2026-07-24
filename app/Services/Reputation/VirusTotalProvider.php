<?php

namespace LinkGuard\Services\Reputation;

final class VirusTotalProvider implements ReputationProvider
{
    public function __construct(
        private readonly string $apiKey,
        private readonly int $timeout = 5,
    ) {
    }

    public function name(): string
    {
        return 'VirusTotal';
    }

    public function check(string $url, string $host): array
    {
        if ($this->apiKey === '') {
            return $this->unavailable('VirusTotal mode is selected but no API key is configured.');
        }

        $id = rtrim(strtr(base64_encode($url), '+/', '-_'), '=');
        $handle = curl_init('https://www.virustotal.com/api/v3/urls/' . rawurlencode($id));
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => $this->timeout,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_HTTPHEADER => ['x-apikey: ' . $this->apiKey, 'Accept: application/json'],
            CURLOPT_MAXREDIRS => 0,
        ]);
        $body = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);
        curl_close($handle);

        if ($body === false || $status !== 200) {
            return $this->unavailable($error !== '' ? 'The provider request failed.' : "Provider returned HTTP {$status}.");
        }
        $payload = json_decode($body, true);
        $stats = $payload['data']['attributes']['last_analysis_stats'] ?? null;
        if (!is_array($stats)) {
            return $this->unavailable('The provider response did not contain analysis statistics.');
        }

        $malicious = (int) ($stats['malicious'] ?? 0);
        $suspicious = (int) ($stats['suspicious'] ?? 0);
        $verdict = $malicious > 0 ? 'malicious' : ($suspicious > 0 ? 'suspicious' : 'clean');

        return [
            'status' => 'available',
            'verdict' => $verdict,
            'source' => $this->name(),
            'mock' => false,
            'message' => "Provider engines: {$malicious} malicious, {$suspicious} suspicious.",
        ];
    }

    private function unavailable(string $message): array
    {
        return [
            'status' => 'unavailable',
            'verdict' => 'unknown',
            'source' => $this->name(),
            'mock' => false,
            'message' => $message,
        ];
    }
}
