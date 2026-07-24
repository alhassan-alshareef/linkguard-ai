<?php

return [
    'name' => 'LinkGuard AI',
    'env' => env('APP_ENV', 'local'),
    'debug' => filter_var(env('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOL),
    'url' => env('APP_URL', 'http://127.0.0.1:8000'),
    'timezone' => env('APP_TIMEZONE', 'Asia/Riyadh'),
    'database' => env('DB_DATABASE', 'database/linkguard.sqlite'),
    'reputation_mode' => env('REPUTATION_MODE', 'mock'),
    'virustotal_key' => env('VIRUSTOTAL_API_KEY', ''),
    'reputation_timeout' => (int) env('REPUTATION_TIMEOUT', '5'),
    'content_sandbox_mode' => env('CONTENT_SANDBOX_MODE', 'disabled'),
    'content_sandbox_url' => env('CONTENT_SANDBOX_URL', 'http://127.0.0.1:8787'),
    'content_sandbox_token' => env('CONTENT_SANDBOX_TOKEN', ''),
    'content_sandbox_timeout' => (int) env('CONTENT_SANDBOX_TIMEOUT', '8'),
    'content_sandbox_max_response' => (int) env('CONTENT_SANDBOX_MAX_RESPONSE', '65536'),
    'rate_limit_max' => (int) env('RATE_LIMIT_MAX', '12'),
    'rate_limit_window' => (int) env('RATE_LIMIT_WINDOW', '60'),
];
