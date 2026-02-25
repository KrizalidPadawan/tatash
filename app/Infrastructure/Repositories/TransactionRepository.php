<?php
declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Application\DTO\TransactionCreateDTO;
use PDO;

final class TransactionRepository
{
    public function __construct(private readonly PDO $db) {}

    public function paginateByTenant(int $tenantId, int $page, int $perPage): array
    {
        $offset = ($page - 1) * $perPage;

        $sql = 'SELECT id, category_id, type, amount, description, transaction_date, created_at
                FROM transactions
                WHERE tenant_id = :tenant_id
                ORDER BY transaction_date DESC, id DESC
                LIMIT :limit OFFSET :offset';

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        $countStmt = $this->db->prepare('SELECT COUNT(*) FROM transactions WHERE tenant_id = :tenant_id');
        $countStmt->execute(['tenant_id' => $tenantId]);
        $total = (int) $countStmt->fetchColumn();

        return [
            'items' => $rows,
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => (int) ceil(max($total, 1) / $perPage),
            ],
        ];
    }

    public function create(TransactionCreateDTO $dto): int
    {
        $sql = 'INSERT INTO transactions
                (tenant_id, category_id, type, amount, description, transaction_date, created_by)
                VALUES (:tenant_id, :category_id, :type, :amount, :description, :transaction_date, :created_by)';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'tenant_id' => $dto->tenantId,
            'category_id' => $dto->categoryId,
            'type' => $dto->type,
            'amount' => $dto->amount,
            'description' => $dto->description,
            'transaction_date' => $dto->transactionDate,
            'created_by' => $dto->createdBy,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function monthlySummary(int $tenantId, string $month): array
    {
        $sql = 'SELECT type, SUM(amount) AS total
                FROM transactions
                WHERE tenant_id = :tenant_id
                AND transaction_date >= :start_date
                AND transaction_date < :end_date
                GROUP BY type';

        $start = $month . '-01';
        $end = date('Y-m-d', strtotime($start . ' +1 month'));

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'tenant_id' => $tenantId,
            'start_date' => $start,
            'end_date' => $end,
        ]);

        return $stmt->fetchAll();
    }

    public function dashboardSummary(int $tenantId): array
    {
        $sql = 'SELECT
                    SUM(CASE WHEN type = "income" THEN amount ELSE 0 END) AS total_income,
                    SUM(CASE WHEN type = "expense" THEN amount ELSE 0 END) AS total_expense,
                    COUNT(*) AS tx_count
                FROM transactions
                WHERE tenant_id = :tenant_id
                AND transaction_date >= (CURDATE() - INTERVAL 30 DAY)';

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['tenant_id' => $tenantId]);

        return $stmt->fetch() ?: [];
    }
}
