<?php

declare(strict_types=1);

namespace Vapehaus\Tests\Unit\Domain;

use DateTimeImmutable;
use Vapehaus\Domain\Exception\ValidationException;
use Vapehaus\Domain\Model\Coupon;
use Vapehaus\Domain\ValueObject\Money;
use Vapehaus\Tests\TestCase;

final class CouponTest extends TestCase
{
    public function testPercentageDiscountCalculationWithCap(): void
    {
        $coupon = new Coupon(
            'cpn_1',
            'VAPE20',
            'percentage',
            20.0,
            Money::fromFloat(500.0),
            Money::fromFloat(150.0) // capped at 150
        );

        $subtotal = Money::fromFloat(1000.0);
        $shipping = Money::fromFloat(50.0);

        // 20% of 1000 is 200, but capped at 150
        $discount = $coupon->calculateDiscount($subtotal, $shipping);
        $this->assertEquals(150.0, $discount->toFloat());
    }

    public function testMinimumSpendEnforcement(): void
    {
        $coupon = new Coupon(
            'cpn_2',
            'BIGSPENDER',
            'fixed',
            200.0,
            Money::fromFloat(2000.0)
        );

        $subtotal = Money::fromFloat(1500.0);
        $shipping = Money::fromFloat(50.0);

        $this->expectException(ValidationException::class);
        $coupon->calculateDiscount($subtotal, $shipping);
    }

    public function testExpiredCouponThrowsValidationException(): void
    {
        $past = (new DateTimeImmutable('-2 days'))->format('Y-m-d H:i:s');
        $coupon = new Coupon(
            'cpn_3',
            'EXPIRED50',
            'percentage',
            50.0,
            Money::zero(),
            null,
            0,
            0,
            $past
        );

        $this->assertTrue($coupon->isExpired());
        $this->expectException(ValidationException::class);
        $coupon->calculateDiscount(Money::fromFloat(500.0), Money::zero());
    }
}
