<?php
declare(strict_types=1);

namespace App\Application\DTO;

final class TransactionCreateDTO
{
    public function __construct(
        public readonly int $tenantId,
        public readonly int $categoryId,
        public readonly string $type,
        public readonly float $amount,
        public readonly string $description,
        public readonly string $transactionDate,
        public readonly int $createdBy,
    ) {}

    public static function fromArray(array $data, int $tenantId, int $createdBy): self
    {
        return new self(
            tenantId: $tenantId,
            categoryId: (int) ($data['category_id'] ?? 0),
            type: (string) ($data['type'] ?? 'expense'),
            amount: (float) ($data['amount'] ?? 0),
            description: trim((string) ($data['description'] ?? '')),
            transactionDate: (string) ($data['transaction_date'] ?? date('Y-m-d')),
            createdBy: $createdBy,
        );
    }
}
