<?php

declare(strict_types=1);

namespace Vapehaus\Execution\Catalog;

use Vapehaus\Domain\Model\Category;
use Vapehaus\Domain\Repository\CategoryRepositoryInterface;

final class ListCategoriesExecution
{
    private CategoryRepositoryInterface $categoryRepository;

    public function __construct(CategoryRepositoryInterface $categoryRepository)
    {
        $this->categoryRepository = $categoryRepository;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function execute(bool $onlyActive = true): array
    {
        $categories = $this->categoryRepository->findAll($onlyActive);
        return array_map(fn (Category $c) => $c->toArray(), $categories);
    }
}
