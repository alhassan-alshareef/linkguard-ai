<?php

declare(strict_types=1);

define('BASE_PATH', __DIR__);

$vendor = BASE_PATH . '/vendor/autoload.php';
if (is_file($vendor)) {
    require $vendor;
} else {
    spl_autoload_register(static function (string $class): void {
        $prefix = 'LinkGuard\\';
        if (!str_starts_with($class, $prefix)) {
            return;
        }
        $path = BASE_PATH . '/app/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($path)) {
            require $path;
        }
    });
}

function env(string $key, ?string $default = null): ?string
{
    static $values;
    if ($values === null) {
        $values = [];
        $file = BASE_PATH . '/.env';
        if (is_file($file)) {
            foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                    continue;
                }
                [$name, $value] = array_map('trim', explode('=', $line, 2));
                $values[$name] = trim($value, "\"'");
            }
        }
    }
    return $_ENV[$key] ?? $_SERVER[$key] ?? $values[$key] ?? $default;
}

function config(?string $key = null, mixed $default = null): mixed
{
    static $config;
    if ($config === null) {
        $config = [
            'app' => require BASE_PATH . '/config/app.php',
            'risk' => require BASE_PATH . '/config/risk.php',
        ];
        date_default_timezone_set($config['app']['timezone']);
    }
    if ($key === null) {
        return $config;
    }
    $value = $config;
    foreach (explode('.', $key) as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }
    return $value;
}

function locale(): string
{
    return \LinkGuard\Support\Translator::locale();
}

function tr(string $english, string $arabic, array $replace = []): string
{
    return \LinkGuard\Support\Translator::choose($english, $arabic, $replace);
}

config();
