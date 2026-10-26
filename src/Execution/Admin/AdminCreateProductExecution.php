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
        $categoryInput = trim((string) ($data['category_id'] ?? $data['category'] ?? ''));
        $category = null;

        if ($categoryInput !== '') {
            $category = $this->categoryRepository->findById($categoryInput);
            if ($category === null) {
                $category = $this->categoryRepository->findBySlug(strtolower($categoryInput));
            }
        }

        if ($category === null) {
            // Default to first active category or cat_devices
            $category = $this->categoryRepository->findById('cat_devices')
                ?? $this->categoryRepository->findById('cat_disposables');
        }

        if ($category === null) {
            throw new EntityNotFoundException('Category', $categoryInput);
        }

        $categoryId = $category->getId();

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
                $sku = !empty($varData['sku']) ? $varData['sku'] : strtoupper(substr($name, 0, 3)) . '-' . bin2hex(random_bytes(3));
                $variant = new ProductVariant(
                    $varId,
                    $productId,
                    new Sku($sku),
                    $varData['flavor'] ?? 'Standard Edition',
                    new NicotineStrength($varData['nicotine'] ?? '20mg'),
                    Money::fromFloat((float) ($varData['price'] ?? 0.0)),
                    (int) ($varData['stock'] ?? 10)
                );
                $this->productRepository->saveVariant($variant);
                $product->addVariant($variant);
            }
        } elseif (isset($data['price'])) {
            // Auto-create default variant when single price is passed
            $varId = 'var_' . bin2hex(random_bytes(6));
            $sku = strtoupper(substr($name, 0, 3)) . '-' . bin2hex(random_bytes(3));
            $variant = new ProductVariant(
                $varId,
                $productId,
                new Sku($sku),
                'Standard Edition',
                new NicotineStrength('20mg'),
                Money::fromFloat((float) $data['price']),
                (int) ($data['stock'] ?? 20)
            );
            $this->productRepository->saveVariant($variant);
            $product->addVariant($variant);
        }

        return $product->toArray();
    }
}
