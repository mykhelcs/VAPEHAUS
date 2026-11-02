<?php

declare(strict_types=1);

namespace Vapehaus\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Vapehaus\Domain\Exception\ValidationException;
use Vapehaus\Domain\Model\Category;
use Vapehaus\Domain\Model\Product;
use Vapehaus\Domain\Repository\CategoryRepositoryInterface;
use Vapehaus\Domain\Repository\ProductRepositoryInterface;
use Vapehaus\Execution\Admin\AdminCreateProductExecution;

final class AdminCreateProductExecutionTest extends TestCase
{
    public function testCreateProductWithSupabaseImage(): void
    {
        $mockProductRepo = $this->createMock(ProductRepositoryInterface::class);
        $mockCategoryRepo = $this->createMock(CategoryRepositoryInterface::class);

        $category = new Category('cat_devices', 'Devices', 'devices', 'Vape Devices');
        $mockCategoryRepo->method('findById')->willReturn($category);
        $mockProductRepo->method('findBySlug')->willReturn(null);

        $mockProductRepo->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Product $p) {
                return $p->getName() === 'Geekvape Aegis Legend 3'
                    && $p->getImageUrl() === 'https://xpegspccnihndttvhfna.supabase.co/storage/v1/object/public/products/prod_123.webp';
            }));

        $mockProductRepo->expects($this->once())
            ->method('saveVariant');

        $execution = new AdminCreateProductExecution($mockProductRepo, $mockCategoryRepo);

        $payload = [
            'name' => 'Geekvape Aegis Legend 3',
            'brand' => 'GEEKVAPE',
            'category_id' => 'cat_devices',
            'price' => 2950.0,
            'image_url' => 'https://xpegspccnihndttvhfna.supabase.co/storage/v1/object/public/products/prod_123.webp',
            'variants' => [
                [
                    'sku' => 'GV-L3-GUN',
                    'flavor' => 'Gunmetal',
                    'nicotine' => '20mg',
                    'price' => 2950.0,
                    'stock' => 15,
                ],
            ],
        ];

        $result = $execution->execute($payload);

        $this->assertSame('Geekvape Aegis Legend 3', $result['name']);
        $this->assertSame('https://xpegspccnihndttvhfna.supabase.co/storage/v1/object/public/products/prod_123.webp', $result['image_url']);
        $this->assertCount(1, $result['variants']);
        $this->assertSame('Gunmetal', $result['variants'][0]['flavor']);
    }

    public function testDuplicateSlugThrowsValidationException(): void
    {
        $mockProductRepo = $this->createMock(ProductRepositoryInterface::class);
        $mockCategoryRepo = $this->createMock(CategoryRepositoryInterface::class);

        $category = new Category('cat_devices', 'Devices', 'devices');
        $mockCategoryRepo->method('findById')->willReturn($category);

        $existingProduct = new Product('prd_existing', 'cat_devices', 'Duplicate Product', 'duplicate-product', 'Vapehaus');
        $mockProductRepo->method('findBySlug')->willReturn($existingProduct);

        $execution = new AdminCreateProductExecution($mockProductRepo, $mockCategoryRepo);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("already exists");

        $execution->execute([
            'name' => 'Duplicate Product',
            'brand' => 'VAPEHAUS',
            'category_id' => 'cat_devices',
            'price' => 1000.0,
        ]);
    }
}
