<?php

declare(strict_types=1);

namespace Vapehaus\Execution\Catalog;

use Vapehaus\Domain\Model\Category;
use Vapehaus\Domain\Repository\CategoryRepositoryInterface;
use Vapehaus\Infrastructure\Cache\CacheInterface;

final class ListCategoriesExecution
{
    private CategoryRepositoryInterface $categoryRepository;
    private ?CacheInterface $cache;

    public function __construct(CategoryRepositoryInterface $categoryRepository, ?CacheInterface $cache = null)
    {
        $this->categoryRepository = $categoryRepository;
        $this->cache = $cache;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function execute(bool $onlyActive = true): array
    {
        $cacheKey = 'catalog:categories:' . ($onlyActive ? 'active' : 'all');
        if ($this->cache !== null && $this->cache->has($cacheKey)) {
            /** @var array<int, array<string, mixed>> $cached */
            $cached = $this->cache->get($cacheKey);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $categories = $this->categoryRepository->findAll($onlyActive);
        $result = array_map(fn (Category $c) => $c->toArray(), $categories);

        if ($this->cache !== null) {
            $this->cache->set($cacheKey, $result, 300, ['catalog', 'categories']);
        }

        return $result;
    }
}
