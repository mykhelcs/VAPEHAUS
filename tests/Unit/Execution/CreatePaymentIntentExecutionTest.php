<?php

declare(strict_types=1);

namespace Vapehaus\Tests\Unit\Execution;

use Vapehaus\Domain\Exception\EntityNotFoundException;
use Vapehaus\Domain\Exception\ValidationException;
use Vapehaus\Domain\Model\Order;
use Vapehaus\Domain\Model\OrderItem;
use Vapehaus\Domain\ValueObject\Money;
use Vapehaus\Domain\ValueObject\OrderStatus;
use Vapehaus\Execution\Payment\CreatePaymentIntentExecution;
use Vapehaus\Infrastructure\Payment\PayMongoGateway;
use Vapehaus\Tests\Mocks\InMemoryOrderRepository;
use Vapehaus\Tests\TestCase;

final class FakePayMongoGateway extends PayMongoGateway
{
    /** @var array<string, mixed> */
    public array $lastCreatedPayload = [];
    public ?string $mockIntentId = 'pi_test_12345';
    public ?string $mockClientKey = 'pi_test_client_key_xyz';

    public function __construct()
    {
        parent::__construct('sk_test_fake_key');
    }

    public function createPaymentIntent(int $amountCentavos, string $description, array $metadata = []): array
    {
        $this->lastCreatedPayload = [
            'amount' => $amountCentavos,
            'description' => $description,
            'metadata' => $metadata,
        ];

        return [
            'payment_intent_id' => $this->mockIntentId ?? 'pi_default',
            'client_key' => $this->mockClientKey ?? 'client_key_default',
        ];
    }
}

final class CreatePaymentIntentExecutionTest extends TestCase
{
    private InMemoryOrderRepository $orderRepo;
    private FakePayMongoGateway $gateway;
    private CreatePaymentIntentExecution $execution;

    protected function setUp(): void
    {
        $this->orderRepo = new InMemoryOrderRepository();
        $this->gateway = new FakePayMongoGateway();
        $this->execution = new CreatePaymentIntentExecution($this->gateway, $this->orderRepo);
    }

    public function testExecuteSuccessReturnsIntentData(): void
    {
        $items = [
            new OrderItem('item_1', 'ord_1', 'var_1', 'Geek Bar', 'SKU-1', 'Mint', '50mg', Money::fromFloat(650.0), 2, Money::fromFloat(1300.0)),
        ];

        $order = new Order(
            'ord_1',
            'VH-2026-0001',
            'usr_1',
            OrderStatus::pending(),
            Money::fromFloat(1300.0),
            Money::fromFloat(150.0),
            Money::fromFloat(1450.0),
            'Sample Address',
            'gcash',
            'unpaid',
            null, // discountAmount
            null, // couponCode
            null, // trackingNumber
            null, // courierName
            null, // trackingUrl
            null, // shippedAt
            null, // deliveredAt
            '2026-10-27 10:00:00', // createdAt
            $items
        );

        $this->orderRepo->save($order);

        $result = $this->execution->execute('ord_1');

        $this->assertEquals('ord_1', $result['order_id']);
        $this->assertEquals('pi_test_12345', $result['payment_intent_id']);
        $this->assertEquals('pi_test_client_key_xyz', $result['client_key']);
        $this->assertEquals(145000, $result['amount_centavos']);

        // Check gateway received correct data
        $this->assertEquals(145000, $this->gateway->lastCreatedPayload['amount']);
        $this->assertEquals('VAPEHAUS Order #VH-2026-0001', $this->gateway->lastCreatedPayload['description']);
        $this->assertEquals('ord_1', $this->gateway->lastCreatedPayload['metadata']['order_id']);
    }

    public function testExecuteThrowsEntityNotFoundForMissingOrder(): void
    {
        $this->expectException(EntityNotFoundException::class);
        $this->execution->execute('non_existent_order');
    }

    public function testGatewayWebhookSignatureVerification(): void
    {
        $gateway = new PayMongoGateway('sk_test_123');
        $secret = 'whsec_test_secret';
        $payload = '{"data":{"id":"evt_123"}}';
        $timestamp = (string) time();
        $te = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
        $signatureHeader = "t={$timestamp},te={$te}";

        $this->assertTrue($gateway->verifyWebhookSignature($payload, $signatureHeader, $secret));
        $this->assertFalse($gateway->verifyWebhookSignature($payload, "t={$timestamp},te=invalid", $secret));
    }
}
