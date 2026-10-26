<?php

declare(strict_types=1);

namespace Vapehaus\Domain\Model;

use DateTimeImmutable;
use Vapehaus\Domain\Exception\ValidationException;

final class Product
{
    private string $id;
    private string $categoryId;
    private string $name;
    private string $slug;
    private string $brand;
    private ?string $description;
    private bool $isActive;
    private string $createdAt;
    /** @var array<string, ProductVariant> */
    private array $variants = [];

    /**
     * @param array<int, ProductVariant> $variants
     */
    public function __construct(
        string $id,
        string $categoryId,
        string $name,
        string $slug,
        string $brand,
        ?string $description = null,
        bool $isActive = true,
        ?string $createdAt = null,
        array $variants = []
    ) {
        $name = trim($name);
        $slug = trim(strtolower($slug));
        $brand = trim($brand);
        if ($name === '') {
            throw new ValidationException("Product name cannot be empty.");
        }
        if ($slug === '') {
            throw new ValidationException("Product slug cannot be empty.");
        }
        if ($brand === '') {
            throw new ValidationException("Product brand cannot be empty.");
        }

        $this->id = $id;
        $this->categoryId = $categoryId;
        $this->name = $name;
        $this->slug = $slug;
        $this->brand = $brand;
        $this->description = $description;
        $this->isActive = $isActive;
        $this->createdAt = $createdAt ?? (new DateTimeImmutable())->format('Y-m-d H:i:s');

        foreach ($variants as $variant) {
            $this->addVariant($variant);
        }
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getCategoryId(): string
    {
        return $this->categoryId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getBrand(): string
    {
        return $this->brand;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function getCreatedAt(): string
    {
        return $this->createdAt;
    }

    public function addVariant(ProductVariant $variant): void
    {
        $this->variants[$variant->getId()] = $variant;
    }

    /**
     * @return array<int, ProductVariant>
     */
    public function getVariants(): array
    {
        return array_values($this->variants);
    }

    public function getVariantById(string $variantId): ?ProductVariant
    {
        return $this->variants[$variantId] ?? null;
    }

    public function update(string $categoryId, string $name, string $slug, string $brand, ?string $description, bool $isActive): void
    {
        $name = trim($name);
        $slug = trim(strtolower($slug));
        $brand = trim($brand);
        if ($name === '') {
            throw new ValidationException("Product name cannot be empty.");
        }
        if ($slug === '') {
            throw new ValidationException("Product slug cannot be empty.");
        }
        if ($brand === '') {
            throw new ValidationException("Product brand cannot be empty.");
        }

        $this->categoryId = $categoryId;
        $this->name = $name;
        $this->slug = $slug;
        $this->brand = $brand;
        $this->description = $description;
        $this->isActive = $isActive;
    }

    public function toArray(): array
    {
        $categorySlug = str_starts_with($this->categoryId, 'cat_')
            ? substr($this->categoryId, 4)
            : $this->categoryId;

        return [
            'id' => $this->id,
            'category_id' => $this->categoryId,
            'category' => $categorySlug,
            'name' => $this->name,
            'slug' => $this->slug,
            'brand' => $this->brand,
            'description' => $this->description,
            'is_active' => $this->isActive,
            'isActive' => $this->isActive,
            'created_at' => $this->createdAt,
            'variants' => array_map(fn (ProductVariant $v) => $v->toArray(), array_values($this->variants)),
        ];
    }
}
