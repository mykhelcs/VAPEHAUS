<?php

declare(strict_types=1);

namespace Vapehaus\Tests\Integration\Api;

use PDO;
use Vapehaus\App;
use Vapehaus\Infrastructure\Database\SchemaManager;
use Vapehaus\Presentation\Http\Request;
use Vapehaus\Presentation\Middleware\RateLimiterMiddleware;
use Vapehaus\Tests\TestCase;

final class CodOrderAndTrackingTest extends TestCase
{
    private App $app;
    private PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiterMiddleware::reset();

        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->app = new App($this->pdo);
        (new SchemaManager($this->pdo))->seedSampleProducts();
    }

    public function testGuestCodOrderCanBeTrackedImmediatelyWithoutAuth(): void
    {
        $checkoutReq = new Request('POST', '/api/v1/checkout', [], [
            'customer_name' => 'Juan Customer',
            'customer_email' => 'customer@vapehaus.ph',
            'customer_phone' => '+639171234567',
            'shipping_address' => '123 Ayala Ave, Makati, Metro Manila 1226',
            'payment_method' => 'cod',
            'shipping_method' => 'standard',
            'is_age_attested' => true,
            'items' => [
                ['variant_id' => 'var_gb_watermelon', 'quantity' => 1],
            ],
        ]);

        $checkoutResp = $this->app->handle($checkoutReq);
        $this->assertEquals(201, $checkoutResp->getStatusCode());

        $checkoutJson = json_decode($checkoutResp->getBody(), true);
        $this->assertTrue($checkoutJson['success']);
        $orderId = $checkoutJson['data']['id'];
        $orderNumber = $checkoutJson['data']['orderNumber'];

        // Immediately track COD order by ID without Bearer token (guest / immediate tracker)
        $trackReq = new Request('GET', "/api/v1/orders/{$orderId}");
        $trackResp = $this->app->handle($trackReq);
        $this->assertEquals(200, $trackResp->getStatusCode());

        $trackJson = json_decode($trackResp->getBody(), true);
        $this->assertTrue($trackJson['success']);
        $this->assertEquals($orderId, $trackJson['data']['id']);
        $this->assertEquals($orderNumber, $trackJson['data']['orderNumber']);
        $this->assertEquals('cod', $trackJson['data']['paymentMethod']);
        $this->assertEquals('pending', $trackJson['data']['status']);
    }

    public function testAuthenticatedCodOrderAppearsInMyOrdersAndTracker(): void
    {
        // 1. Login as seeded adult customer
        $loginReq = new Request('POST', '/api/v1/auth/login', [], [
            'email' => 'customer@vapehaus.ph',
            'password' => 'VapePass123!',
        ]);
        $loginResp = $this->app->handle($loginReq);
        $this->assertEquals(200, $loginResp->getStatusCode());
        $loginData = json_decode($loginResp->getBody(), true);
        $token = $loginData['data']['token'];

        $headers = ['authorization' => "Bearer {$token}"];

        // 2. Place COD order
        $checkoutReq = new Request('POST', '/api/v1/checkout', [], [
            'customerName' => 'Maria Clara',
            'customerEmail' => 'customer@vapehaus.ph',
            'customerPhone' => '+639179998888',
            'shippingAddress' => '456 Ortigas Ave, Pasig, Metro Manila 1605',
            'paymentMethod' => 'cod',
            'shippingMethod' => 'standard',
            'isAgeAttested' => true,
            'items' => [
                ['variantId' => 'var_gb_watermelon', 'quantity' => 2],
            ],
        ], $headers);

        $checkoutResp = $this->app->handle($checkoutReq);
        $this->assertEquals(201, $checkoutResp->getStatusCode());
        $orderJson = json_decode($checkoutResp->getBody(), true);
        $orderId = $orderJson['data']['id'];

        // 3. Verify GET /api/v1/orders returns the COD order for My Orders(1)
        $ordersReq = new Request('GET', '/api/v1/orders', [], [], $headers);
        $ordersResp = $this->app->handle($ordersReq);
        $this->assertEquals(200, $ordersResp->getStatusCode());
        $ordersJson = json_decode($ordersResp->getBody(), true);
        $this->assertTrue($ordersJson['success']);
        $this->assertCount(1, $ordersJson['data']);
        $this->assertEquals($orderId, $ordersJson['data'][0]['id']);

        // 4. Verify GET /api/v1/orders/{id} returns full order details for OrderTrackerModal
        $detailReq = new Request('GET', "/api/v1/orders/{$orderId}", [], [], $headers);
        $detailResp = $this->app->handle($detailReq);
        $this->assertEquals(200, $detailResp->getStatusCode());
        $detailJson = json_decode($detailResp->getBody(), true);
        $this->assertTrue($detailJson['success']);
        $this->assertEquals($orderId, $detailJson['data']['id']);
    }

    public function testRedirectHttpAuthorizationHeaderFallbackExtractsBearerToken(): void
    {
        $prevServer = $_SERVER;
        try {
            unset($_SERVER['HTTP_AUTHORIZATION']);
            $_SERVER['REQUEST_METHOD'] = 'GET';
            $_SERVER['REQUEST_URI'] = '/api/v1/orders';
            $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] = 'Bearer test_apache_rewritten_token_123';

            $req = Request::createFromGlobals();
            $this->assertEquals('test_apache_rewritten_token_123', $req->getBearerToken());
        } finally {
            $_SERVER = $prevServer;
        }
    }

    public function testProductDetailAndReviewsEndpointsReturnDeterministicEnvelope(): void
    {
        $listResp = $this->app->handle(new Request('GET', '/api/v1/products'));
        $this->assertEquals(200, $listResp->getStatusCode());
        $listJson = json_decode($listResp->getBody(), true);
        $this->assertTrue($listJson['success']);
        $this->assertTrue(!empty($listJson['data']));

        $firstProductId = $listJson['data'][0]['id'];

        $prodResp = $this->app->handle(new Request('GET', "/api/v1/products/{$firstProductId}"));
        $this->assertEquals(200, $prodResp->getStatusCode());
        $prodJson = json_decode($prodResp->getBody(), true);
        $this->assertTrue($prodJson['success']);
        $this->assertEquals($firstProductId, $prodJson['data']['id']);
        $this->assertTrue(!empty($prodJson['data']['variants']));

        $revResp = $this->app->handle(new Request('GET', "/api/v1/products/{$firstProductId}/reviews"));
        $this->assertEquals(200, $revResp->getStatusCode());
        $revJson = json_decode($revResp->getBody(), true);
        $this->assertTrue($revJson['success']);
        $this->assertTrue(is_array($revJson['data']));
    }

    public function testUnauthenticatedOrdersListReturnsDeterministicEmptyArray(): void
    {
        $ordersResp = $this->app->handle(new Request('GET', '/api/v1/orders'));
        $this->assertEquals(200, $ordersResp->getStatusCode());
        $ordersJson = json_decode($ordersResp->getBody(), true);
        $this->assertTrue($ordersJson['success']);
        $this->assertEquals([], $ordersJson['data']);
    }
}
