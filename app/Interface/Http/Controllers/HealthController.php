<?php
declare(strict_types=1);

namespace App\Interface\Http\Controllers;

use App\Infrastructure\Cache\ApcuFileCache;
use App\Infrastructure\Database\Connection;
use App\Interface\Http\Request;

final class HealthController extends BaseController
{
    public function show(Request $request)
    {
        $dbOk = false;
        $dbMessage = 'ok';

        try {
            $stmt = Connection::get()->query('SELECT 1');
            $dbOk = (bool) $stmt->fetchColumn();
        } catch (\Throwable $e) {
            $dbMessage = $e->getMessage();
        }

        $cache = new ApcuFileCache(dirname(__DIR__, 4) . '/storage/cache');
        $probeKey = 'health_probe';
        $cache->set($probeKey, 'ok', 10);
        $cacheOk = $cache->get($probeKey) === 'ok';
        $cache->delete($probeKey);

        $status = ($dbOk && $cacheOk) ? 'ready' : 'degraded';

        return $this->ok([
            'status' => $status,
            'time' => gmdate('c'),
            'checks' => [
                'database' => [
                    'ok' => $dbOk,
                    'message' => $dbMessage,
                ],
                'cache' => [
                    'ok' => $cacheOk,
                    'driver' => function_exists('apcu_enabled') && apcu_enabled() ? 'apcu' : 'file',
                ],
            ],
        ]);
    }
}
