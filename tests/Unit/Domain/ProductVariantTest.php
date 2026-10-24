<?php

declare(strict_types=1);

namespace Vapehaus\Tests\Unit\Domain;

use Vapehaus\Domain\Exception\InsufficientStockException;
use Vapehaus\Domain\Model\ProductVariant;
use Vapehaus\Domain\ValueObject\Money;
use Vapehaus\Domain\ValueObject\NicotineStrength;
use Vapehaus\Domain\ValueObject\Sku;
use Vapehaus\Tests\TestCase;

final class ProductVariantTest extends TestCase
{
    public function testStockDeductionSucceedsWhenSufficient(): void
    {
        $variant = new ProductVariant(
            'var-1',
            'prod-1',
            new Sku('RELX-WTRMLN-3MG'),
            'Watermelon Ice',
            new NicotineStrength('3mg'),
            Money::fromFloat(250.00),
            10
        );

        $this->assertTrue($variant->hasSufficientStock(5));
        $variant->decrementStock(5);
        $this->assertEquals(5, $variant->getStockQuantity());
    }

    public function testStockDeductionThrowsOnInsufficientStock(): void
    {
        $variant = new ProductVariant(
            'var-2',
            'prod-1',
            new Sku('RELX-MINT-50MG'),
            'Cool Mint',
            new NicotineStrength('50mg'),
            Money::fromFloat(300.00),
            2
        );

        $this->expectException(InsufficientStockException::class);
        $variant->decrementStock(3);
    }
}
