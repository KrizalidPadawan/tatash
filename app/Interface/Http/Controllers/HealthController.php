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
        $cacheMessage = 'ok';
        $cacheMeta = $cache->diagnostics();

        try {
            $cache->set($probeKey, 'ok', 10);
            $cacheOk = $cache->get($probeKey) === 'ok';
            $cache->delete($probeKey);
            if (!$cacheOk) {
                $cacheMessage = 'Cache probe read/write failed';
            }
        } catch (\Throwable $e) {
            $cacheOk = false;
            $cacheMessage = $e->getMessage();
        }

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
                    'driver' => $cacheMeta['apcu_enabled'] ? 'apcu' : 'file',
                    'message' => $cacheMessage,
                    'path' => $cacheMeta['path'],
                    'directory_exists' => $cacheMeta['directory_exists'],
                    'directory_writable' => $cacheMeta['directory_writable'],
                ],
            ],
        ]);
    }
}
