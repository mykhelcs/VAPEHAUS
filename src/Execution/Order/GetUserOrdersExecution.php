<?php

declare(strict_types=1);

namespace Vapehaus\Execution\Order;

use Vapehaus\Domain\Model\Order;
use Vapehaus\Domain\Repository\OrderRepositoryInterface;

final class GetUserOrdersExecution
{
    private OrderRepositoryInterface $orderRepository;

    public function __construct(OrderRepositoryInterface $orderRepository)
    {
        $this->orderRepository = $orderRepository;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function execute(string $userId, int $page = 1, int $limit = 20): array
    {
        $page = max(1, $page);
        $limit = min(100, max(1, $limit));
        $offset = ($page - 1) * $limit;

        $orders = $this->orderRepository->findByUserId($userId, $limit, $offset);
        return array_map(fn (Order $o) => $o->toArray(), $orders);
    }
}
