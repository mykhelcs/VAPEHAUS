<?php

declare(strict_types=1);

namespace Vapehaus\Domain\Repository;

use Vapehaus\Domain\Model\InventoryAuditLog;

interface InventoryAuditRepositoryInterface
{
    public function log(InventoryAuditLog $auditLog): void;

    /**
     * @return array<int, InventoryAuditLog>
     */
    public function findByVariantId(string $variantId, int $limit = 50): array;

    /**
     * @return array<int, InventoryAuditLog>
     */
    public function findAll(int $limit = 50, int $offset = 0): array;

    public function count(): int;
}
