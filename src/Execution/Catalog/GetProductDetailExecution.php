<?php

declare(strict_types=1);

namespace Vapehaus\Execution\Catalog;

use Vapehaus\Domain\Exception\EntityNotFoundException;
use Vapehaus\Domain\Repository\ProductRepositoryInterface;
use Vapehaus\Infrastructure\Cache\CacheInterface;

final class GetProductDetailExecution
{
    private ProductRepositoryInterface $productRepository;
    private ?CacheInterface $cache;

    public function __construct(ProductRepositoryInterface $productRepository, ?CacheInterface $cache = null)
    {
        $this->productRepository = $productRepository;
        $this->cache = $cache;
    }

    /**
     * @return array<string, mixed>
     */
    public function execute(string $identifier): array
    {
        $normalizedId = strtolower(trim($identifier));
        $cacheKey = 'catalog:product:' . $normalizedId;

        if ($this->cache !== null && $this->cache->has($cacheKey)) {
            /** @var array<string, mixed> $cached */
            $cached = $this->cache->get($cacheKey);
            if (is_array($cached)) {
                return $cached;
            }
        }

        // Try slug first, then ID
        $product = $this->productRepository->findBySlug($identifier);
        if ($product === null) {
            $product = $this->productRepository->findById($identifier);
        }

        if ($product === null) {
            throw new EntityNotFoundException('Product', $identifier);
        }

        $result = $product->toArray();

        if ($this->cache !== null) {
            $this->cache->set($cacheKey, $result, 300, ['catalog', 'products']);
            $idKey = 'catalog:product:' . strtolower($product->getId());
            $slugKey = 'catalog:product:' . strtolower($product->getSlug());
            if ($idKey !== $cacheKey) {
                $this->cache->set($idKey, $result, 300, ['catalog', 'products']);
            }
            if ($slugKey !== $cacheKey) {
                $this->cache->set($slugKey, $result, 300, ['catalog', 'products']);
            }
        }

        return $result;
    }
}
