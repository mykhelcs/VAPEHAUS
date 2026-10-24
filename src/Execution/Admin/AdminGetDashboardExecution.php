<?php

declare(strict_types=1);

namespace Vapehaus\Execution\Admin;

use Vapehaus\Domain\Repository\OrderRepositoryInterface;
use Vapehaus\Domain\Repository\ProductRepositoryInterface;
use Vapehaus\Domain\Repository\UserRepositoryInterface;

final class AdminGetDashboardExecution
{
    private OrderRepositoryInterface $orderRepository;
    private UserRepositoryInterface $userRepository;
    private ProductRepositoryInterface $productRepository;

    public function __construct(
        OrderRepositoryInterface $orderRepository,
        UserRepositoryInterface $userRepository,
        ProductRepositoryInterface $productRepository
    ) {
        $this->orderRepository = $orderRepository;
        $this->userRepository = $userRepository;
        $this->productRepository = $productRepository;
    }

    /**
     * @return array<string, mixed>
     */
    public function execute(): array
    {
        $metrics = $this->orderRepository->getMetrics();
        $totalCustomers = $this->userRepository->count();
        $totalProducts = $this->productRepository->countSearch([]);

        return [
            'total_revenue' => $metrics['total_revenue'],
            'total_orders' => $metrics['total_orders'],
            'pending_orders' => $metrics['pending_orders'],
            'delivered_orders' => $metrics['delivered_orders'],
            'total_customers' => $totalCustomers,
            'total_products' => $totalProducts,
        ];
    }
}
