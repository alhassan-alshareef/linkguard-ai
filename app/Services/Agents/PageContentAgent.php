<?php

namespace LinkGuard\Services\Agents;

use LinkGuard\Services\Sandbox\ContentSandbox;

final class PageContentAgent
{
    public function __construct(private readonly ContentSandbox $sandbox)
    {
    }

    public function analyze(array $url): AgentResult
    {
        $inspection = $this->sandbox->inspect($url['url']);
        if (($inspection['status'] ?? '') !== 'available') {
            return new AgentResult(
                'Sandbox Content Agent',
                'unavailable',
                [],
                ['Page metadata could not be inspected: ' . ($inspection['message'] ?? 'sandbox unavailable')],
                0.0,
                $inspection,
            );
        }

        $metadata = is_array($inspection['metadata'] ?? null) ? $inspection['metadata'] : null;
        if (($inspection['fetch_status'] ?? '') !== 'inspected' || $metadata === null) {
            $reason = match ($inspection['fetch_status'] ?? '') {
                'redirect_blocked' => 'The page returned a redirect. Redirects are intentionally not followed.',
                'unsupported_content_type' => 'The response was not an HTML page.',
                default => 'The sandbox returned no inspectable HTML metadata.',
            };
            return new AgentResult(
                'Sandbox Content Agent',
                'partial',
                [],
                [$reason],
                0.4,
                $inspection,
            );
        }

        $findings = [];
        if (($metadata['title_status'] ?? '') !== 'present') {
            $findings[] = $this->finding('page_title_missing', 'Page title is missing', 'The inspected HTML did not contain a usable page title. This is a weak quality signal, not proof of harm.', 'low');
        }
        if ((int) ($metadata['password_fields'] ?? 0) > 0) {
            $findings[] = $this->finding('password_form', 'Password field detected', 'The page requests a password. Verify the domain independently before entering credentials.', 'medium');
        }
        if ((int) ($metadata['external_form_actions'] ?? 0) > 0) {
            $findings[] = $this->finding('external_form_action', 'Form submits to another origin', 'At least one form sends data to a different site, which can be a meaningful credential-harvesting indicator.', 'high');
        }
        if ((bool) ($metadata['meta_refresh'] ?? false)) {
            $findings[] = $this->finding('meta_refresh', 'Automatic page refresh or redirect declared', 'The page contains a meta refresh directive. The sandbox did not follow it.', 'medium');
        }
        if ((array) ($metadata['phishing_terms'] ?? []) !== []) {
            $findings[] = $this->finding('content_phishing_language', 'Phishing-style language found in page text', 'The visible HTML text contains account-verification, urgency, suspension, or prize wording.', 'high');
        }
        if ((int) ($metadata['scripts'] ?? 0) >= 25) {
            $findings[] = $this->finding('excessive_scripts', 'Unusually high script count', 'The page declares many scripts. Scripts were counted but never executed.', 'low');
        }

        return new AgentResult(
            'Sandbox Content Agent',
            'complete',
            $findings,
            ['Page JavaScript, downloads, and redirects were not executed or followed.'],
            0.8,
            $inspection,
        );
    }

    private function finding(string $code, string $title, string $explanation, string $severity): array
    {
        return compact('code', 'title', 'explanation', 'severity');
    }
}
