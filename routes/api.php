<?php
declare(strict_types=1);

use App\Infrastructure\Cache\ApcuFileCache;
use App\Infrastructure\Database\Connection;
use App\Infrastructure\Repositories\UserRepository;
use App\Infrastructure\Security\JwtHandler;
use App\Interface\Http\Controllers\AuthController;
use App\Interface\Http\Controllers\DashboardController;
use App\Interface\Http\Controllers\HealthController;
use App\Interface\Http\Controllers\ReportController;
use App\Interface\Http\Controllers\TransactionController;
use App\Interface\Http\Router;
use App\Middleware\AuthMiddleware;
use App\Middleware\RbacMiddleware;
use App\Middleware\RateLimitMiddleware;

/** @var Router $router */
$appCfg = require dirname(__DIR__) . '/config/app.php';
$secCfg = require dirname(__DIR__) . '/config/security.php';

$jwt = new JwtHandler($secCfg['jwt_secret'], $secCfg['jwt_issuer']);
$cache = new ApcuFileCache($appCfg['storage_path'] . '/cache');
/** @var callable(): UserRepository $userRepoFactory */
$userRepoFactory = static fn (): UserRepository => new UserRepository(Connection::get());

$rate = new RateLimitMiddleware($cache, $secCfg['rate_limit_per_minute']);
$auth = new AuthMiddleware($jwt);

$prefix = $appCfg['api_prefix'];

$router->add('GET', $prefix . '/health', [HealthController::class, 'show'], [$rate]);
$router->add('POST', $prefix . '/auth/login', [AuthController::class, 'login'], [$rate]);
$router->add('POST', $prefix . '/auth/refresh', [AuthController::class, 'refresh'], [$rate]);

$router->add('GET', $prefix . '/transactions', [TransactionController::class, 'index'], [
    $rate,
    $auth,
    new RbacMiddleware($userRepoFactory, 'transactions.read'),
]);

$router->add('POST', $prefix . '/transactions', [TransactionController::class, 'store'], [
    $rate,
    $auth,
    new RbacMiddleware($userRepoFactory, 'transactions.write'),
]);

$router->add('GET', $prefix . '/reports/monthly', [ReportController::class, 'monthly'], [
    $rate,
    $auth,
    new RbacMiddleware($userRepoFactory, 'reports.read'),
]);

$router->add('GET', $prefix . '/dashboard/summary', [DashboardController::class, 'summary'], [
    $rate,
    $auth,
    new RbacMiddleware($userRepoFactory, 'dashboard.read'),
]);
