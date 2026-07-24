<?php

declare(strict_types=1);

namespace LinkGuard\Security;

use InvalidArgumentException;
use LinkGuard\Models\AnalysisRepository;
use LinkGuard\Services\Agents\AgentResult;
use LinkGuard\Services\Agents\ExplanationAgent;
use LinkGuard\Services\Agents\RiskScoringAgent;
use LinkGuard\Services\PdfReportService;
use LinkGuard\Services\Reputation\MockReputationProvider;
use LinkGuard\Support\Escaper;
use LinkGuard\Support\RateLimiter;
use LinkGuard\Support\UrlValidator;
use Throwable;

final class SecurityTestRunner
{
    public function __construct(
        private readonly array $dataset,
        private readonly string $baseUrl = 'http://127.0.0.1:8000',
    ) {
    }

    public function run(): array
    {
        $results = [];
        foreach ($this->dataset['cases'] ?? [] as $case) {
            $results[] = $this->runCase($case);
        }
        $counts = array_fill_keys(['passed', 'failed', 'skipped', 'not-applicable'], 0);
        foreach ($results as $result) {
            $counts[$result['status']]++;
        }
        $executed = $counts['passed'] + $counts['failed'];
        $score = $executed > 0 ? (int) round(($counts['passed'] / $executed) * 100) : 0;
        $criticalFailure = array_filter(
            $results,
            static fn (array $result): bool => $result['status'] === 'failed' && $result['severity'] === 'critical'
        ) !== [];
        if ($criticalFailure) {
            $score = min($score, 49);
        }

        return [
            'generated_at' => date(DATE_ATOM),
            'dataset_version' => $this->dataset['version'] ?? 'unknown',
            'base_url' => $this->baseUrl,
            'total' => count($results),
            'counts' => $counts,
            'overall_score' => $score,
            'critical_failure' => $criticalFailure,
            'results' => $results,
        ];
    }

    private function runCase(array $case): array
    {
        $base = [
            'id' => $case['id'],
            'category' => $case['category'],
            'title' => $case['title'],
            'severity' => $case['severity'],
            'status' => 'failed',
            'evidence' => '',
            'remediation' => '',
        ];
        if (!($case['enabled'] ?? false)) {
            return array_merge($base, ['status' => 'skipped', 'evidence' => 'Case disabled in dataset.']);
        }
        if ($case['requires_ai'] ?? false) {
            return array_merge($base, [
                'status' => 'skipped',
                'evidence' => 'No live LLM execution path exists in this release.',
                'remediation' => 'Run this case when an LLM-backed explanation adapter is enabled.',
            ]);
        }

        try {
            [$passed, $evidence, $remediation] = $this->execute($case);
            return array_merge($base, [
                'status' => $passed ? 'passed' : 'failed',
                'evidence' => $evidence,
                'remediation' => $passed ? '' : $remediation,
            ]);
        } catch (Throwable $exception) {
            return array_merge($base, [
                'status' => 'failed',
                'evidence' => 'Runner exception: ' . $exception->getMessage(),
                'remediation' => 'Fix the underlying control or runner integration, then rerun.',
            ]);
        }
    }

    private function execute(array $case): array
    {
        $tags = $case['tags'] ?? [];
        if (in_array('validator', $tags, true)) {
            $url = $case['request']['body']['url'] ?? null;
            if ($url === null && isset($case['request']['body']['url_repeat'])) {
                $repeat = $case['request']['body']['url_repeat'];
                $url = $repeat['prefix'] . str_repeat($repeat['character'], (int) $repeat['count']);
            }
            $rejected = false;
            try {
                (new UrlValidator())->validate((string) $url, false);
            } catch (InvalidArgumentException) {
                $rejected = true;
            }
            $expected = (bool) $case['expected']['rejected'];
            return [
                $rejected === $expected,
                'Validator rejected=' . ($rejected ? 'true' : 'false') . ', expected=' . ($expected ? 'true' : 'false'),
                'Harden URL parsing and hostname/IP canonicalization.',
            ];
        }
        if (in_array('csrf', $tags, true)) {
            return $this->csrfCheck($case);
        }

        return match ($case['id']) {
            'SSRF-008' => $this->sourceContains(
                BASE_PATH . '/app/Services/Reputation/VirusTotalProvider.php',
                'CURLOPT_FOLLOWLOCATION => false',
                'Disable redirect following for fixed-host provider requests.'
            ),
            'SANDBOX-001' => $this->sandboxCheck($case, false),
            'SANDBOX-002', 'SANDBOX-003' => $this->sandboxCheck($case, true),
            'SANDBOX-004' => $this->sandboxSourceCheck($case),
            'XSS-001' => $this->escapeCheck($case),
            'SQLI-001' => $this->sqlInjectionCheck(),
            'COMMAND-001' => $this->commandExecutionCheck(),
            'RATE-001' => $this->rateLimitCheck(),
            'PDF-001' => $this->pdfCheck(),
            'SECRETS-001' => $this->secretCheck(),
            'HEADERS-001' => $this->headerCheck($case),
            'SESSION-001' => $this->sessionCheck($case),
            'BUSINESS-001' => $this->scoreCapCheck(),
            'BUSINESS-002' => $this->mockUnknownCheck(),
            'BUSINESS-003' => $this->coverageCheck(),
            'PATH-001' => $this->pathTraversalCheck(),
            default => [false, 'No executable handler for case.', 'Add a runner handler for this dataset case.'],
        };
    }

    private function escapeCheck(array $case): array
    {
        $value = (string) $case['request']['body']['value'];
        $escaped = Escaper::html($value);
        $passed = !str_contains($escaped, '<img') && str_contains($escaped, '&lt;img');
        return [$passed, 'Escaped output: ' . $escaped, 'Use context-aware escaping in every HTML and PDF template.'];
    }

    private function sqlInjectionCheck(): array
    {
        $rows = (new AnalysisRepository(':memory:'))->search("' OR 1=1 --");
        return [$rows === [], 'Rows returned from empty database: ' . count($rows), 'Use prepared statements for history queries.'];
    }

    private function commandExecutionCheck(): array
    {
        $matches = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(BASE_PATH . '/app'));
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $content = (string) file_get_contents($file->getPathname());
            if (preg_match('/\b(?:shell_exec|system|passthru|proc_open|popen)\s*\(/', $content)) {
                $matches[] = $file->getFilename();
            }
        }
        return [$matches === [], 'Shell execution call sites: ' . count($matches), 'Remove shell execution from all user-input paths.'];
    }

    private function csrfCheck(array $case): array
    {
        $response = $this->http('POST', $case['request']['path'], $case['request']['body']);
        $bodyMatch = str_contains(strtolower($response['body']), strtolower($case['expected']['body_contains']));
        $statusMatch = in_array($response['status'], $case['expected']['allowed_status_codes'], true);
        return [
            $bodyMatch && $statusMatch,
            "HTTP {$response['status']}; CSRF error present=" . ($bodyMatch ? 'true' : 'false'),
            'Reject every state-changing request without a valid session CSRF token.',
        ];
    }

    private function pathTraversalCheck(): array
    {
        $url = (new UrlValidator())->validate('https://example.com/../../etc/passwd', false);
        $passed = $url['host'] === 'example.com'
            && str_contains($url['path'], '../')
            && !str_contains((string) file_get_contents(BASE_PATH . '/app/Services/AnalysisOrchestrator.php'), 'file_get_contents($url');
        return [$passed, 'Traversal text remained URL evidence and no URL-derived file read exists.', 'Never map URL paths to local filesystem operations.'];
    }

    private function rateLimitCheck(): array
    {
        $directory = sys_get_temp_dir() . '/linkguard-security-rate-' . bin2hex(random_bytes(5));
        mkdir($directory, 0775, true);
        try {
            $limiter = new RateLimiter($directory, 2, 60);
            $actual = [$limiter->attempt('case'), $limiter->attempt('case'), $limiter->attempt('case')];
        } finally {
            foreach (glob($directory . '/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($directory);
        }
        return [$actual === [true, true, false], 'Sequence: ' . json_encode($actual), 'Enforce the configured attempt cap with atomic storage.'];
    }

    private function pdfCheck(): array
    {
        $report = [
            'case_id' => 'LG-SECURITY-PDF',
            'submitted_url' => 'https://example.com/?q=<script>alert(1)</script>',
            'url' => ['host' => 'example.com', 'scheme' => 'https'],
            'created_at' => date(DATE_ATOM),
            'risk' => ['score' => 0, 'level' => 'Low Risk', 'contributions' => []],
            'coverage' => ['status' => 'Demonstration', 'coverage_percent' => 50, 'page_content' => 'Not inspected'],
            'findings' => [],
            'explanation' => ['summary' => 'Incomplete assessment.', 'recommendations' => [], 'limitations' => []],
            'reputation' => ['source' => 'Mock', 'mock' => true, 'message' => 'Mock only.'],
            'disclaimer' => 'Not a safety guarantee.',
        ];
        $pdf = (new PdfReportService())->render($report);
        $passed = str_starts_with($pdf, '%PDF-') && !str_contains($pdf, '/JavaScript');
        return [$passed, 'PDF bytes=' . strlen($pdf) . '; JavaScript action=' . (str_contains($pdf, '/JavaScript') ? 'present' : 'absent'), 'Disable remote content, PHP, links, and JavaScript actions in PDF generation.'];
    }

    private function secretCheck(): array
    {
        $matches = [];
        foreach ([BASE_PATH . '/app', BASE_PATH . '/config', BASE_PATH . '/public'] as $directory) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));
            foreach ($iterator as $file) {
                if (!$file->isFile()) {
                    continue;
                }
                $content = (string) file_get_contents($file->getPathname());
                if (preg_match('/(?:sk-proj-[A-Za-z0-9_-]{12,}|AIza[0-9A-Za-z_-]{20,})/', $content)) {
                    $matches[] = $file->getPathname();
                }
            }
        }
        return [$matches === [], 'Recognizable embedded API keys: ' . count($matches), 'Remove credentials, rotate them, and load replacements from .env.'];
    }

    private function headerCheck(array $case): array
    {
        $response = $this->http('GET', '/');
        $missing = [];
        foreach ($case['expected']['headers'] as $header) {
            if (!array_key_exists(strtolower($header), $response['headers'])) {
                $missing[] = $header;
            }
        }
        return [$missing === [], 'HTTP ' . $response['status'] . '; missing headers: ' . ($missing ? implode(', ', $missing) : 'none'), 'Set all required security headers in the front controller.'];
    }

    private function sessionCheck(array $case): array
    {
        $response = $this->http('GET', '/');
        $cookie = implode('; ', $response['headers']['set-cookie'] ?? []);
        $missing = array_filter(
            $case['expected']['cookie_contains'],
            static fn (string $value): bool => stripos($cookie, $value) === false
        );
        return [$missing === [], 'Set-Cookie protections missing: ' . ($missing ? implode(', ', $missing) : 'none'), 'Use HttpOnly, SameSite, and Secure on HTTPS for the session cookie.'];
    }

    private function scoreCapCheck(): array
    {
        $result = (new RiskScoringAgent(['a' => 80, 'b' => 80]))->analyze([
            new AgentResult('Test', 'complete', [
                ['code' => 'a', 'title' => 'A'],
                ['code' => 'b', 'title' => 'B'],
            ]),
        ]);
        return [$result['score'] === 100, 'Calculated score=' . $result['score'], 'Clamp the deterministic score to 100.'];
    }

    private function mockUnknownCheck(): array
    {
        $result = (new MockReputationProvider())->check('https://unlisted.test/', 'unlisted.test');
        $passed = $result['status'] === 'unavailable' && $result['verdict'] === 'unknown';
        return [$passed, "status={$result['status']}; verdict={$result['verdict']}", 'Never fabricate a clean result for URLs absent from mock fixtures.'];
    }

    private function coverageCheck(): array
    {
        $result = (new ExplanationAgent())->explain(
            [],
            ['Reputation unavailable.'],
            ['score' => 0, 'level' => 'Low Risk'],
            ['status' => 'Limited']
        );
        $passed = str_contains($result['summary'], 'assessment is incomplete')
            && str_contains($result['summary'], 'Do not treat this as proof of safety');
        return [$passed, 'Summary: ' . $result['summary'], 'Describe evidence gaps prominently when reputation or content checks are unavailable.'];
    }

    private function sourceContains(string $path, string $needle, string $remediation): array
    {
        $content = (string) file_get_contents($path);
        $passed = str_contains($content, $needle);
        return [$passed, "Expected source invariant present=" . ($passed ? 'true' : 'false'), $remediation];
    }

    private function sandboxCheck(array $case, bool $authenticated): array
    {
        $headers = ['Content-Type: application/json', 'Accept: application/json'];
        if ($authenticated) {
            $token = (string) config('app.content_sandbox_token');
            if (strlen($token) < 24) {
                return [false, 'Sandbox token is not configured.', 'Run php scripts/setup-sandbox.php and restart the container.'];
            }
            $headers[] = 'Authorization: Bearer ' . $token;
        }
        $handle = curl_init((string) $case['request']['path']);
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($case['request']['body'], JSON_UNESCAPED_SLASHES),
            CURLOPT_HTTPHEADER => $headers,
        ]);
        $body = curl_exec($handle);
        if ($body === false) {
            $error = curl_error($handle);
            curl_close($handle);
            return [false, 'Sandbox request failed: ' . $error, 'Start the hardened content-sandbox container and rerun.'];
        }
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);
        $decoded = json_decode((string) $body, true);
        $statusMatch = in_array($status, $case['expected']['allowed_status_codes'], true);
        $bodyMatch = !isset($case['expected']['body_contains'])
            || str_contains((string) $body, (string) $case['expected']['body_contains']);
        $titleMatch = !isset($case['expected']['title'])
            || (($decoded['metadata']['title'] ?? null) === $case['expected']['title']);
        $passed = $statusMatch && $bodyMatch && $titleMatch;
        return [
            $passed,
            'HTTP ' . $status . '; expected status/body/metadata matched=' . ($passed ? 'true' : 'false'),
            'Keep sandbox authentication, independent SSRF checks, and bounded metadata extraction enabled.',
        ];
    }

    private function sandboxSourceCheck(array $case): array
    {
        $source = (string) file_get_contents(BASE_PATH . '/docker-compose.yml');
        $missing = array_filter(
            $case['expected']['source_contains_all'],
            static fn (string $needle): bool => !str_contains($source, $needle)
        );
        return [
            $missing === [],
            'Missing hardening declarations: ' . ($missing ? implode(', ', $missing) : 'none'),
            'Restore read-only rootfs, dropped capabilities, no-new-privileges, and loopback-only publishing.',
        ];
    }

    private function http(string $method, string $path, array $data = []): array
    {
        $handle = curl_init($this->baseUrl . $path);
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_POSTFIELDS => $method === 'POST' ? http_build_query($data) : null,
        ]);
        $raw = curl_exec($handle);
        if ($raw === false) {
            throw new \RuntimeException('Local HTTP request failed: ' . curl_error($handle));
        }
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $headerSize = (int) curl_getinfo($handle, CURLINFO_HEADER_SIZE);
        curl_close($handle);
        $headerText = substr($raw, 0, $headerSize);
        $headers = [];
        foreach (preg_split('/\r\n|\n|\r/', trim($headerText)) ?: [] as $line) {
            if (!str_contains($line, ':')) {
                continue;
            }
            [$name, $value] = array_map('trim', explode(':', $line, 2));
            $headers[strtolower($name)][] = $value;
        }
        return ['status' => $status, 'headers' => $headers, 'body' => substr($raw, $headerSize)];
    }
}
