<?php

declare(strict_types=1);

namespace Vapehaus\Execution\Order;

use Vapehaus\Domain\Exception\AuthenticationException;
use Vapehaus\Domain\Exception\EntityNotFoundException;
use Vapehaus\Domain\Exception\InvalidOrderStateException;
use Vapehaus\Domain\Repository\OrderRepositoryInterface;
use Vapehaus\Domain\Repository\ProductRepositoryInterface;

final class CancelOrderExecution
{
    private OrderRepositoryInterface $orderRepository;
    private ProductRepositoryInterface $productRepository;

    public function __construct(
        OrderRepositoryInterface $orderRepository,
        ProductRepositoryInterface $productRepository
    ) {
        $this->orderRepository = $orderRepository;
        $this->productRepository = $productRepository;
    }

    /**
     * @return array<string, mixed>
     */
    public function execute(string $orderId, string $userId, bool $isAdmin = false): array
    {
        $order = $this->orderRepository->findById($orderId);
        if ($order === null) {
            throw new EntityNotFoundException('Order', $orderId);
        }

        if (!$isAdmin && $order->getUserId() !== $userId) {
            throw new AuthenticationException('You are not authorized to cancel this order.');
        }

        if (!$order->canCancel()) {
            throw new InvalidOrderStateException($order->getStatus()->getValue(), 'cancelled');
        }

        // Restore stock for all items
        foreach ($order->getItems() as $item) {
            $variant = $this->productRepository->findVariantById($item->getVariantId());
            if ($variant !== null) {
                $variant->incrementStock($item->getQuantity());
                $this->productRepository->saveVariant($variant);
            }
        }

        $order->cancel();
        $this->orderRepository->save($order);

        return $order->toArray();
    }
}
