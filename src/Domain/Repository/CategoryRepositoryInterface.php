<?php

declare(strict_types=1);

namespace Vapehaus\Domain\Repository;

use Vapehaus\Domain\Model\Category;

interface CategoryRepositoryInterface
{
    public function save(Category $category): void;

    public function findById(string $id): ?Category;

    public function findBySlug(string $slug): ?Category;

    /**
     * @return array<int, Category>
     */
    public function findAll(bool $onlyActive = true): array;

    public function delete(string $id): void;
}
