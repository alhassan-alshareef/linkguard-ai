<?php

namespace Tests\Unit;

use LinkGuard\Support\Translator;
use PHPUnit\Framework\TestCase;

final class TranslatorTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SESSION['locale']);
    }

    public function testEnglishIsTheSafeDefault(): void
    {
        unset($_SESSION['locale']);
        self::assertSame('en', Translator::locale());
        self::assertSame('Inspect', Translator::choose('Inspect', 'فحص'));
    }

    public function testArabicLocaleAndReportTerms(): void
    {
        Translator::setLocale('ar');
        self::assertSame('ar', Translator::locale());
        self::assertTrue(Translator::isRtl());
        self::assertSame('مخاطر متوسطة', Translator::riskLevel('Moderate Risk'));
        self::assertSame('وكيل السمعة', Translator::agent('Reputation Agent'));
        self::assertSame(
            'اشتباه في انتحال علامة تجارية',
            Translator::findingTitle(['code' => 'brand_impersonation', 'title' => 'Possible brand impersonation'])
        );
    }

    public function testUnsupportedLocaleIsIgnored(): void
    {
        Translator::setLocale('fr');
        self::assertSame('en', Translator::locale());
    }
}
