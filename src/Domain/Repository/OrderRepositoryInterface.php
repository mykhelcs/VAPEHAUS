<?php

declare(strict_types=1);

namespace Vapehaus\Domain\Repository;

use Vapehaus\Domain\Model\Order;

interface OrderRepositoryInterface
{
    public function save(Order $order): void;

    public function findById(string $id): ?Order;

    public function findByOrderNumber(string $orderNumber): ?Order;

    /**
     * @return array<int, Order>
     */
    public function findByUserId(string $userId, int $limit = 20, int $offset = 0): array;

    /**
     * @param array{status?: string, user_id?: string} $filters
     * @return array<int, Order>
     */
    public function findAll(array $filters = [], int $limit = 50, int $offset = 0): array;

    /**
     * @param array{status?: string, user_id?: string} $filters
     */
    public function countAll(array $filters = []): int;

    /**
     * @return array{total_revenue: float, total_orders: int, pending_orders: int, delivered_orders: int}
     */
    public function getMetrics(): array;
}
