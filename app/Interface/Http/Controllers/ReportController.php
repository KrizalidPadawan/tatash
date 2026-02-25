<?php
declare(strict_types=1);

namespace App\Interface\Http\Controllers;

use App\Application\Services\TransactionService;
use App\Infrastructure\Cache\ApcuFileCache;
use App\Infrastructure\Database\Connection;
use App\Infrastructure\Repositories\TransactionRepository;
use App\Interface\Http\Request;

final class ReportController extends BaseController
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

    public function monthly(Request $request)
    {
        $tenantId = (int) $request->attributes['tenant_id'];
        $month = (string) ($request->query['month'] ?? date('Y-m'));

        return $this->ok(['month' => $month, 'summary' => $this->service()->monthlyReport($tenantId, $month)]);
    }
}
