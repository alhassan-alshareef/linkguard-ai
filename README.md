# LinkGuard AI

[![Tests](https://github.com/alhassan-alshareef/linkguard-ai/actions/workflows/tests.yml/badge.svg)](https://github.com/alhassan-alshareef/linkguard-ai/actions/workflows/tests.yml)

LinkGuard AI is a bilingual web application that helps people take a closer look at suspicious links before opening them.

Instead of returning a mysterious percentage, it shows the warning signs it found, explains how they affected the score, and tells the user which checks were actually available. The goal is not to declare a link “safe.” It is to make the available evidence easier to understand.

**Live demo:** [linkguard-ai-demo.onrender.com](https://linkguard-ai-demo.onrender.com)

> A score of 0 does not guarantee that a link is safe. It only means that the checks available at that moment did not find a weighted risk indicator.

## Why I built it

Suspicious links often look convincing at first glance. A changed letter, a misleading subdomain, or an urgent word such as “verify” can be easy to miss.

LinkGuard breaks the inspection into small, explainable checks. Each check returns structured evidence, and a deterministic scoring service combines that evidence into a final report. This keeps the result predictable: the same findings always produce the same score.

## What it checks

- Whether the URL is valid and uses HTTP or HTTPS
- Local, private, reserved, and special-purpose network addresses
- Direct IP hosts, Punycode, URL shorteners, unusual ports, and excessive subdomains
- Long or misleading URL structures
- Phishing-related wording and possible brand impersonation
- Reputation data from a replaceable provider
- Optional page metadata such as titles, forms, password fields, and meta redirects
- The coverage and limitations of the analysis

The application can also save a case locally and export the report as an Arabic or English PDF.

## How the analysis works

1. **URL validation** normalizes the address and rejects unsafe or malformed targets.
2. **Structure analysis** looks for suspicious properties in the URL itself.
3. **Reputation analysis** checks either the local demo dataset or VirusTotal.
4. **Phishing analysis** looks for sensitive wording and brand-like hostnames.
5. **Content analysis** optionally requests limited metadata from an isolated Node.js service.
6. **Risk scoring** adds configured weights, removes duplicate findings, and caps the total at 100.
7. **Explanation** turns the existing evidence into a readable summary without changing the score.

The score represents observed indicators, not the probability that a website is malicious.

## Technology used

| Technology | Role in the project |
|---|---|
| PHP 8.2 | Application logic, routing, validation, services, and views |
| HTML, CSS, and vanilla JavaScript | Responsive bilingual interface |
| SQLite and PDO | Local case storage with prepared statements |
| Dompdf | PDF report generation |
| Ar-PHP | Arabic text shaping in PDF reports |
| Node.js | Metadata-only page inspection service |
| Docker Compose | Isolation and resource limits for content inspection |
| VirusTotal API | Optional live URL reputation provider |
| PHPUnit | Unit, feature, and security tests |
| GitHub Actions | Automated PHP and Node.js test runs |
| Render | Docker-based deployment of the public demo |

## Run it locally

You need PHP 8.2 or newer, Composer 2, and the `curl`, `dom`, `mbstring`, and `pdo_sqlite` PHP extensions.

```bash
cp .env.example .env
composer install
php -S 127.0.0.1:8000 -t public public/router.php
```

Then open [http://127.0.0.1:8000](http://127.0.0.1:8000).

On Windows PowerShell, copy the environment file with:

```powershell
Copy-Item .env.example .env
```

The database is created automatically the first time the application starts.

## Optional isolated page inspection

The main PHP application does not fetch submitted websites directly. When page inspection is enabled, it sends a validated public URL to a separate Node.js container.

```bash
php scripts/setup-sandbox.php
docker compose up -d --build
```

Then set this value in `.env`:

```dotenv
CONTENT_SANDBOX_MODE=http
```

The sandbox does not execute page JavaScript, follow redirects, or download files. It returns bounded metadata only.

## Reputation modes

### Demo mode

```dotenv
REPUTATION_MODE=mock
```

This uses a small local dataset. It is useful for demonstrations and automated tests, but it is not live internet reputation.

### VirusTotal mode

```dotenv
REPUTATION_MODE=virustotal
VIRUSTOTAL_API_KEY=your_key_here
```

Keep the API key in `.env` or in your hosting provider’s secret settings. Never commit it to GitHub.

If reputation data is unavailable, LinkGuard marks that check as unavailable. Missing data is never treated as proof that a link is safe.

## Safe demo cases

These addresses are intended for testing:

| Scenario | URL |
|---|---|
| Few observed indicators | `https://example.com/` |
| Suspicious mock reputation | `https://suspicious-demo.example.com/verify-account` |
| Brand imitation pattern | `https://paypal-secure.example.com/verify-account` |
| High-risk mock case | `https://known-risk.example.com/login` |
| Invalid URL | `https://webook@@.com/w` |

The mock domains are test fixtures. Their results are not claims about real websites.

## Scoring

All weights are defined in [`config/risk.php`](config/risk.php). A few examples:

| Indicator | Points |
|---|---:|
| Direct IP host | 20 |
| Punycode hostname | 20 |
| Possible brand impersonation | 25 |
| Malicious reputation result | 40 |
| Suspicious reputation result | 25 |
| Phishing-related wording | 10 |
| Plain HTTP | 5 |
| Form posting to another domain | 20 |

Risk levels:

- 0–24: Low observed risk
- 25–49: Moderate risk
- 50–74: High risk
- 75–100: Critical risk

## Tests

Run the PHP test suite:

```bash
composer test
```

Run the sandbox tests:

```bash
cd sandbox
npm test
```

Run the executable security dataset while the local application is running:

```bash
php security/run_security_tests.php --base-url=http://127.0.0.1:8000
```

The test coverage includes URL normalization, SSRF protections, XSS escaping, CSRF, prepared SQL statements, rate limiting, deterministic scoring, PDF generation, metadata limits, and verification that untrusted page scripts are not executed.

## Project structure

```text
app/
  Controllers/        Request handling
  Models/             SQLite persistence
  Services/
    Agents/           Analysis and scoring agents
    Reputation/       Reputation provider adapters
    Sandbox/          Isolated content-service adapter
  Support/            Validation, security, translation, and rate limits
  Views/              HTML pages and PDF template
config/               Application and risk settings
database/             SQLite schema
public/               Front controller and frontend assets
sandbox/              Isolated Node.js metadata service
security/             Executable security dataset
tests/                PHPUnit tests
```

## Security approach

The project includes:

- Strict HTTP/HTTPS parsing and embedded-credential rejection
- Blocking for localhost, private, reserved, link-local, and special IP ranges
- DNS-result validation before analysis
- A separate read-only content container with restricted permissions and resources
- Redirect, script execution, download, response-size, and timeout controls
- Output escaping for HTML and PDF
- CSRF protection and file-backed rate limiting
- Prepared SQLite statements
- Secure browser headers and session-cookie settings
- Ignored environment secrets and generic production error messages

More detail is available in [`SECURITY.md`](SECURITY.md).

## Current limitations

LinkGuard is a working prototype, not a replacement for a professional threat-intelligence platform.

- The public demo uses mock reputation data.
- Domain age, registration ownership, and certificate history are not checked.
- The isolated inspector does not observe JavaScript-rendered behavior.
- The brand and keyword rules are intentionally small and can produce false positives or false negatives.
- VirusTotal might not have an existing report for every URL.
- SQLite is suitable for this demo, but a multi-user production service would need authentication, case ownership, and a more durable database.

The important design rule is simple: unavailable evidence must remain visible as unavailable. It must never silently become a “safe” result.
