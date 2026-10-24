<?php

declare(strict_types=1);

namespace Vapehaus\Execution\Order;

use Vapehaus\Domain\Exception\AuthenticationException;
use Vapehaus\Domain\Exception\EntityNotFoundException;
use Vapehaus\Domain\Repository\OrderRepositoryInterface;

final class GetOrderDetailExecution
{
    private OrderRepositoryInterface $orderRepository;

    public function __construct(OrderRepositoryInterface $orderRepository)
    {
        $this->orderRepository = $orderRepository;
    }

    /**
     * @return array<string, mixed>
     */
    public function execute(string $orderId, ?string $userId = null, bool $isAdmin = false): array
    {
        $order = $this->orderRepository->findById($orderId);
        if ($order === null) {
            $order = $this->orderRepository->findByOrderNumber($orderId);
        }

        if ($order === null) {
            throw new EntityNotFoundException('Order', $orderId);
        }

        // Authorization check: User can only view their own orders unless admin
        if (!$isAdmin && $userId !== null && $order->getUserId() !== $userId) {
            throw new AuthenticationException('You are not authorized to view this order.');
        }

        return $order->toArray();
    }
}
