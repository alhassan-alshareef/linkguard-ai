<?php

namespace LinkGuard\Services\Agents;

final class UrlStructureAgent
{
    private const SHORTENERS = [
        'bit.ly', 'tinyurl.com', 't.co', 'is.gd', 'buff.ly', 'ow.ly', 'cutt.ly', 'rebrand.ly',
    ];

    public function analyze(array $url): AgentResult
    {
        $findings = [];
        $host = $url['host'];
        $full = $url['url'];
        $labels = explode('.', $host);

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $findings[] = $this->finding('literal_ip', 'Host is a literal IP address', 'The link uses a public IP address instead of a recognizable domain.', 'high');
        }
        if (str_contains($host, 'xn--')) {
            $findings[] = $this->finding('punycode', 'Punycode is present', 'Encoded international characters can be used to imitate familiar domain names.', 'high');
        }
        if (count($labels) > 4) {
            $findings[] = $this->finding('excessive_subdomains', 'Excessive subdomains', 'The host has more nested labels than most ordinary websites.', 'medium');
        }
        if (in_array($host, self::SHORTENERS, true)) {
            $findings[] = $this->finding('url_shortener', 'URL shortener detected', 'The final destination is hidden behind a shortening service.', 'medium');
        }
        $length = strlen($full);
        if ($length > 180) {
            $findings[] = $this->finding('long_url_high', 'Unusually long URL', "The URL is {$length} characters long and is difficult to inspect manually.", 'medium');
        } elseif ($length > 100) {
            $findings[] = $this->finding('long_url_medium', 'Long URL', "The URL is {$length} characters long.", 'low');
        }
        if ($url['port'] !== null && !in_array((int) $url['port'], [80, 443], true)) {
            $findings[] = $this->finding('non_standard_port', 'Non-standard port', 'The link specifies a port that is uncommon for normal web browsing.', 'low');
        }
        if ($url['scheme'] === 'http') {
            $findings[] = $this->finding('http', 'Connection is not encrypted', 'HTTP does not protect traffic in transit; HTTPS alone would still not prove the site is safe.', 'low');
        }

        return new AgentResult('URL Structure Agent', 'complete', $findings);
    }

    private function finding(string $code, string $title, string $explanation, string $severity): array
    {
        return compact('code', 'title', 'explanation', 'severity');
    }
}
