<?php

declare(strict_types=1);

namespace Vapehaus\Tests\Mocks;

use Vapehaus\Domain\Model\Product;
use Vapehaus\Domain\Model\ProductVariant;
use Vapehaus\Domain\Repository\ProductRepositoryInterface;

final class InMemoryProductRepository implements ProductRepositoryInterface
{
    /** @var array<string, Product> */
    private array $products = [];

    /** @var array<string, ProductVariant> */
    private array $variants = [];

    public function save(Product $product): void
    {
        $this->products[$product->getId()] = $product;
    }

    public function findById(string $id): ?Product
    {
        return $this->products[$id] ?? null;
    }

    public function findBySlug(string $slug): ?Product
    {
        foreach ($this->products as $p) {
            if ($p->getSlug() === $slug) {
                return $p;
            }
        }
        return null;
    }

    public function findVariantById(string $variantId): ?ProductVariant
    {
        return $this->variants[$variantId] ?? null;
    }

    public function findVariantBySku(string $sku): ?ProductVariant
    {
        foreach ($this->variants as $v) {
            if ($v->getSku()->getValue() === $sku) {
                return $v;
            }
        }
        return null;
    }

    public function saveVariant(ProductVariant $variant): void
    {
        $this->variants[$variant->getId()] = $variant;
    }

    public function search(array $criteria = [], int $limit = 20, int $offset = 0): array
    {
        return array_slice(array_values($this->products), $offset, $limit);
    }

    public function countSearch(array $criteria = []): int
    {
        return count($this->products);
    }

    public function delete(string $id): void
    {
        unset($this->products[$id]);
    }
}
