<?php

declare(strict_types=1);

namespace Vapehaus\Tests\Unit\Execution;

use Vapehaus\Domain\Exception\InsufficientStockException;
use Vapehaus\Domain\Model\Product;
use Vapehaus\Domain\Model\ProductVariant;
use Vapehaus\Domain\ValueObject\Money;
use Vapehaus\Domain\ValueObject\NicotineStrength;
use Vapehaus\Domain\ValueObject\Sku;
use Vapehaus\Execution\Cart\AddToCartExecution;
use Vapehaus\Tests\Mocks\InMemoryCartRepository;
use Vapehaus\Tests\Mocks\InMemoryProductRepository;
use Vapehaus\Tests\TestCase;

final class AddToCartExecutionTest extends TestCase
{
    private InMemoryCartRepository $cartRepo;
    private InMemoryProductRepository $productRepo;
    private AddToCartExecution $execution;

    protected function setUp(): void
    {
        $this->cartRepo = new InMemoryCartRepository();
        $this->productRepo = new InMemoryProductRepository();
        $this->execution = new AddToCartExecution($this->cartRepo, $this->productRepo);

        // Seed a sample product and variant
        $product = new Product('prd_1', 'cat_1', 'Oxva Xlim Pro', 'oxva-xlim-pro', 'Oxva', 'Pod system');
        $this->productRepo->save($product);

        $variant = new ProductVariant(
            'var_1',
            'prd_1',
            new Sku('OXVA-XLIM-BLK'),
            'Black Carbon',
            new NicotineStrength('0mg'),
            Money::fromFloat(1200.00),
            5
        );
        $this->productRepo->saveVariant($variant);
    }

    public function testAddToCartSuccessfullyWithinStock(): void
    {
        $result = $this->execution->execute('usr_1', null, 'var_1', 2);

        $this->assertEquals(2, $result['item_count']);
        $this->assertEquals(2400.0, $result['subtotal']);
    }

    public function testAddToCartExceedingStockThrowsException(): void
    {
        $this->expectException(InsufficientStockException::class);
        $this->execution->execute('usr_1', null, 'var_1', 6);
    }
}
