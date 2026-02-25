<?php
declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTO\TransactionCreateDTO;
use App\Infrastructure\Cache\CacheInterface;
use App\Infrastructure\Repositories\TransactionRepository;
use PDO;

final class TransactionService
{
    public function __construct(
        private readonly TransactionRepository $repo,
        private readonly CacheInterface $cache,
        private readonly PDO $db
    ) {}

    public function list(int $tenantId, int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = min(max(1, $perPage), 100);

        return $this->repo->paginateByTenant($tenantId, $page, $perPage);
    }

    public function create(TransactionCreateDTO $dto): int
    {
        if (!in_array($dto->type, ['income', 'expense'], true)) {
            throw new \InvalidArgumentException('Invalid transaction type');
        }

        if ($dto->amount <= 0) {
            throw new \InvalidArgumentException('Amount must be greater than zero');
        }

        $id = $this->repo->create($dto);
        $this->cache->delete('dashboard:' . $dto->tenantId);

        $log = $this->db->prepare('INSERT INTO audit_logs (tenant_id, user_id, action, entity, entity_id, ip_address, metadata)
                  VALUES (:tenant_id, :user_id, :action, :entity, :entity_id, :ip_address, :metadata)');
        $log->execute([
            'tenant_id' => $dto->tenantId,
            'user_id' => $dto->createdBy,
            'action' => 'transaction.created',
            'entity' => 'transactions',
            'entity_id' => $id,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            'metadata' => json_encode(['amount' => $dto->amount, 'type' => $dto->type], JSON_THROW_ON_ERROR),
        ]);

        return $id;
    }

    public function monthlyReport(int $tenantId, string $month): array
    {
        return $this->repo->monthlySummary($tenantId, $month);
    }

    public function dashboardSummary(int $tenantId): array
    {
        $cacheKey = 'dashboard:' . $tenantId;
        $cached = $this->cache->get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $result = $this->repo->dashboardSummary($tenantId);
        $this->cache->set($cacheKey, $result, 60);

        return $result;
    }
}
