<?php

namespace Tests\Feature;

use LinkGuard\Services\PdfReportService;
use PHPUnit\Framework\TestCase;

final class PdfReportServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SESSION['locale']);
    }

    public function testItGeneratesAPdfWithoutMakingTheUrlClickable(): void
    {
        if (!class_exists(\Dompdf\Dompdf::class)) {
            self::markTestSkipped('Dompdf is not installed.');
        }
        $report = [
            'case_id' => 'LG-TEST-PDF001',
            'submitted_url' => 'https://example.com/?x=<script>alert(1)</script>',
            'url' => ['host' => 'example.com', 'scheme' => 'https'],
            'created_at' => date(DATE_ATOM),
            'risk' => ['score' => 10, 'level' => 'Low Risk', 'contributions' => []],
            'findings' => [],
            'explanation' => [
                'summary' => 'Further verification is recommended.',
                'recommendations' => ['Verify independently.'],
                'limitations' => ['No content was fetched.'],
            ],
            'reputation' => ['source' => 'Mock', 'mock' => true, 'message' => 'Mock data.'],
            'disclaimer' => 'This is not a guarantee.',
        ];
        $pdf = (new PdfReportService())->render($report);
        self::assertStringStartsWith('%PDF-', $pdf);
        self::assertGreaterThan(1000, strlen($pdf));
    }

    public function testArabicLocaleProducesAnRtlArabicPdfTemplate(): void
    {
        $_SESSION['locale'] = 'ar';
        $html = (new PdfReportService())->html($this->sampleReport());

        self::assertStringContainsString('lang="ar" dir="rtl"', $html);
        self::assertMatchesRegularExpression('/[\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]/u', $html);
        self::assertStringNotContainsString('Risk assessment', $html);
    }

    private function sampleReport(): array
    {
        return [
            'case_id' => 'LG-TEST-ARABIC',
            'submitted_url' => 'https://weboak.com/',
            'url' => ['host' => 'weboak.com', 'scheme' => 'https'],
            'created_at' => date(DATE_ATOM),
            'risk' => [
                'score' => 25,
                'level' => 'Moderate Risk',
                'contributions' => [['code' => 'brand_impersonation', 'points' => 25]],
            ],
            'coverage' => ['status' => 'Limited', 'coverage_percent' => 50, 'page_title' => 'Not inspected', 'page_content' => 'Not inspected'],
            'findings' => [[
                'code' => 'brand_impersonation',
                'title' => 'Possible brand impersonation',
                'explanation' => 'Lookalike domain.',
            ]],
            'explanation' => [
                'summary' => 'Some warning indicators were observed.',
                'recommendations' => ['Confirm the sender and destination before opening the link.'],
                'limitations' => ['Reputation data is unavailable; this must not be interpreted as a clean result.'],
            ],
            'reputation' => ['source' => 'Local demonstration dataset', 'mock' => true, 'message' => 'Mock data.'],
            'disclaimer' => 'This is not a guarantee.',
        ];
    }
}
