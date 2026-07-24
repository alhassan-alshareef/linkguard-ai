<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use LinkGuard\Controllers\AppController;
use LinkGuard\Services\ServiceFactory;

$sessionPath = BASE_PATH . '/storage/sessions';
if (!is_dir($sessionPath)) {
    mkdir($sessionPath, 0775, true);
}
ini_set('session.save_path', $sessionPath);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', BASE_PATH . '/storage/logs/php-error.log');
error_reporting(E_ALL);

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_set_cookie_params([
    'httponly' => true,
    'secure' => $isHttps,
    'samesite' => 'Lax',
    'path' => '/',
]);
session_name('linkguard_session');
session_start();

if (isset($_GET['lang']) && is_string($_GET['lang'])) {
    \LinkGuard\Support\Translator::setLocale($_GET['lang']);
    $query = $_GET;
    unset($query['lang']);
    $target = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    if ($query !== []) {
        $target .= '?' . http_build_query($query);
    }
    header('Location: ' . $target, true, 303);
    exit;
}

header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; base-uri 'none'; form-action 'self'; frame-ancestors 'none'; object-src 'none'");
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header('Cross-Origin-Opener-Policy: same-origin');

$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$controller = new AppController(ServiceFactory::repository());

if ($method === 'GET' && $path === '/') {
    $controller->home();
} elseif ($method === 'POST' && $path === '/analyze') {
    $controller->analyze();
} elseif ($method === 'GET' && $path === '/history') {
    $controller->history();
} elseif ($method === 'GET' && $path === '/about') {
    $controller->about();
} elseif ($method === 'GET' && preg_match('#^/cases/([A-Z0-9-]+)$#', $path, $matches)) {
    $controller->show($matches[1]);
} elseif ($method === 'GET' && preg_match('#^/cases/([A-Z0-9-]+)/pdf$#', $path, $matches)) {
    $controller->pdf($matches[1]);
} elseif ($method === 'POST' && preg_match('#^/history/([A-Z0-9-]+)/delete$#', $path, $matches)) {
    $controller->delete($matches[1]);
} else {
    $controller->notFound();
}
