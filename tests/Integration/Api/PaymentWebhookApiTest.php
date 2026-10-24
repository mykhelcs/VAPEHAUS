<?php

declare(strict_types=1);

namespace Vapehaus\Tests\Integration\Api;

use PDO;
use Vapehaus\App;
use Vapehaus\Infrastructure\Security\JwtTokenService;
use Vapehaus\Presentation\Http\Request;
use Vapehaus\Tests\TestCase;

final class PaymentWebhookApiTest extends TestCase
{
    private App $app;
    private PDO $pdo;
    private string $customerToken;
    private string $adminToken;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->app = new App($this->pdo);

        $tokenService = new JwtTokenService();
        $this->customerToken = $tokenService->generateToken([
            'sub' => 'usr_customer_default',
            'email' => 'customer@vapehaus.ph',
            'role' => 'customer',
        ]);
        $this->adminToken = $tokenService->generateToken([
            'sub' => 'usr_admin_default',
            'email' => 'admin@vapehaus.ph',
            'role' => 'admin',
        ]);
    }

    public function testPaymentWebhookAndRefundWorkflow(): void
    {
        $headers = ['authorization' => "Bearer {$this->customerToken}"];
        $adminHeaders = ['authorization' => "Bearer {$this->adminToken}"];

        // 1. Checkout an order
        $addReq = new Request('POST', '/api/v1/cart/items', [], [
            'variant_id' => 'var_gb_watermelon',
            'quantity' => 1,
        ], $headers);
        $this->app->handle($addReq);

        $checkoutReq = new Request('POST', '/api/v1/checkout', [], [
            'shipping_address' => 'Sample Address',
            'payment_method' => 'gcash',
        ], $headers);
        $checkoutResp = $this->app->handle($checkoutReq);
        $orderData = json_decode($checkoutResp->getBody(), true);
        $orderId = $orderData['data']['id'];

        $this->assertEquals('pending', $orderData['data']['status']);

        // 2. Simulate PayMongo / GCash Webhook
        $whReq = new Request('POST', '/api/v1/webhooks/payments', [], [
            'event' => 'payment.paid',
            'gateway' => 'gcash',
            'data' => [
                'order_id' => $orderId,
                'transaction_reference' => 'gc_tx_99887766',
                'amount' => 700.0,
            ]
        ]);
        $whResp = $this->app->handle($whReq);
        $this->assertEquals(200, $whResp->getStatusCode());

        $whData = json_decode($whResp->getBody(), true);
        $this->assertEquals('paid', $whData['data']['order']['status']);
        $this->assertEquals('paid', $whData['data']['order']['payment_status']);

        // 3. Repeated webhook with same transaction reference returns already_processed
        $repeatResp = $this->app->handle($whReq);
        $repeatData = json_decode($repeatResp->getBody(), true);
        $this->assertEquals('already_processed', $repeatData['data']['status']);

        // 4. Admin issues refund
        $refundReq = new Request('POST', "/api/v1/admin/orders/{$orderId}/refund", [], [
            'reason' => 'Customer requested cancellation after payment',
        ], $adminHeaders);
        $refundResp = $this->app->handle($refundReq);
        $this->assertEquals(200, $refundResp->getStatusCode());
        $refundData = json_decode($refundResp->getBody(), true);
        $this->assertEquals('refunded', $refundData['data']['payment_status']);
    }
}
