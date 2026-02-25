<?php
declare(strict_types=1);

namespace App\Domain;

final class Transaction
{
    public function __construct(
        public readonly int $id,
        public readonly int $tenantId,
        public readonly int $categoryId,
        public readonly string $type,
        public readonly float $amount,
        public readonly string $description,
        public readonly string $transactionDate,
    ) {}
}
