<?php

declare(strict_types=1);

namespace Vapehaus\Execution\Admin;

use Vapehaus\Domain\Exception\EntityNotFoundException;
use Vapehaus\Domain\Exception\ValidationException;
use Vapehaus\Domain\Model\Product;
use Vapehaus\Domain\Model\ProductVariant;
use Vapehaus\Domain\Repository\CategoryRepositoryInterface;
use Vapehaus\Domain\Repository\ProductRepositoryInterface;
use Vapehaus\Domain\ValueObject\Money;
use Vapehaus\Domain\ValueObject\NicotineStrength;
use Vapehaus\Domain\ValueObject\Sku;

final class AdminCreateProductExecution
{
    private ProductRepositoryInterface $productRepository;
    private CategoryRepositoryInterface $categoryRepository;

    public function __construct(
        ProductRepositoryInterface $productRepository,
        CategoryRepositoryInterface $categoryRepository
    ) {
        $this->productRepository = $productRepository;
        $this->categoryRepository = $categoryRepository;
    }

    /**
     * @param array{
     *     category_id: string,
     *     name: string,
     *     slug?: string,
     *     brand: string,
     *     description?: ?string,
     *     is_active?: bool,
     *     variants?: array<int, array{sku: string, flavor: string, nicotine: string, price: float, stock: int}>
     * } $data
     * @return array<string, mixed>
     */
    public function execute(array $data): array
    {
        $categoryId = $data['category_id'] ?? '';
        $category = $this->categoryRepository->findById($categoryId);
        if ($category === null) {
            throw new EntityNotFoundException('Category', $categoryId);
        }

        $name = trim($data['name'] ?? '');
        $slug = trim(strtolower($data['slug'] ?? ''));
        if ($slug === '' && $name !== '') {
            $slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower($name));
            $slug = trim((string) $slug, '-');
        }

        $existing = $this->productRepository->findBySlug($slug);
        if ($existing !== null) {
            throw new ValidationException("Product with slug '{$slug}' already exists.");
        }

        $productId = 'prd_' . bin2hex(random_bytes(6));
        $product = new Product(
            $productId,
            $categoryId,
            $name,
            $slug,
            $data['brand'] ?? '',
            $data['description'] ?? null,
            (bool) ($data['is_active'] ?? true)
        );

        $this->productRepository->save($product);

        // Add initial variants if provided
        if (!empty($data['variants']) && is_array($data['variants'])) {
            foreach ($data['variants'] as $varData) {
                $varId = 'var_' . bin2hex(random_bytes(6));
                $variant = new ProductVariant(
                    $varId,
                    $productId,
                    new Sku($varData['sku']),
                    $varData['flavor'],
                    new NicotineStrength($varData['nicotine']),
                    Money::fromFloat((float) $varData['price']),
                    (int) ($varData['stock'] ?? 0)
                );
                $this->productRepository->saveVariant($variant);
                $product->addVariant($variant);
            }
        }

        return $product->toArray();
    }
}
