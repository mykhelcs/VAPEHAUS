<?php

declare(strict_types=1);

namespace Vapehaus\Execution\Admin;

use Vapehaus\Domain\Exception\ValidationException;
use Vapehaus\Domain\Model\Category;
use Vapehaus\Domain\Repository\CategoryRepositoryInterface;

final class AdminCreateCategoryExecution
{
    private CategoryRepositoryInterface $categoryRepository;

    public function __construct(CategoryRepositoryInterface $categoryRepository)
    {
        $this->categoryRepository = $categoryRepository;
    }

    /**
     * @param array{name: string, slug?: string, description?: ?string, is_active?: bool} $data
     * @return array<string, mixed>
     */
    public function execute(array $data): array
    {
        $name = trim($data['name'] ?? '');
        $slug = trim(strtolower($data['slug'] ?? ''));

        if ($slug === '' && $name !== '') {
            $slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower($name));
            $slug = trim((string) $slug, '-');
        }

        $existing = $this->categoryRepository->findBySlug($slug);
        if ($existing !== null) {
            throw new ValidationException("Category with slug '{$slug}' already exists.");
        }

        $categoryId = 'cat_' . bin2hex(random_bytes(6));
        $category = new Category(
            $categoryId,
            $name,
            $slug,
            $data['description'] ?? null,
            (bool) ($data['is_active'] ?? true)
        );

        $this->categoryRepository->save($category);

        return $category->toArray();
    }
}
