<?php

declare(strict_types=1);

namespace Vapehaus\Execution\Admin;

use Vapehaus\Domain\Model\Order;
use Vapehaus\Domain\Repository\OrderRepositoryInterface;

final class AdminListOrdersExecution
{
    private OrderRepositoryInterface $orderRepository;

    public function __construct(OrderRepositoryInterface $orderRepository)
    {
        $this->orderRepository = $orderRepository;
    }

    /**
     * @param array{status?: string, page?: int, limit?: int} $filters
     * @return array{orders: array<int, array<string, mixed>>, total: int, page: int, limit: int}
     */
    public function execute(array $filters = []): array
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $limit = min(100, max(1, (int) ($filters['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $orders = $this->orderRepository->findAll($filters, $limit, $offset);
        $total = $this->orderRepository->countAll($filters);

        return [
            'orders' => array_map(fn (Order $o) => $o->toArray(), $orders),
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
        ];
    }
}
