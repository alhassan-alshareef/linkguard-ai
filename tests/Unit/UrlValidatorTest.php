<?php

namespace Tests\Unit;

use InvalidArgumentException;
use LinkGuard\Support\UrlValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UrlValidatorTest extends TestCase
{
    public function testItNormalizesAValidPublicUrl(): void
    {
        $result = (new UrlValidator())->validate(' HTTPS://Example.COM/path?q=1 ', false);
        self::assertSame('https://example.com/path?q=1', $result['url']);
        self::assertSame('example.com', $result['host']);
    }

    #[DataProvider('blockedUrls')]
    public function testItRejectsUnsafeOrInvalidInputs(string $url): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new UrlValidator())->validate($url, false);
    }

    public static function blockedUrls(): array
    {
        return [
            'empty' => [''],
            'script protocol' => ['javascript:alert(1)'],
            'file protocol' => ['file:///etc/passwd'],
            'localhost' => ['http://localhost/admin'],
            'loopback IPv4' => ['http://127.0.0.1/'],
            'private IPv4' => ['http://10.0.0.4/'],
            'link-local' => ['http://169.254.169.254/latest/meta-data'],
            'IPv6 loopback' => ['http://[::1]/'],
            'decimal loopback' => ['http://2130706433/'],
            'hex loopback' => ['http://0x7f000001/'],
            'octal loopback' => ['http://0177.0.0.1/'],
            'short dotted loopback' => ['http://127.1/'],
            'credentials' => ['https://user:pass@example.com/'],
            'trailing hyphen host' => ['https://offers-eshop-/zain.netlify.app'],
            'single-label public host' => ['https://not-a-public-domain/path'],
            'XSS-shaped invalid URL' => ['"><script>alert(1)</script>'],
        ];
    }
}
