<?php

declare(strict_types=1);

namespace Vapehaus\Execution\Admin;

use Vapehaus\Domain\Repository\KycRepositoryInterface;
use Vapehaus\Domain\Repository\OrderRepositoryInterface;
use Vapehaus\Domain\Repository\ProductRepositoryInterface;
use Vapehaus\Domain\Repository\UserRepositoryInterface;

final class AdminGetDashboardExecution
{
    private OrderRepositoryInterface $orderRepository;
    private UserRepositoryInterface $userRepository;
    private ProductRepositoryInterface $productRepository;
    private ?KycRepositoryInterface $kycRepository;

    public function __construct(
        OrderRepositoryInterface $orderRepository,
        UserRepositoryInterface $userRepository,
        ProductRepositoryInterface $productRepository,
        ?KycRepositoryInterface $kycRepository = null
    ) {
        $this->orderRepository = $orderRepository;
        $this->userRepository = $userRepository;
        $this->productRepository = $productRepository;
        $this->kycRepository = $kycRepository;
    }

    /**
     * @return array<string, mixed>
     */
    public function execute(): array
    {
        $metrics = $this->orderRepository->getMetrics();
        $totalCustomers = $this->userRepository->count();
        $totalProducts = $this->productRepository->countSearch([]);

        $pendingKyc = 0;
        if ($this->kycRepository !== null) {
            $kycList = $this->kycRepository->findByStatus('pending');
            $pendingKyc = count($kycList);
        }

        // Count low stock skus (< 10 units)
        $lowStockCount = 0;
        $products = $this->productRepository->search([], 100, 0);
        foreach ($products as $p) {
            foreach ($p->getVariants() as $v) {
                if ($v->getStockQuantity() < 10) {
                    $lowStockCount++;
                }
            }
        }

        return [
            'total_revenue' => $metrics['total_revenue'],
            'total_orders' => $metrics['total_orders'],
            'pending_orders' => $metrics['pending_orders'],
            'delivered_orders' => $metrics['delivered_orders'],
            'total_customers' => $totalCustomers,
            'total_products' => $totalProducts,
            'active_products' => $totalProducts,
            'low_stock_skus' => $lowStockCount,
            'pending_kyc' => $pendingKyc,
        ];
    }
}
