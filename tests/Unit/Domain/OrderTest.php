<?php

declare(strict_types=1);

namespace Vapehaus\Tests\Unit\Domain;

use Vapehaus\Domain\Exception\InvalidOrderStateException;
use Vapehaus\Domain\Model\Order;
use Vapehaus\Domain\ValueObject\Money;
use Vapehaus\Domain\ValueObject\OrderStatus;
use Vapehaus\Tests\TestCase;

final class OrderTest extends TestCase
{
    public function testOrderStateTransitions(): void
    {
        $order = new Order(
            'ord-1',
            'VH-20261023-0001',
            'usr-1',
            OrderStatus::pending(),
            Money::fromFloat(500.00),
            Money::fromFloat(50.00),
            Money::fromFloat(550.00),
            '123 Vape Street, Metro Manila'
        );

        $this->assertEquals('pending', $order->getStatus()->getValue());
        $this->assertTrue($order->canCancel());

        $order->transitionTo('paid');
        $this->assertEquals('paid', $order->getStatus()->getValue());
        $this->assertEquals('paid', $order->getPaymentStatus());

        $order->transitionTo('processing');
        $this->assertEquals('processing', $order->getStatus()->getValue());

        $order->transitionTo('shipped');
        $this->assertEquals('shipped', $order->getStatus()->getValue());

        $order->transitionTo('delivered');
        $this->assertEquals('delivered', $order->getStatus()->getValue());
    }

    public function testDisallowedStateTransitionThrows(): void
    {
        $order = new Order(
            'ord-2',
            'VH-20261023-0002',
            'usr-1',
            OrderStatus::delivered(),
            Money::fromFloat(500.00),
            Money::fromFloat(50.00),
            Money::fromFloat(550.00),
            '123 Vape Street, Metro Manila'
        );

        $this->expectException(InvalidOrderStateException::class);
        $order->transitionTo('cancelled');
    }
}
