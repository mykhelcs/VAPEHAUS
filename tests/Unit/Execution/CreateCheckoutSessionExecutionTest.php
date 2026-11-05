<?php

declare(strict_types=1);

namespace Vapehaus\Tests\Unit\Execution;

use Vapehaus\Domain\Exception\EntityNotFoundException;
use Vapehaus\Domain\Exception\ValidationException;
use Vapehaus\Domain\Model\Order;
use Vapehaus\Domain\Model\OrderItem;
use Vapehaus\Domain\Repository\OrderRepositoryInterface;
use Vapehaus\Domain\ValueObject\Money;
use Vapehaus\Domain\ValueObject\OrderStatus;
use Vapehaus\Execution\Payment\CreateCheckoutSessionExecution;
use Vapehaus\Infrastructure\Payment\PayMongoGateway;
use Vapehaus\Tests\TestCase;

final class CreateCheckoutSessionExecutionTest extends TestCase
{
    private MockOrderRepository $orderRepo;
    private MockPayMongoGateway $gateway;
    private CreateCheckoutSessionExecution $execution;

    protected function setUp(): void
    {
        parent::setUp();
        $this->orderRepo = new MockOrderRepository();
        $this->gateway = new MockPayMongoGateway();
        $this->execution = new CreateCheckoutSessionExecution($this->gateway, $this->orderRepo);
    }

    public function testRejectsEmptyOrderId(): void
    {
        $this->expectException(ValidationException::class);
        $this->execution->execute('');
    }

    public function testRejectsNonExistentOrder(): void
    {
        $this->expectException(EntityNotFoundException::class);
        $this->execution->execute('ord_missing');
    }

    public function testRejectsCancelledOrder(): void
    {
        $order = new Order(
            'ord_cancelled',
            'VH-CAN-001',
            'usr_01',
            OrderStatus::cancelled(),
            Money::fromFloat(500),
            Money::zero(),
            Money::fromFloat(500),
            'Address'
        );
        $this->orderRepo->save($order);

        $this->expectException(ValidationException::class);
        $this->execution->execute('ord_cancelled');
    }

    public function testRejectsAlreadyPaidOrder(): void
    {
        $order = new Order(
            'ord_paid',
            'VH-PAID-001',
            'usr_01',
            OrderStatus::paid(),
            Money::fromFloat(500),
            Money::zero(),
            Money::fromFloat(500),
            'Address'
        );
        $this->orderRepo->save($order);

        $this->expectException(ValidationException::class);
        $this->execution->execute('ord_paid');
    }

    public function testCreatesCheckoutSessionSuccessfully(): void
    {
        $item = new OrderItem(
            'oit_01',
            'ord_valid',
            'var_01',
            'Geek Bar Pulse',
            'GB-WTR-01',
            'Watermelon',
            '50mg',
            Money::fromFloat(650),
            2
        );

        $order = new Order(
            'ord_valid',
            'VH-VAL-001',
            'usr_01',
            OrderStatus::pending(),
            Money::fromFloat(1300),
            Money::fromFloat(150),
            Money::fromFloat(1450),
            'Sample Delivery Address',
            'gcash',
            'unpaid',
            null,
            null,
            null,
            'Standard Courier',
            null,
            null,
            null,
            null,
            [$item]
        );
        $this->orderRepo->save($order);

        $result = $this->execution->execute('ord_valid');

        $this->assertEquals('ord_valid', $result['order_id']);
        $this->assertEquals('VH-VAL-001', $result['order_number']);
        $this->assertEquals('cs_test_mock_123', $result['checkout_session_id']);
        $this->assertEquals('https://checkout.paymongo.com/cs_test_mock_123', $result['checkout_url']);
        $this->assertEquals(145000, $result['amount_centavos']);
        $this->assertEquals(145000, $this->gateway->lastAmount);
    }
}

final class MockOrderRepository implements OrderRepositoryInterface
{
    /** @var array<string, Order> */
    private array $orders = [];

    public function save(Order $order): void
    {
        $this->orders[$order->getId()] = $order;
    }

    public function findById(string $id): ?Order
    {
        return $this->orders[$id] ?? null;
    }

    public function findByOrderNumber(string $orderNumber): ?Order
    {
        foreach ($this->orders as $o) {
            if ($o->getOrderNumber() === $orderNumber) {
                return $o;
            }
        }
        return null;
    }

    public function findByUserId(string $userId, int $limit = 20, int $offset = 0): array
    {
        return array_values(array_filter($this->orders, fn (Order $o) => $o->getUserId() === $userId));
    }

    public function findAll(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        return array_values($this->orders);
    }

    public function countAll(array $filters = []): int
    {
        return count($this->orders);
    }

    public function getMetrics(): array
    {
        return [
            'total_revenue' => 0.0,
            'total_orders' => count($this->orders),
            'pending_orders' => 0,
            'delivered_orders' => 0,
        ];
    }
}

final class MockPayMongoGateway extends PayMongoGateway
{
    public int $lastAmount = 0;
    public array $lastLineItems = [];

    public function __construct()
    {
        parent::__construct('sk_test_mock');
    }

    public function createCheckoutSession(
        int $amountCentavos,
        string $description,
        array $lineItems,
        array $metadata,
        string $successUrl,
        string $cancelUrl,
        array $paymentMethods = ['gcash', 'paymaya', 'card']
    ): array {
        $this->lastAmount = $amountCentavos;
        $this->lastLineItems = $lineItems;

        return [
            'checkout_session_id' => 'cs_test_mock_123',
            'checkout_url' => 'https://checkout.paymongo.com/cs_test_mock_123',
        ];
    }
}
