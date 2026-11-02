<?php

declare(strict_types=1);

namespace Vapehaus\Tests\Unit;

use PDO;
use PHPUnit\Framework\TestCase;
use Vapehaus\Domain\Model\Category;
use Vapehaus\Infrastructure\Database\SchemaManager;
use Vapehaus\Infrastructure\Repository\PdoCategoryRepository;
use Vapehaus\Infrastructure\Repository\PdoProductRepository;
use Vapehaus\Execution\Admin\AdminCreateProductExecution;

final class AdminCreateProductFlowTest extends TestCase
{
    private PDO $pdo;
    private PdoProductRepository $productRepo;
    private PdoCategoryRepository $categoryRepo;
    private AdminCreateProductExecution $createProductExec;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        // Run schema creation once (O(1) init pattern)
        $schema = new SchemaManager($this->pdo);
        $schema->createTables();

        $this->productRepo = new PdoProductRepository($this->pdo);
        $this->categoryRepo = new PdoCategoryRepository($this->pdo);

        // Seed test category
        $cat = new Category('cat_devices', 'Devices & Mods', 'devices', 'Hardware');
        $this->categoryRepo->save($cat);

        $this->createProductExec = new AdminCreateProductExecution($this->productRepo, $this->categoryRepo);
    }

    public function testAddCatalogProductExecutesInSingleTripWithSupabaseImageUrl(): void
    {
        $supabaseCdnUrl = 'https://xpegspccnihndttvhfna.supabase.co/storage/v1/object/public/products/prod_sample123.webp';

        $payload = [
            'name' => 'Geekvape L200 Classic',
            'brand' => 'GEEKVAPE',
            'category_id' => 'cat_devices',
            'price' => 3200.0,
            'image_url' => $supabaseCdnUrl,
            'description' => 'IP68 rated 200W mod.',
            'variants' => [
                [
                    'sku' => 'GV-L200-BLK',
                    'flavor' => 'Volcanic Black',
                    'nicotine' => '20mg',
                    'price' => 3200.0,
                    'stock' => 25,
                ],
                [
                    'sku' => 'GV-L200-SLV',
                    'flavor' => 'Rainbow Silver',
                    'nicotine' => '20mg',
                    'price' => 3200.0,
                    'stock' => 30,
                ],
            ],
        ];

        $productData = $this->createProductExec->execute($payload);

        // Deterministic assertion
        $this->assertNotEmpty($productData['id']);
        $this->assertSame('Geekvape L200 Classic', $productData['name']);
        $this->assertSame('geekvape-l200-classic', $productData['slug']);
        $this->assertSame($supabaseCdnUrl, $productData['image_url']);
        $this->assertCount(2, $productData['variants']);

        // Verify O(1) indexed lookup from database repository
        $savedProduct = $this->productRepo->findById($productData['id']);
        $this->assertNotNull($savedProduct);
        $this->assertSame($supabaseCdnUrl, $savedProduct->getImageUrl());
        $this->assertCount(2, $savedProduct->getVariants());

        // Verify variant lookup by SKU
        $variant = $this->productRepo->findVariantBySku('GV-L200-BLK');
        $this->assertNotNull($variant);
        $this->assertSame('Volcanic Black', $variant->getFlavor());
        $this->assertSame(25, $variant->getStockQuantity());
    }
}
