<?php

declare(strict_types=1);

namespace Vapehaus\Domain\Repository;

use Vapehaus\Domain\Model\ReturnRequest;

interface ReturnRepositoryInterface
{
    public function save(ReturnRequest $return): void;

    public function findById(string $id): ?ReturnRequest;

    /**
     * @return array<int, ReturnRequest>
     */
    public function findByUserId(string $userId): array;

    /**
     * @param array{status?: string, order_id?: string} $filters
     * @return array<int, ReturnRequest>
     */
    public function findAll(array $filters = []): array;
}
