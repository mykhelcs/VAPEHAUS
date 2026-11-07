<?php

declare(strict_types=1);

namespace Vapehaus\Execution\Admin;

use Vapehaus\Domain\Exception\EntityNotFoundException;
use Vapehaus\Domain\Repository\ProductRepositoryInterface;
use Vapehaus\Infrastructure\Cache\CacheInterface;

final class AdminDeleteProductExecution
{
    private ProductRepositoryInterface $productRepository;
    private ?CacheInterface $cache;

    public function __construct(ProductRepositoryInterface $productRepository, ?CacheInterface $cache = null)
    {
        $this->productRepository = $productRepository;
        $this->cache = $cache;
    }

    /**
     * @return array{id: string, name: string, deleted: bool}
     */
    public function execute(string $id): array
    {
        $product = $this->productRepository->findById($id);
        if ($product === null) {
            throw new EntityNotFoundException('Product', $id);
        }

        $this->productRepository->delete($id);

        if ($this->cache !== null) {
            $this->cache->invalidateTags(['catalog', 'products', 'facets']);
        }

        return [
            'id' => $id,
            'name' => $product->getName(),
            'deleted' => true,
        ];
    }
}
