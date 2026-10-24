<?php

declare(strict_types=1);

namespace Vapehaus\Execution\Catalog;

use Vapehaus\Domain\Model\Product;
use Vapehaus\Domain\Repository\ProductRepositoryInterface;

final class ListProductsExecution
{
    private ProductRepositoryInterface $productRepository;

    public function __construct(ProductRepositoryInterface $productRepository)
    {
        $this->productRepository = $productRepository;
    }

    /**
     * @param array{
     *     category_id?: string,
     *     brand?: string,
     *     flavor?: string,
     *     nicotine?: string,
     *     query?: string,
     *     in_stock?: bool,
     *     page?: int,
     *     limit?: int
     * } $filters
     * @return array{products: array<int, array<string, mixed>>, total: int, page: int, limit: int}
     */
    public function execute(array $filters = []): array
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $limit = min(100, max(1, (int) ($filters['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $criteria = [
            'category_id' => $filters['category_id'] ?? null,
            'brand' => $filters['brand'] ?? null,
            'flavor' => $filters['flavor'] ?? null,
            'nicotine' => $filters['nicotine'] ?? null,
            'query' => $filters['query'] ?? null,
            'in_stock' => isset($filters['in_stock']) ? (bool) $filters['in_stock'] : null,
        ];

        $criteria = array_filter($criteria, fn ($val) => $val !== null && $val !== '');

        $products = $this->productRepository->search($criteria, $limit, $offset);
        $total = $this->productRepository->countSearch($criteria);

        return [
            'products' => array_map(fn (Product $p) => $p->toArray(), $products),
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
        ];
    }
}
