<?php

declare(strict_types=1);

namespace Vapehaus\Tests\Integration\Api;

use PDO;
use Vapehaus\App;
use Vapehaus\Infrastructure\Database\SchemaManager;
use Vapehaus\Infrastructure\Security\JwtTokenService;
use Vapehaus\Presentation\Http\Request;
use Vapehaus\Presentation\Middleware\RateLimiterMiddleware;
use Vapehaus\Tests\TestCase;

final class AdminOrdersFulfillmentTest extends TestCase
{
    private App $app;
    private PDO $pdo;
    private string $adminToken;
    private string $customerToken;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiterMiddleware::reset();

        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->app = new App($this->pdo);
        (new SchemaManager($this->pdo))->seedSampleProducts();

        // Login real admin session to insert session row into DB
        $adminLoginReq = new Request('POST', '/api/v1/auth/login', [], [
            'email' => 'admin@vapehaus.ph',
            'password' => 'AdminPass123!',
        ]);
        $adminLoginResp = $this->app->handle($adminLoginReq);
        $adminData = json_decode($adminLoginResp->getBody(), true);
        $this->adminToken = $adminData['data']['token'];

        // Login real customer session
        $custLoginReq = new Request('POST', '/api/v1/auth/login', [], [
            'email' => 'customer@vapehaus.ph',
            'password' => 'VapePass123!',
        ]);
        $custLoginResp = $this->app->handle($custLoginReq);
        $custData = json_decode($custLoginResp->getBody(), true);
        $this->customerToken = $custData['data']['token'];
    }

    private function createTestOrder(string $paymentMethod = 'cod'): string
    {
        $headers = ['authorization' => "Bearer {$this->customerToken}"];
        $checkoutReq = new Request('POST', '/api/v1/checkout', [], [
            'shipping_address' => 'Unit 801, Prestige Tower, Emerald Ave, Pasig',
            'payment_method' => $paymentMethod,
            'is_age_attested' => true,
            'items' => [
                ['variant_id' => 'var_gb_watermelon', 'quantity' => 2],
            ],
        ], $headers);

        $resp = $this->app->handle($checkoutReq);
        $this->assertEquals(201, $resp->getStatusCode());
        $data = json_decode($resp->getBody(), true);
        $this->assertTrue($data['success']);
        return (string) $data['data']['id'];
    }

    public function testAdminCanTransitionPendingCodOrderToProcessing(): void
    {
        $orderId = $this->createTestOrder('cod');
        $headers = ['authorization' => "Bearer {$this->adminToken}"];

        $req = new Request('PATCH', "/api/v1/admin/orders/{$orderId}/status", [], [
            'status' => 'processing',
        ], $headers);

        $resp = $this->app->handle($req);
        $this->assertEquals(200, $resp->getStatusCode());
        $data = json_decode($resp->getBody(), true);
        $this->assertTrue($data['success']);
        $this->assertEquals('processing', $data['data']['status']);
    }

    public function testAdminCanShipOrderDirectlyWithCourierAndTracking(): void
    {
        $orderId = $this->createTestOrder('cod');
        $headers = ['authorization' => "Bearer {$this->adminToken}"];

        // Ship directly from pending COD or processing
        $req = new Request('PATCH', "/api/v1/admin/orders/{$orderId}/ship", [], [
            'courier_name' => 'J&T Express',
            'tracking_number' => 'JT-9988776655PH',
        ], $headers);

        $resp = $this->app->handle($req);
        $this->assertEquals(200, $resp->getStatusCode());
        $data = json_decode($resp->getBody(), true);
        $this->assertTrue($data['success']);
        $this->assertEquals('shipped', $data['data']['status']);
        $this->assertEquals('J&T Express', $data['data']['courierName']);
        $this->assertEquals('JT-9988776655PH', $data['data']['trackingNumber']);
    }

    public function testAdminCanCompleteDeliveryAndAutoSettlesCodPayment(): void
    {
        $orderId = $this->createTestOrder('cod');
        $headers = ['authorization' => "Bearer {$this->adminToken}"];

        // 1. Ship
        $this->app->handle(new Request('PATCH', "/api/v1/admin/orders/{$orderId}/ship", [], [
            'courier_name' => 'Lalamove',
            'tracking_number' => 'LLM-112233',
        ], $headers));

        // 2. Deliver
        $req = new Request('PATCH', "/api/v1/admin/orders/{$orderId}/status", [], [
            'status' => 'delivered',
        ], $headers);

        $resp = $this->app->handle($req);
        $this->assertEquals(200, $resp->getStatusCode());
        $data = json_decode($resp->getBody(), true);
        $this->assertTrue($data['success']);
        $this->assertEquals('delivered', $data['data']['status']);
        $this->assertEquals('paid', $data['data']['paymentStatus']);
    }

    public function testAdminCanCancelProcessingOrderAndRestoresStock(): void
    {
        $orderId = $this->createTestOrder('cod');
        $headers = ['authorization' => "Bearer {$this->adminToken}"];

        // Advance to processing
        $this->app->handle(new Request('PATCH', "/api/v1/admin/orders/{$orderId}/status", [], [
            'status' => 'processing',
        ], $headers));

        // Cancel
        $req = new Request('PATCH', "/api/v1/admin/orders/{$orderId}/status", [], [
            'status' => 'cancelled',
        ], $headers);

        $resp = $this->app->handle($req);
        $this->assertEquals(200, $resp->getStatusCode());
        $data = json_decode($resp->getBody(), true);
        $this->assertTrue($data['success']);
        $this->assertEquals('cancelled', $data['data']['status']);
    }

    public function testCustomerCannotAccessAdminOrderEndpoints(): void
    {
        $orderId = $this->createTestOrder('cod');
        $headers = ['authorization' => "Bearer {$this->customerToken}"];

        $req = new Request('PATCH', "/api/v1/admin/orders/{$orderId}/status", [], [
            'status' => 'processing',
        ], $headers);

        $resp = $this->app->handle($req);
        $this->assertEquals(403, $resp->getStatusCode());
    }
}
