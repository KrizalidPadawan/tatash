<?php
declare(strict_types=1);

namespace App\Interface\Http\Controllers;

use App\Application\DTO\TransactionCreateDTO;
use App\Application\Services\TransactionService;
use App\Infrastructure\Cache\ApcuFileCache;
use App\Infrastructure\Database\Connection;
use App\Infrastructure\Repositories\TransactionRepository;
use App\Interface\Http\Request;

final class TransactionController extends BaseController
{
    private function service(): TransactionService
    {
        $db = Connection::get();
        $cache = new ApcuFileCache(dirname(__DIR__, 4) . '/storage/cache');

        return new TransactionService(new TransactionRepository($db), $cache, $db);
    }

    public function index(Request $request)
    {
        $tenantId = (int) $request->attributes['tenant_id'];
        $page = (int) ($request->query['page'] ?? 1);
        $perPage = (int) ($request->query['per_page'] ?? 20);

        return $this->ok($this->service()->list($tenantId, $page, $perPage));
    }

    public function store(Request $request)
    {
        $auth = $request->attributes['auth'];
        $dto = TransactionCreateDTO::fromArray(
            $request->body,
            (int) $auth['tenant_id'],
            (int) $auth['sub']
        );

        try {
            $id = $this->service()->create($dto);
        } catch (\Throwable $e) {
            return $this->fail('validation_error', $e->getMessage(), 422);
        }

        return $this->ok(['id' => $id], 201);
    }
}
