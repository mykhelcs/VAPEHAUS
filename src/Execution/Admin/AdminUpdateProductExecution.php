<?php

declare(strict_types=1);

namespace Vapehaus\Execution\Admin;

use Vapehaus\Domain\Exception\EntityNotFoundException;
use Vapehaus\Domain\Exception\ValidationException;
use Vapehaus\Domain\Repository\CategoryRepositoryInterface;
use Vapehaus\Domain\Repository\ProductRepositoryInterface;

final class AdminUpdateProductExecution
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
     *     category_id?: string,
     *     category?: string,
     *     name?: string,
     *     slug?: string,
     *     brand?: string,
     *     description?: ?string,
     *     is_active?: bool,
     *     isActive?: bool,
     *     image_url?: ?string,
     *     imageUrl?: ?string
     * } $data
     * @return array<string, mixed>
     */
    public function execute(string $productId, array $data): array
    {
        $product = $this->productRepository->findById($productId);
        if ($product === null) {
            throw new EntityNotFoundException('Product', $productId);
        }

        $name = isset($data['name']) ? trim((string) $data['name']) : $product->getName();
        $brand = isset($data['brand']) ? trim((string) $data['brand']) : $product->getBrand();
        $slug = isset($data['slug']) ? trim(strtolower((string) $data['slug'])) : $product->getSlug();

        if ($name === '') {
            throw new ValidationException('Product name cannot be empty.');
        }
        if ($brand === '') {
            throw new ValidationException('Product brand cannot be empty.');
        }
        if ($slug === '') {
            $slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower($name));
            $slug = trim((string) $slug, '-');
        }

        // Validate uniqueness of slug if changed
        if ($slug !== $product->getSlug()) {
            $existing = $this->productRepository->findBySlug($slug);
            if ($existing !== null && $existing->getId() !== $productId) {
                throw new ValidationException("Product with slug '{$slug}' already exists.");
            }
        }

        // Category resolution
        $categoryId = $product->getCategoryId();
        $categoryInput = trim((string) ($data['category_id'] ?? $data['category'] ?? ''));
        if ($categoryInput !== '') {
            $category = $this->categoryRepository->findById($categoryInput);
            if ($category === null) {
                $category = $this->categoryRepository->findBySlug(strtolower($categoryInput));
            }
            if ($category === null) {
                throw new EntityNotFoundException('Category', $categoryInput);
            }
            $categoryId = $category->getId();
        }

        $description = array_key_exists('description', $data)
            ? ($data['description'] !== null ? (string) $data['description'] : null)
            : $product->getDescription();

        $isActive = $product->isActive();
        if (isset($data['is_active'])) {
            $isActive = (bool) $data['is_active'];
        } elseif (isset($data['isActive'])) {
            $isActive = (bool) $data['isActive'];
        }

        $product->update($categoryId, $name, $slug, $brand, $description, $isActive);

        // Update image_url if provided
        if (array_key_exists('image_url', $data) || array_key_exists('imageUrl', $data)) {
            $newImageUrl = $data['image_url'] ?? $data['imageUrl'] ?? null;
            $product->setImageUrl($newImageUrl !== null && trim((string) $newImageUrl) !== '' ? trim((string) $newImageUrl) : null);
        }

        $this->productRepository->save($product);

        return $product->toArray();
    }
}
