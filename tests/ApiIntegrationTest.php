<?php

declare(strict_types=1);

namespace Vapehaus\Tests;

use PDO;
use Vapehaus\App;
use Vapehaus\Infrastructure\Database\SchemaManager;
use Vapehaus\Presentation\Http\Request;

final class ApiIntegrationTest extends TestCase
{
    private PDO $pdo;
    private App $app;

    protected function setUp(): void
    {
        // Deterministic in-memory database
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $this->app = new App($this->pdo);
    }

    private function request(string $method, string $path, array $body = [], array $headers = []): array
    {
        $normalizedHeaders = [];
        foreach ($headers as $k => $v) {
            $normalizedHeaders[strtolower($k)] = $v;
        }

        $req = new Request($method, $path, $normalizedHeaders, [], $body);
        $response = $this->app->handle($req);

        $json = json_decode($response->getBody(), true);
        $this->assertNotNull($json, "Response body should be valid JSON: " . $response->getBody());

        return [
            'status' => $response->getStatusCode(),
            'body' => $json,
        ];
    }

    // ── 1. Deterministic Response Envelope ──────────────────────────────

    public function testStandardSuccessEnvelopeFormat(): void
    {
        $res = $this->request('GET', '/api/v1/categories');
        $this->assertEquals(200, $res['status']);
        $this->assertTrue($res['body']['success']);
        $this->assertTrue(is_array($res['body']['data']));
        $this->assertCount(3, $res['body']['data']);
    }

    public function testStandardErrorEnvelopeFormat(): void
    {
        $res = $this->request('GET', '/api/v1/products/non-existent-product-id');
        $this->assertEquals(404, $res['status']);
        $this->assertFalse($res['body']['success']);
        $this->assertEquals('NOT_FOUND', $res['body']['error']['code']);
        $this->assertNotNull($res['body']['error']['message']);
    }

    // ── 2. Auth Flow: Login, Me, Logout ─────────────────────────────────

    public function testAuthLoginAndMeFlow(): void
    {
        // 1. Login
        $loginRes = $this->request('POST', '/api/v1/auth/login', [
            'email' => 'customer@vapehaus.ph',
            'password' => 'VapePass123!',
        ]);

        $this->assertEquals(200, $loginRes['status']);
        $this->assertTrue($loginRes['body']['success']);
        $token = $loginRes['body']['data']['token'];
        $this->assertNotNull($token);

        // 2. Access /api/v1/auth/me with Bearer token
        $meRes = $this->request('GET', '/api/v1/auth/me', [], [
            'Authorization' => "Bearer {$token}",
        ]);

        $this->assertEquals(200, $meRes['status']);
        $this->assertTrue($meRes['body']['success']);
        $this->assertEquals('customer@vapehaus.ph', $meRes['body']['data']['email']);

        // 3. Logout
        $logoutRes = $this->request('POST', '/api/v1/auth/logout', [], [
            'Authorization' => "Bearer {$token}",
        ]);
        $this->assertEquals(200, $logoutRes['status']);
        $this->assertTrue($logoutRes['body']['success']);

        // 4. Access /api/v1/auth/me after logout should return 401 UNAUTHENTICATED
        $meAfter = $this->request('GET', '/api/v1/auth/me', [], [
            'Authorization' => "Bearer {$token}",
        ]);
        $this->assertEquals(401, $meAfter['status']);
        $this->assertEquals('UNAUTHENTICATED', $meAfter['body']['error']['code']);
    }

    // ── 3. Server-Side RBAC Guard (Admin Only) ──────────────────────────

    public function testNonAdminAccessToAdminRouteIsForbidden(): void
    {
        // Login as customer
        $loginRes = $this->request('POST', '/api/v1/auth/login', [
            'email' => 'customer@vapehaus.ph',
            'password' => 'VapePass123!',
        ]);
        $customerToken = $loginRes['body']['data']['token'];

        // Customer attempts to access /api/v1/admin/dashboard
        $adminRes = $this->request('GET', '/api/v1/admin/dashboard', [], [
            'Authorization' => "Bearer {$customerToken}",
        ]);

        $this->assertEquals(403, $adminRes['status']);
        $this->assertFalse($adminRes['body']['success']);
        $this->assertEquals('FORBIDDEN', $adminRes['body']['error']['code']);
    }

    public function testAdminAccessToAdminDashboardSucceeds(): void
    {
        // Login as admin
        $loginRes = $this->request('POST', '/api/v1/auth/login', [
            'email' => 'admin@vapehaus.ph',
            'password' => 'AdminPass123!',
        ]);
        $adminToken = $loginRes['body']['data']['token'];

        // Admin accesses /api/v1/admin/dashboard
        $adminRes = $this->request('GET', '/api/v1/admin/dashboard', [], [
            'Authorization' => "Bearer {$adminToken}",
        ]);

        $this->assertEquals(200, $adminRes['status']);
        $this->assertTrue($adminRes['body']['success']);
        $this->assertNotNull($adminRes['body']['data']['total_revenue']);
        $this->assertNotNull($adminRes['body']['data']['active_products']);
    }

    // ── 4. Catalog Endpoints & Query Filtering ──────────────────────────

    public function testCatalogFiltering(): void
    {
        // Filter by category slug 'disposables'
        $res = $this->request('GET', '/api/v1/products?category=disposables');
        $this->assertEquals(200, $res['status']);
        $this->assertTrue($res['body']['success']);

        foreach ($res['body']['data'] as $prod) {
            $this->assertEquals('disposables', $prod['category']);
        }
    }

    // ── 5. Full Customer E-Commerce Flow: Cart → Coupon → Checkout ─────

    public function testFullPurchaseFlow(): void
    {
        // 1. Add item to cart
        $addRes = $this->request('POST', '/api/v1/cart/items', [
            'variantId' => 'var_gb_watermelon',
            'quantity' => 2,
        ], [
            'X-Session-Token' => 'test_flow_session',
        ]);
        $this->assertEquals(201, $addRes['status']);
        $this->assertTrue($addRes['body']['success']);

        // 2. Apply HAUS10 coupon (10% off)
        $couponRes = $this->request('POST', '/api/v1/cart/coupon', [
            'code' => 'HAUS10',
        ], [
            'X-Session-Token' => 'test_flow_session',
        ]);
        $this->assertEquals(200, $couponRes['status']);
        $this->assertTrue($couponRes['body']['success']);
        $this->assertEquals(130.0, $couponRes['body']['data']['discount_amount']); // 10% of 1300

        // 3. Checkout
        $checkoutRes = $this->request('POST', '/api/v1/checkout', [
            'customerName' => 'Juan Dela Cruz',
            'customerEmail' => 'customer@vapehaus.ph',
            'customerPhone' => '+639178889999',
            'shippingAddress' => 'Unit 12B, Emerald Tower, Ortigas, Pasig City',
            'paymentMethod' => 'cod',
            'isAgeAttested' => true,
            'sessionToken' => 'test_flow_session',
        ]);

        $this->assertEquals(201, $checkoutRes['status']);
        $this->assertTrue($checkoutRes['body']['success']);
        $this->assertNotNull($checkoutRes['body']['data']['orderNumber']);
        $this->assertTrue(str_starts_with($checkoutRes['body']['data']['orderNumber'], 'VH-'));
        $this->assertEquals('pending', $checkoutRes['body']['data']['status']);
    }
}
