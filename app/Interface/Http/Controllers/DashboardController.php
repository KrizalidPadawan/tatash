<?php
declare(strict_types=1);

namespace App\Interface\Http\Controllers;

use App\Application\Services\TransactionService;
use App\Infrastructure\Cache\ApcuFileCache;
use App\Infrastructure\Database\Connection;
use App\Infrastructure\Repositories\TransactionRepository;
use App\Interface\Http\Request;

final class DashboardController extends BaseController
{
    private function service(): TransactionService
    {
        $db = Connection::get();
        return new TransactionService(
            new TransactionRepository($db),
            new ApcuFileCache(dirname(__DIR__, 4) . '/storage/cache'),
            $db
        );
    }

    public function summary(Request $request)
    {
        $tenantId = (int) $request->attributes['tenant_id'];
        return $this->ok($this->service()->dashboardSummary($tenantId));
    }
}
