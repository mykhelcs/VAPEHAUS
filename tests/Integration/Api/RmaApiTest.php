<?php

declare(strict_types=1);

namespace Vapehaus\Tests\Integration\Api;

use PDO;
use Vapehaus\App;
use Vapehaus\Infrastructure\Security\JwtTokenService;
use Vapehaus\Presentation\Http\Request;
use Vapehaus\Tests\TestCase;

final class RmaApiTest extends TestCase
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

    public function testRmaCreationAndAdminApproval(): void
    {
        $custHeaders = ['authorization' => "Bearer {$this->customerToken}"];
        $adminHeaders = ['authorization' => "Bearer {$this->adminToken}"];

        // 1. Checkout order
        $addReq = new Request('POST', '/api/v1/cart/items', [], [
            'variant_id' => 'var_oxva_black',
            'quantity' => 1,
        ], $custHeaders);
        $this->app->handle($addReq);

        $checkoutReq = new Request('POST', '/api/v1/checkout', [], [
            'shipping_address' => 'Sample Address',
        ], $custHeaders);
        $checkoutResp = $this->app->handle($checkoutReq);
        $orderData = json_decode($checkoutResp->getBody(), true);
        $orderId = $orderData['data']['id'];

        // 2. Customer Files RMA for Defective Device
        $rmaReq = new Request('POST', "/api/v1/user/orders/{$orderId}/returns", [], [
            'order_id' => $orderId,
            'variant_id' => 'var_oxva_black',
            'reason' => 'dead_on_arrival',
            'description' => 'Device screen does not turn on even after charging for 2 hours.',
        ], $custHeaders);

        $rmaResp = $this->app->handle($rmaReq);
        $this->assertEquals(201, $rmaResp->getStatusCode());
        $rmaData = json_decode($rmaResp->getBody(), true);
        $rmaId = $rmaData['data']['id'];
        $this->assertEquals('requested', $rmaData['data']['status']);

        // 3. Admin Approves Replacement
        $modReq = new Request('PATCH', "/api/v1/admin/returns/{$rmaId}/status", [], [
            'status' => 'approved',
            'admin_notes' => 'Serial number verified against batch warranty.',
        ], $adminHeaders);

        $modResp = $this->app->handle($modReq);
        $this->assertEquals(200, $modResp->getStatusCode());
        $modData = json_decode($modResp->getBody(), true);
        $this->assertEquals('approved', $modData['data']['status']);
    }
}
