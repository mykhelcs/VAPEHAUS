<?php

declare(strict_types=1);

namespace Vapehaus\Execution\Admin;

use Vapehaus\Domain\Exception\EntityNotFoundException;
use Vapehaus\Domain\Exception\ValidationException;
use Vapehaus\Domain\Repository\OrderRepositoryInterface;
use Vapehaus\Domain\Repository\ProductRepositoryInterface;

final class AdminUpdateOrderStatusExecution
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
    public function execute(string $orderId, string $newStatus): array
    {
        $order = $this->orderRepository->findById($orderId);
        if ($order === null) {
            throw new EntityNotFoundException('Order', $orderId);
        }

        $newStatus = strtolower(trim($newStatus));
        if ($newStatus === '') {
            throw new ValidationException('Target order status is required.');
        }

        // If cancelling from admin, return stock
        if ($newStatus === 'cancelled' && $order->getStatus()->getValue() !== 'cancelled') {
            foreach ($order->getItems() as $item) {
                $variant = $this->productRepository->findVariantById($item->getVariantId());
                if ($variant !== null) {
                    $variant->incrementStock($item->getQuantity());
                    $this->productRepository->saveVariant($variant);
                }
            }
        }

        $order->transitionTo($newStatus);
        $this->orderRepository->save($order);

        return $order->toArray();
    }
}
