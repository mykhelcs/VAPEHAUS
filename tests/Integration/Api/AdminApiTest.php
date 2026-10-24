<?php

declare(strict_types=1);

namespace Vapehaus\Tests\Integration\Api;

use PDO;
use Vapehaus\App;
use Vapehaus\Infrastructure\Security\JwtTokenService;
use Vapehaus\Presentation\Http\Request;
use Vapehaus\Tests\TestCase;

final class AdminApiTest extends TestCase
{
    private App $app;
    private PDO $pdo;
    private string $adminToken;
    private string $customerToken;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->app = new App($this->pdo);

        $tokenService = new JwtTokenService();
        $this->adminToken = $tokenService->generateToken([
            'sub' => 'usr_admin_default',
            'email' => 'admin@vapehaus.ph',
            'role' => 'admin',
        ]);

        $this->customerToken = $tokenService->generateToken([
            'sub' => 'usr_customer_default',
            'email' => 'customer@vapehaus.ph',
            'role' => 'customer',
        ]);
    }

    public function testCustomerForbiddenFromAdminEndpoints(): void
    {
        $headers = ['authorization' => "Bearer {$this->customerToken}"];
        $request = new Request('GET', '/api/v1/admin/dashboard', [], [], $headers);
        $response = $this->app->handle($request);

        $this->assertEquals(403, $response->getStatusCode());
        $data = json_decode($response->getBody(), true);
        $this->assertFalse($data['success']);
        $this->assertEquals('FORBIDDEN', $data['error']['code']);
    }

    public function testAdminDashboardAccess(): void
    {
        $headers = ['authorization' => "Bearer {$this->adminToken}"];
        $request = new Request('GET', '/api/v1/admin/dashboard', [], [], $headers);
        $response = $this->app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getBody(), true);
        $this->assertTrue($data['success']);
        $this->assertEquals(3, $data['data']['total_products']);
        $this->assertEquals(2, $data['data']['total_customers']);
    }

    public function testAdminCreateProductAndVariant(): void
    {
        $headers = ['authorization' => "Bearer {$this->adminToken}"];

        // Create Product
        $prodReq = new Request('POST', '/api/v1/admin/products', [], [
            'category_id' => 'cat_disposables',
            'name' => 'Flava Hyper 10000 Puffs',
            'slug' => 'flava-hyper-10000',
            'brand' => 'Flava',
            'description' => 'Rechargeable high-puff disposable with mesh coil.',
        ], $headers);
        $prodResp = $this->app->handle($prodReq);
        $this->assertEquals(201, $prodResp->getStatusCode());

        $prodData = json_decode($prodResp->getBody(), true);
        $productId = $prodData['data']['id'];

        // Add Variant to Product
        $varReq = new Request('POST', "/api/v1/admin/products/{$productId}/variants", [], [
            'sku' => 'FLV-HYPER-TARO-30MG',
            'flavor' => 'Taro Milkshake',
            'nicotine_strength' => '30mg',
            'price' => 550.00,
            'stock_quantity' => 15,
        ], $headers);
        $varResp = $this->app->handle($varReq);
        $this->assertEquals(201, $varResp->getStatusCode());

        $varData = json_decode($varResp->getBody(), true);
        $this->assertEquals('FLV-HYPER-TARO-30MG', $varData['data']['sku']);
        $this->assertEquals(15, $varData['data']['stock_quantity']);
    }

    public function testAdminUpdateStock(): void
    {
        $headers = ['authorization' => "Bearer {$this->adminToken}"];

        $req = new Request('PATCH', '/api/v1/admin/variants/var_gb_watermelon/stock', [], [
            'adjustment_type' => 'increment',
            'amount' => 10,
        ], $headers);

        $resp = $this->app->handle($req);
        $this->assertEquals(200, $resp->getStatusCode());

        $data = json_decode($resp->getBody(), true);
        $this->assertEquals(35, $data['data']['stock_quantity']); // Seeded with 25, +10 = 35
    }

    public function testAdminListCustomers(): void
    {
        $headers = ['authorization' => "Bearer {$this->adminToken}"];
        $req = new Request('GET', '/api/v1/admin/customers', [], [], $headers);
        $resp = $this->app->handle($req);

        $this->assertEquals(200, $resp->getStatusCode());
        $data = json_decode($resp->getBody(), true);
        $this->assertTrue($data['success']);
        $this->assertTrue($data['meta']['total'] >= 2);
    }
}
