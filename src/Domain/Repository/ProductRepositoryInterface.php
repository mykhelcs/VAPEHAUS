<?php

declare(strict_types=1);

namespace Vapehaus\Domain\Repository;

use Vapehaus\Domain\Model\Product;
use Vapehaus\Domain\Model\ProductVariant;

interface ProductRepositoryInterface
{
    public function save(Product $product): void;

    public function findById(string $id): ?Product;

    public function findBySlug(string $slug): ?Product;

    public function findVariantById(string $variantId): ?ProductVariant;

    public function findVariantBySku(string $sku): ?ProductVariant;

    public function saveVariant(ProductVariant $variant): void;

    /**
     * @param array{category_id?: string, brand?: string, flavor?: string, nicotine?: string, query?: string, in_stock?: bool} $criteria
     * @return array<int, Product>
     */
    public function search(array $criteria = [], int $limit = 20, int $offset = 0): array;

    /**
     * @param array{category_id?: string, brand?: string, flavor?: string, nicotine?: string, query?: string, in_stock?: bool} $criteria
     */
    public function countSearch(array $criteria = []): int;

    public function delete(string $id): void;
}
