<?php

declare(strict_types=1);

namespace Vapehaus\Domain\Model;

use Vapehaus\Domain\Exception\ValidationException;

final class Category
{
    private string $id;
    private string $name;
    private string $slug;
    private ?string $description;
    private bool $isActive;

    public function __construct(
        string $id,
        string $name,
        string $slug,
        ?string $description = null,
        bool $isActive = true
    ) {
        $name = trim($name);
        $slug = trim(strtolower($slug));
        if ($name === '') {
            throw new ValidationException("Category name cannot be empty.");
        }
        if ($slug === '') {
            throw new ValidationException("Category slug cannot be empty.");
        }

        $this->id = $id;
        $this->name = $name;
        $this->slug = $slug;
        $this->description = $description;
        $this->isActive = $isActive;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function update(string $name, string $slug, ?string $description, bool $isActive): void
    {
        $name = trim($name);
        $slug = trim(strtolower($slug));
        if ($name === '') {
            throw new ValidationException("Category name cannot be empty.");
        }
        if ($slug === '') {
            throw new ValidationException("Category slug cannot be empty.");
        }

        $this->name = $name;
        $this->slug = $slug;
        $this->description = $description;
        $this->isActive = $isActive;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'is_active' => $this->isActive,
        ];
    }
}
