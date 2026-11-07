<?php

declare(strict_types=1);

namespace Vapehaus\Execution\Catalog;

use Vapehaus\Domain\Model\Product;
use Vapehaus\Domain\Repository\ProductRepositoryInterface;
use Vapehaus\Infrastructure\Cache\CacheInterface;

final class ListProductsExecution
{
    private ProductRepositoryInterface $productRepository;
    private ?CacheInterface $cache;

    public function __construct(ProductRepositoryInterface $productRepository, ?CacheInterface $cache = null)
    {
        $this->productRepository = $productRepository;
        $this->cache = $cache;
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

        $category = $filters['category_id'] ?? $filters['category'] ?? null;
        if ($category === 'all') {
            $category = null;
        }

        $criteria = [
            'category_id' => $category,
            'brand' => $filters['brand'] ?? null,
            'flavor' => $filters['flavor'] ?? null,
            'nicotine' => $filters['nicotine'] ?? null,
            'query' => $filters['query'] ?? $filters['q'] ?? null,
            'in_stock' => isset($filters['in_stock']) ? (bool) $filters['in_stock'] : null,
        ];

        $criteria = array_filter($criteria, fn ($val) => $val !== null && $val !== '');
        ksort($criteria);

        $cacheKey = 'catalog:products:' . md5(json_encode(['c' => $criteria, 'p' => $page, 'l' => $limit]));

        if ($this->cache !== null && $this->cache->has($cacheKey)) {
            /** @var array{products: array<int, array<string, mixed>>, total: int, page: int, limit: int} $cached */
            $cached = $this->cache->get($cacheKey);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $products = $this->productRepository->search($criteria, $limit, $offset);
        $total = $this->productRepository->countSearch($criteria);

        $result = [
            'products' => array_map(fn (Product $p) => $p->toArray(), $products),
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
        ];

        if ($this->cache !== null) {
            $this->cache->set($cacheKey, $result, 300, ['catalog', 'products']);
        }

        return $result;
    }
}
