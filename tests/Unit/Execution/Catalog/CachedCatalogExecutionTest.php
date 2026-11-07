<?php

declare(strict_types=1);

namespace Vapehaus\Tests\Unit\Execution\Catalog;

use PDO;
use Vapehaus\Domain\Model\Category;
use Vapehaus\Domain\Model\Product;
use Vapehaus\Domain\Model\ProductVariant;
use Vapehaus\Domain\ValueObject\Money;
use Vapehaus\Domain\ValueObject\NicotineStrength;
use Vapehaus\Domain\ValueObject\Sku;
use Vapehaus\Domain\Repository\CategoryRepositoryInterface;
use Vapehaus\Domain\Repository\ProductRepositoryInterface;
use Vapehaus\Execution\Catalog\GetCatalogFacetsExecution;
use Vapehaus\Execution\Catalog\GetProductDetailExecution;
use Vapehaus\Execution\Catalog\ListCategoriesExecution;
use Vapehaus\Execution\Catalog\ListProductsExecution;
use Vapehaus\Infrastructure\Cache\InMemoryCache;
use Vapehaus\Tests\TestCase;

class CountingCategoryRepository implements CategoryRepositoryInterface
{
    public int $callCount = 0;
    /** @var array<int, Category> */
    public array $categories = [];

    public function __construct()
    {
        $this->categories = [
            new Category('cat_1', 'Disposables', 'disposables', 'Desc', true),
            new Category('cat_2', 'Pod Systems', 'pod-systems', 'Desc', true),
        ];
    }

    public function findById(string $id): ?Category
    {
        foreach ($this->categories as $c) {
            if ($c->getId() === $id) return $c;
        }
        return null;
    }

    public function findBySlug(string $slug): ?Category
    {
        foreach ($this->categories as $c) {
            if ($c->getSlug() === $slug) return $c;
        }
        return null;
    }

    public function findAll(bool $onlyActive = true): array
    {
        $this->callCount++;
        return $this->categories;
    }

    public function save(Category $category): void
    {
        $this->categories[] = $category;
    }

    public function delete(string $id): void
    {
        $this->categories = array_values(array_filter($this->categories, fn ($c) => $c->getId() !== $id));
    }
}

class CountingProductRepository implements ProductRepositoryInterface
{
    public int $searchCount = 0;
    public int $detailCount = 0;
    /** @var array<int, Product> */
    public array $products = [];

    public function __construct()
    {
        $variant = new ProductVariant(
            'var_1',
            'prod_1',
            new Sku('GB-15K-WML'),
            'Default',
            new NicotineStrength('50mg'),
            Money::fromFloat(650.0),
            50,
            true
        );

        $this->products = [
            new Product(
                'prod_1',
                'cat_1',
                'Geek Bar Pulse 15000',
                'geek-bar-pulse-15000',
                'Geek Bar',
                'Desc',
                true,
                null,
                [$variant],
                'https://example.com/img.jpg'
            ),
        ];
    }

    public function save(Product $product): void
    {
        $this->products[] = $product;
    }

    public function findById(string $id): ?Product
    {
        $this->detailCount++;
        foreach ($this->products as $p) {
            if ($p->getId() === $id) return $p;
        }
        return null;
    }

    public function findBySlug(string $slug): ?Product
    {
        $this->detailCount++;
        foreach ($this->products as $p) {
            if ($p->getSlug() === $slug) return $p;
        }
        return null;
    }

    public function findVariantById(string $variantId): ?ProductVariant
    {
        foreach ($this->products as $p) {
            foreach ($p->getVariants() as $v) {
                if ($v->getId() === $variantId) return $v;
            }
        }
        return null;
    }

    public function findVariantBySku(string $sku): ?ProductVariant
    {
        foreach ($this->products as $p) {
            foreach ($p->getVariants() as $v) {
                if ($v->getSku() === $sku) return $v;
            }
        }
        return null;
    }

    public function saveVariant(ProductVariant $variant): void {}

    public function search(array $criteria = [], int $limit = 20, int $offset = 0): array
    {
        $this->searchCount++;
        return $this->products;
    }

    public function countSearch(array $criteria = []): int
    {
        return count($this->products);
    }

    public function delete(string $id): void
    {
        $this->products = array_values(array_filter($this->products, fn ($p) => $p->getId() !== $id));
    }
}

final class CachedCatalogExecutionTest extends TestCase
{
    private PDO $pdo;
    private InMemoryCache $cache;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('CREATE TABLE categories (id TEXT PRIMARY KEY, name TEXT, is_active INTEGER)');
        $this->pdo->exec('CREATE TABLE products (id TEXT PRIMARY KEY, category_id TEXT, brand TEXT, is_active INTEGER)');
        $this->pdo->exec('CREATE TABLE product_variants (id TEXT PRIMARY KEY, nicotine_strength TEXT, price REAL, is_active INTEGER)');

        $this->pdo->exec("INSERT INTO categories VALUES ('c1', 'Disposables', 1)");
        $this->pdo->exec("INSERT INTO products VALUES ('p1', 'c1', 'Geek Bar', 1)");
        $this->pdo->exec("INSERT INTO product_variants VALUES ('v1', '50mg', 450.0, 1)");

        $this->cache = new InMemoryCache();
    }

    public function testGetCatalogFacetsExecutionUsesServerCacheStore(): void
    {
        $exec = new GetCatalogFacetsExecution($this->pdo, $this->cache);

        // 1. Initial query populates cache
        $facets1 = $exec->execute();
        $this->assertEquals(1, count($facets1['categories']));
        $this->assertEquals('Geek Bar', $facets1['brands'][0]['brand']);
        $this->assertTrue($this->cache->has('catalog:facets'));

        // 2. Modify database directly
        $this->pdo->exec("INSERT INTO products VALUES ('p2', 'c1', 'OXVA', 1)");

        // 3. Second call returns cached result (fast server-side cache hit)
        $facets2 = $exec->execute();
        $this->assertEquals(1, count($facets2['brands']));
        $this->assertEquals('Geek Bar', $facets2['brands'][0]['brand']);

        // 4. Invalidate cache
        $this->cache->invalidateTags(['catalog', 'facets']);
        $this->assertFalse($this->cache->has('catalog:facets'));

        // 5. Subsequent call reflects fresh database state
        $facets3 = $exec->execute();
        $this->assertEquals(2, count($facets3['brands']));
    }

    public function testListCategoriesExecutionUsesServerCacheStore(): void
    {
        $repo = new CountingCategoryRepository();
        $exec = new ListCategoriesExecution($repo, $this->cache);

        $this->assertEquals(0, $repo->callCount);

        // 1. First execution queries repo
        $cats1 = $exec->execute(true);
        $this->assertEquals(2, count($cats1));
        $this->assertEquals(1, $repo->callCount);

        // 2. Second execution hits server cache without calling repo
        $cats2 = $exec->execute(true);
        $this->assertEquals(2, count($cats2));
        $this->assertEquals(1, $repo->callCount);

        // 3. Invalidate categories cache
        $this->cache->invalidateTags(['categories']);

        // 4. Third execution calls repo again
        $cats3 = $exec->execute(true);
        $this->assertEquals(2, count($cats3));
        $this->assertEquals(2, $repo->callCount);
    }

    public function testListProductsAndDetailExecutionUsesServerCacheStore(): void
    {
        $repo = new CountingProductRepository();
        $listExec = new ListProductsExecution($repo, $this->cache);
        $detailExec = new GetProductDetailExecution($repo, $this->cache);

        // --- List Products caching ---
        $this->assertEquals(0, $repo->searchCount);
        $res1 = $listExec->execute(['brand' => 'Geek Bar']);
        $this->assertEquals(1, count($res1['products']));
        $this->assertEquals(1, $repo->searchCount);

        // Second list call hits cache
        $res2 = $listExec->execute(['brand' => 'Geek Bar']);
        $this->assertEquals(1, count($res2['products']));
        $this->assertEquals(1, $repo->searchCount);

        // --- Product Detail caching ---
        $this->assertEquals(0, $repo->detailCount);
        $detail1 = $detailExec->execute('geek-bar-pulse-15000');
        $this->assertEquals('Geek Bar Pulse 15000', $detail1['name']);
        $this->assertTrue($repo->detailCount > 0);
        $currentDetailCount = $repo->detailCount;

        // Second detail call hits cache
        $detail2 = $detailExec->execute('geek-bar-pulse-15000');
        $this->assertEquals('Geek Bar Pulse 15000', $detail2['name']);
        $this->assertEquals($currentDetailCount, $repo->detailCount);

        // Invalidate products tag
        $this->cache->invalidateTags(['products']);

        // List and detail query repo again
        $listExec->execute(['brand' => 'Geek Bar']);
        $this->assertEquals(2, $repo->searchCount);

        $detailExec->execute('geek-bar-pulse-15000');
        $this->assertTrue($repo->detailCount > $currentDetailCount);
    }
}
