<?php

declare(strict_types=1);

namespace Vapehaus\Tests;

use DateTimeImmutable;
use Vapehaus\Domain\Exception\InvalidEmailException;
use Vapehaus\Domain\Exception\InvalidOrderStateException;
use Vapehaus\Domain\Exception\UnderageException;
use Vapehaus\Domain\Exception\ValidationException;
use Vapehaus\Domain\Model\Coupon;
use Vapehaus\Domain\Model\Order;
use Vapehaus\Domain\Model\OrderItem;
use Vapehaus\Domain\Model\Product;
use Vapehaus\Domain\Model\ProductVariant;
use Vapehaus\Domain\Model\User;
use Vapehaus\Domain\ValueObject\Email;
use Vapehaus\Domain\ValueObject\Money;
use Vapehaus\Domain\ValueObject\NicotineStrength;
use Vapehaus\Domain\ValueObject\OrderStatus;
use Vapehaus\Domain\ValueObject\Role;
use Vapehaus\Domain\ValueObject\Sku;

final class DomainTest extends TestCase
{
    // ── 1. Money Value Object ──────────────────────────────────────────

    public function testMoneyFormattingAndCentsArithmetic(): void
    {
        $m = Money::fromFloat(1500.0);
        $this->assertEquals(150000, $m->getAmountInCents());
        $this->assertEquals(1500.0, $m->toFloat());
        $this->assertEquals('PHP', $m->getCurrency());

        $m2 = Money::fromFloat(3490.50);
        $sum = $m->add($m2);
        $this->assertEquals(4990.50, $sum->toFloat());

        $zero = Money::zero();
        $this->assertEquals(0.0, $zero->toFloat());
    }

    // ── 2. OrderStatus State Machine ───────────────────────────────────

    public function testOrderStatusTransitions(): void
    {
        $pending = OrderStatus::pending();
        $this->assertEquals('pending', $pending->getValue());
        $this->assertTrue($pending->canTransitionTo('processing'));
        $this->assertTrue($pending->canTransitionTo('cancelled'));
        $this->assertFalse($pending->canTransitionTo('delivered'));

        $processing = new OrderStatus('processing');
        $this->assertTrue($processing->canTransitionTo('shipped'));

        $delivered = new OrderStatus('delivered');
        $this->assertFalse($delivered->canTransitionTo('pending'));
        $this->assertFalse($delivered->canTransitionTo('cancelled'));
    }

    // ── 3. Email Value Object ──────────────────────────────────────────

    public function testEmailValidation(): void
    {
        $email = new Email('customer@vapehaus.ph');
        $this->assertEquals('customer@vapehaus.ph', $email->getValue());

        $this->expectException(InvalidEmailException::class);
        new Email('invalid-email-address');
    }

    // ── 4. Role Value Object ───────────────────────────────────────────

    public function testRoleRbac(): void
    {
        $customer = Role::customer();
        $this->assertTrue($customer->isCustomer());
        $this->assertFalse($customer->isAdmin());

        $admin = Role::admin();
        $this->assertTrue($admin->isAdmin());
        $this->assertFalse($admin->isCustomer());
    }

    // ── 5. User Invariants & 18+ Age Rule (RA 11900) ───────────────────

    public function testUserUnderageAssertion(): void
    {
        // 25 years old adult
        $adultDob = (new DateTimeImmutable('-25 years'))->format('Y-m-d');
        $user = new User(
            'usr_adult',
            new Email('adult@test.ph'),
            'hash',
            'Adult',
            'User',
            '+639170001111',
            Role::customer(),
            $adultDob,
            true
        );

        $this->assertEquals(25, $user->calculateAge());
        // Should not throw
        $user->assertCanPurchaseVape(18);

        // 16 years old minor
        $minorDob = (new DateTimeImmutable('-16 years'))->format('Y-m-d');
        $minor = new User(
            'usr_minor',
            new Email('minor@test.ph'),
            'hash',
            'Minor',
            'User',
            '+639170002222',
            Role::customer(),
            $minorDob,
            false
        );

        $this->assertEquals(16, $minor->calculateAge());
        $this->expectException(UnderageException::class);
        $minor->assertCanPurchaseVape(18);
    }

    // ── 6. Coupon Discount Calculations ────────────────────────────────

    public function testCouponDiscountCalculations(): void
    {
        $coupon = new Coupon(
            'cpn_haus10',
            'HAUS10',
            'percentage',
            10.0,
            Money::fromFloat(0.0),
            null,
            1000,
            0,
            date('Y-m-d H:i:s', strtotime('+30 days')),
            true
        );

        $subtotal = Money::fromFloat(2000.0);
        $shipping = Money::fromFloat(150.0);

        $discount = $coupon->calculateDiscount($subtotal, $shipping);
        $this->assertEquals(200.0, $discount->toFloat());

        $arr = $coupon->toArray();
        $this->assertTrue($arr['isActive']);
        $this->assertEquals(10.0, $arr['discountPercent']);
    }

    // ── 7. Order Entity & Deterministic Snapshotting ────────────────────

    public function testOrderLifecycleAndTransitions(): void
    {
        $item = new OrderItem(
            'oit_1',
            'ord_1',
            'var_1',
            'Geek Bar Pulse',
            'GB-WTRMLN',
            'Watermelon Ice',
            '50mg',
            Money::fromFloat(650.0),
            2
        );

        $this->assertEquals(1300.0, $item->getTotalPrice()->toFloat());

        $order = new Order(
            'ord_1',
            'VH-20261024-TEST',
            'usr_1',
            OrderStatus::pending(),
            Money::fromFloat(1300.0),
            Money::fromFloat(150.0),
            Money::fromFloat(1450.0),
            'Manila, Philippines',
            'cod',
            'unpaid',
            Money::zero(),
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            [$item]
        );

        $this->assertEquals('pending', $order->getStatus()->getValue());
        $order->transitionTo('processing');
        $this->assertEquals('processing', $order->getStatus()->getValue());

        $order->transitionTo('shipped');
        $this->assertEquals('shipped', $order->getStatus()->getValue());

        $order->transitionTo('delivered');
        $this->assertEquals('delivered', $order->getStatus()->getValue());

        // Reverse should fail
        $this->expectException(InvalidOrderStateException::class);
        $order->transitionTo('pending');
    }
}
