<?php

namespace Tests\Unit;

use LinkGuard\Support\Escaper;
use PHPUnit\Framework\TestCase;

final class EscaperTest extends TestCase
{
    public function testItEscapesHtmlAndAttributeCharacters(): void
    {
        $escaped = Escaper::html('"><script>alert("x")</script>');
        self::assertSame('&quot;&gt;&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;', $escaped);
    }
}
