<?php

namespace LinkGuard\Services\Agents;

final class PhishingPatternAgent
{
    private const KEYWORDS = ['login', 'verify', 'verification', 'secure', 'account', 'update', 'prize', 'wallet', 'password', 'invoice'];
    private const BRANDS = [
        'paypal' => ['paypal.com'],
        'apple' => ['apple.com'],
        'microsoft' => ['microsoft.com', 'live.com'],
        'amazon' => ['amazon.com'],
        'google' => ['google.com'],
        'zain' => ['zain.com', 'sa.zain.com', 'kw.zain.com', 'jo.zain.com', 'iq.zain.com'],
        'webook' => ['webook.com'],
    ];

    public function analyze(array $url): AgentResult
    {
        $findings = [];
        $sensitive = strtolower($url['path'] . '?' . $url['query']);
        $matched = array_values(array_filter(self::KEYWORDS, static fn (string $word): bool => str_contains($sensitive, $word)));
        if ($matched !== []) {
            $findings[] = [
                'code' => 'phishing_keyword',
                'title' => 'Phishing-related wording',
                'explanation' => 'Sensitive URL positions contain: ' . implode(', ', array_slice($matched, 0, 4)) . '.',
                'severity' => 'medium',
                'evidence' => $matched,
            ];
        }

        $host = strtolower($url['host']);
        $compact = preg_replace('/[^a-z0-9]/', '', $host) ?? $host;
        foreach (self::BRANDS as $brand => $trustedDomains) {
            $trusted = in_array($host, $trustedDomains, true)
                || array_filter($trustedDomains, static fn (string $domain): bool => str_ends_with($host, '.' . $domain));
            $distance = levenshtein($brand, explode('.', $host)[0]);
            $resembles = str_contains($compact, $brand) || ($distance > 0 && $distance <= 2);
            if (!$trusted && $resembles) {
                $findings[] = [
                    'code' => 'brand_impersonation',
                    'title' => 'Possible brand impersonation',
                    'explanation' => "The host resembles “{$brand}” but is not one of its recognized domains.",
                    'severity' => 'high',
                    'evidence' => [$brand],
                ];
                break;
            }
        }

        return new AgentResult(
            'Phishing Pattern Agent',
            'complete',
            $findings,
            ['The URL-pattern check uses address text only; isolated page metadata is reported by the Sandbox Content Agent.'],
            0.78
        );
    }
}
