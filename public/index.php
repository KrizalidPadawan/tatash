<?php
declare(strict_types=1);

use App\Infrastructure\Logging\Logger;
use App\Interface\Http\Request;
use App\Interface\Http\Response;
use App\Interface\Http\Router;

require dirname(__DIR__) . '/bootstrap/autoload.php';

$appCfg = require dirname(__DIR__) . '/config/app.php';
$secCfg = require dirname(__DIR__) . '/config/security.php';

date_default_timezone_set($appCfg['timezone']);
session_start();
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$logger = new Logger($appCfg['storage_path'] . '/logs');
$start = microtime(true);

set_exception_handler(static function (Throwable $e) use ($logger, $appCfg): void {
    $logger->error('unhandled_exception', [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $appCfg['debug'] ? $e->getTraceAsString() : null,
    ]);

    Response::json(false, [], [['code' => 'server_error', 'message' => 'Unexpected server error']], 500)->send();
});

$path = rtrim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/');
$path = $path === '' ? '/' : $path;

if ($path === '/manifest.json') {
    header('Content-Type: application/manifest+json');
    readfile(__DIR__ . '/manifest.json');
    exit;
}
if ($path === '/service-worker.js') {
    header('Content-Type: application/javascript');
    readfile(__DIR__ . '/service-worker.js');
    exit;
}
if ($path === '/offline.html') {
    header('Content-Type: text/html; charset=utf-8');
    readfile(__DIR__ . '/offline.html');
    exit;
}
if ($path === '/' || $path === '/app') {
    header('Content-Type: text/html; charset=utf-8');
    readfile(__DIR__ . '/app.html');
    exit;
}

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'");

$router = new Router();
require dirname(__DIR__) . '/routes/api.php';

$request = Request::capture();
$response = $router->dispatch($request);
$response->send();

$elapsedMs = round((microtime(true) - $start) * 1000, 2);
$logger->info('request_handled', [
    'method' => $request->method,
    'path' => $request->path,
    'ip' => $request->ip,
    'status' => http_response_code(),
    'elapsed_ms' => $elapsedMs,
]);
