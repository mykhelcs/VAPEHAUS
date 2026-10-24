<?php

declare(strict_types=1);

namespace Vapehaus\Tests\Integration\Api;

use PDO;
use Vapehaus\App;
use Vapehaus\Infrastructure\Security\JwtTokenService;
use Vapehaus\Presentation\Http\Request;
use Vapehaus\Tests\TestCase;

final class OrderApiTest extends TestCase
{
    private App $app;
    private PDO $pdo;
    private string $customerToken;

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
    }

    public function testCustomerCheckoutWorkflow(): void
    {
        $headers = [
            'authorization' => "Bearer {$this->customerToken}",
        ];

        // 1. Add item to customer cart
        $addReq = new Request('POST', '/api/v1/cart/items', [], [
            'variant_id' => 'var_oxva_black',
            'quantity' => 1,
        ], $headers);
        $addResp = $this->app->handle($addReq);
        $this->assertEquals(201, $addResp->getStatusCode());

        // 2. Checkout
        $checkoutReq = new Request('POST', '/api/v1/checkout', [], [
            'shipping_address' => 'Unit 12B, Emerald Tower, Pasig City',
            'payment_method' => 'cod',
        ], $headers);
        $checkoutResp = $this->app->handle($checkoutReq);
        $this->assertEquals(201, $checkoutResp->getStatusCode());

        $orderData = json_decode($checkoutResp->getBody(), true);
        $this->assertTrue($orderData['success']);
        $orderId = $orderData['data']['id'];
        $this->assertEquals('pending', $orderData['data']['status']);
        $this->assertEquals(1250.0, $orderData['data']['subtotal']);
        $this->assertEquals(50.0, $orderData['data']['shipping_fee']);
        $this->assertEquals(1300.0, $orderData['data']['total']);

        // 3. View user orders
        $ordersReq = new Request('GET', '/api/v1/user/orders', [], [], $headers);
        $ordersResp = $this->app->handle($ordersReq);
        $this->assertEquals(200, $ordersResp->getStatusCode());
        $ordersList = json_decode($ordersResp->getBody(), true);
        $this->assertCount(1, $ordersList['data']);

        // 4. Cancel order
        $cancelReq = new Request('POST', "/api/v1/user/orders/{$orderId}/cancel", [], [], $headers);
        $cancelResp = $this->app->handle($cancelReq);
        $this->assertEquals(200, $cancelResp->getStatusCode());
        $cancelData = json_decode($cancelResp->getBody(), true);
        $this->assertEquals('cancelled', $cancelData['data']['status']);
    }
}
