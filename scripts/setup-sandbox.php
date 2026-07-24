<?php

declare(strict_types=1);

$basePath = dirname(__DIR__);
$envPath = $basePath . DIRECTORY_SEPARATOR . '.env';
$examplePath = $basePath . DIRECTORY_SEPARATOR . '.env.example';

if (!is_file($envPath)) {
    if (!copy($examplePath, $envPath)) {
        fwrite(STDERR, "Could not create .env.\n");
        exit(1);
    }
}

$contents = (string) file_get_contents($envPath);
$values = [
    'CONTENT_SANDBOX_MODE' => 'enabled',
    'CONTENT_SANDBOX_URL' => 'http://127.0.0.1:8787',
    'CONTENT_SANDBOX_TOKEN' => bin2hex(random_bytes(32)),
    'CONTENT_SANDBOX_TIMEOUT' => '8',
    'CONTENT_SANDBOX_MAX_RESPONSE' => '65536',
];

foreach ($values as $key => $value) {
    $line = $key . '=' . $value;
    $pattern = '/^' . preg_quote($key, '/') . '=.*$/m';
    if (preg_match($pattern, $contents) === 1) {
        $contents = (string) preg_replace($pattern, $line, $contents);
    } else {
        $contents = rtrim($contents) . PHP_EOL . $line . PHP_EOL;
    }
}

if (file_put_contents($envPath, $contents, LOCK_EX) === false) {
    fwrite(STDERR, "Could not update .env.\n");
    exit(1);
}

fwrite(STDOUT, "Content sandbox configuration enabled. The shared token was stored without printing it.\n");
