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
        $product = $this->products[$id] ?? null;
        if ($product !== null) {
            foreach ($this->variants as $variant) {
                if ($variant->getProductId() === $id) {
                    $product->addVariant($variant);
                }
            }
        }
        return $product;
    }

    public function findBySlug(string $slug): ?Product
    {
        $slug = strtolower(trim($slug));
        foreach ($this->products as $product) {
            if ($product->getSlug() === $slug) {
                return $this->findById($product->getId());
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
        $sku = strtoupper(trim($sku));
        foreach ($this->variants as $variant) {
            if ($variant->getSku()->getValue() === $sku) {
                return $variant;
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
        $results = array_values($this->products);
        if (isset($criteria['category_id'])) {
            $results = array_filter($results, fn ($p) => $p->getCategoryId() === $criteria['category_id']);
        }
        return array_slice(array_values($results), $offset, $limit);
    }

    public function countSearch(array $criteria = []): int
    {
        return count($this->search($criteria, 1000, 0));
    }

    public function delete(string $id): void
    {
        unset($this->products[$id]);
    }
}
