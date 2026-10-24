<?php

declare(strict_types=1);

namespace Vapehaus\Tests\Mocks;

use Vapehaus\Domain\Model\Order;
use Vapehaus\Domain\Repository\OrderRepositoryInterface;

final class InMemoryOrderRepository implements OrderRepositoryInterface
{
    /** @var array<string, Order> */
    private array $orders = [];

    public function save(Order $order): void
    {
        $this->orders[$order->getId()] = $order;
    }

    public function findById(string $id): ?Order
    {
        return $this->orders[$id] ?? null;
    }

    public function findByOrderNumber(string $orderNumber): ?Order
    {
        foreach ($this->orders as $order) {
            if ($order->getOrderNumber() === $orderNumber) {
                return $order;
            }
        }
        return null;
    }

    public function findByUserId(string $userId, int $limit = 20, int $offset = 0): array
    {
        $userOrders = array_filter($this->orders, fn ($o) => $o->getUserId() === $userId);
        return array_slice(array_values($userOrders), $offset, $limit);
    }

    public function findAll(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $filtered = array_values($this->orders);
        if (isset($filters['status'])) {
            $filtered = array_filter($filtered, fn ($o) => $o->getStatus()->getValue() === $filters['status']);
        }
        return array_slice(array_values($filtered), $offset, $limit);
    }

    public function countAll(array $filters = []): int
    {
        return count($this->findAll($filters, 10000, 0));
    }

    public function getMetrics(): array
    {
        $revenue = 0.0;
        $total = count($this->orders);
        $pending = 0;
        $delivered = 0;

        foreach ($this->orders as $order) {
            if ($order->getStatus()->getValue() !== 'cancelled') {
                $revenue += $order->getTotal()->toFloat();
            }
            if ($order->getStatus()->getValue() === 'pending') {
                $pending++;
            }
            if ($order->getStatus()->getValue() === 'delivered') {
                $delivered++;
            }
        }

        return [
            'total_revenue' => $revenue,
            'total_orders' => $total,
            'pending_orders' => $pending,
            'delivered_orders' => $delivered,
        ];
    }
}
