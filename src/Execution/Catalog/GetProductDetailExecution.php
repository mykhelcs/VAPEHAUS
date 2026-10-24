<?php

declare(strict_types=1);

namespace Vapehaus\Execution\Catalog;

use Vapehaus\Domain\Exception\EntityNotFoundException;
use Vapehaus\Domain\Repository\ProductRepositoryInterface;

final class GetProductDetailExecution
{
    private ProductRepositoryInterface $productRepository;

    public function __construct(ProductRepositoryInterface $productRepository)
    {
        $this->productRepository = $productRepository;
    }

    /**
     * @return array<string, mixed>
     */
    public function execute(string $identifier): array
    {
        // Try slug first, then ID
        $product = $this->productRepository->findBySlug($identifier);
        if ($product === null) {
            $product = $this->productRepository->findById($identifier);
        }

        if ($product === null) {
            throw new EntityNotFoundException('Product', $identifier);
        }

        return $product->toArray();
    }
}
