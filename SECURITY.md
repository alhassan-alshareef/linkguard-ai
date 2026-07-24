# LinkGuard AI security model

## Scope

This application analyzes URL text, configured reputation evidence, and optional bounded HTML metadata retrieved by a separate isolated service. It never executes submitted JavaScript, follows redirects, downloads files, or claims that a zero score proves safety.

The latest executable audit is generated from `security/golden_dataset.json`:

```powershell
php -S 127.0.0.1:8000 -t public public/router.php
php security/run_security_tests.php --base-url=http://127.0.0.1:8000
```

Reports are written to:

- `security/reports/latest.json`
- `security/reports/latest.html`

The audit score measures the controls represented by the current dataset. It is not a claim that the application or an analyzed URL is completely secure.

## Trust boundaries

- Submitted URL: untrusted text; parsed, canonicalized, validated, and escaped.
- DNS answers: untrusted; every returned address is checked against blocked ranges.
- Reputation data: untrusted provider output; it may add configured findings but cannot override scoring rules.
- Mock data: demonstration evidence only; an unmatched URL is `unknown`, never `clean`.
- SQLite: local case storage accessed only with prepared statements.
- PDF HTML: escaped output with remote resources and PHP disabled.
- Explanation layer: may restate findings but cannot add facts or change the deterministic score.
- Content sandbox: separate loopback-authenticated Node container; it receives a validated public URL and returns bounded metadata only.

## Implemented controls

- HTTP/HTTPS allowlist and credential rejection
- malformed hostname and noncanonical numeric-host rejection
- explicit IPv4 and IPv6 CIDR blocking, including documentation, mapped IPv4, private, loopback, link-local, multicast, and reserved ranges
- separate read-only, capability-free content container with loopback-only publishing and resource limits
- independent sandbox DNS resolution, address pinning, SSRF blocking, TLS verification, size/time limits, and redirect blocking
- CSRF protection for analysis and deletion
- file-backed rate limiting with locking
- HTML/PDF output escaping
- prepared SQLite statements
- restrictive browser security headers
- HttpOnly and SameSite session cookies; Secure when HTTPS is active
- ignored `.env`, source secret checks, and generic UI errors
- assessment-coverage reporting for unavailable reputation and uninspected content

## Accuracy boundary

The displayed number is an **observed indicator score**, not a probability that a site is malicious. Every case report also states which check categories were covered:

- `Limited`: neither live reputation nor HTML metadata was available.
- `Demonstration`: HTML metadata was inspected but reputation came from local mock fixtures.
- `Extended`: either live reputation or HTML metadata was available.
- `Comprehensive`: live reputation and HTML metadata were both available.

## Remaining limitations

- No live reputation is available unless VirusTotal mode and a valid key are configured.
- VirusTotal integration is not exercised by the local audit without a key.
- The sandbox performs static metadata extraction only; it does not observe JavaScript-rendered behavior.
- The brand list and lexical phishing rules are illustrative and can produce false positives or negatives.
- The app has no user accounts. Local case IDs are not an authorization boundary; authentication and per-user ownership are required before multi-user deployment.
- AI prompt-injection tests are skipped because no live LLM execution path exists.

## Reporting vulnerabilities

Do not include real API keys, malicious payloads, or sensitive URLs in a report. Provide an inert reproduction using `example.com` or another reserved fixture.
