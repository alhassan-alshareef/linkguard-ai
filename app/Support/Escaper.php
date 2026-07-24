<?php

namespace LinkGuard\Support;

final class Escaper
{
    public static function html(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
