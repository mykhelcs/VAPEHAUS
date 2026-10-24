<?php

declare(strict_types=1);

namespace Vapehaus\Tests\Integration\Api;

use PDO;
use Vapehaus\App;
use Vapehaus\Infrastructure\Security\JwtTokenService;
use Vapehaus\Presentation\Http\Request;
use Vapehaus\Tests\TestCase;

final class WishlistApiTest extends TestCase
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

    public function testWishlistToggleAndRestockSubscription(): void
    {
        $headers = ['authorization' => "Bearer {$this->customerToken}"];

        // 1. Toggle Wishlist Add
        $togReq = new Request('POST', '/api/v1/wishlist/toggle/prd_geek_pulse', [], [], $headers);
        $togResp = $this->app->handle($togReq);
        $this->assertEquals(200, $togResp->getStatusCode());
        $togData = json_decode($togResp->getBody(), true);
        $this->assertTrue($togData['data']['is_wishlisted']);

        // 2. View Wishlist
        $listReq = new Request('GET', '/api/v1/wishlist', [], [], $headers);
        $listResp = $this->app->handle($listReq);
        $this->assertEquals(200, $listResp->getStatusCode());
        $listData = json_decode($listResp->getBody(), true);
        $this->assertCount(1, $listData['data']);
        $this->assertEquals('Geek Bar Pulse 15000 Puffs', $listData['data'][0]['name']);

        // 3. Toggle Wishlist Remove
        $remResp = $this->app->handle($togReq);
        $remData = json_decode($remResp->getBody(), true);
        $this->assertFalse($remData['data']['is_wishlisted']);

        // 4. Subscribe for restock alert
        $subReq = new Request('POST', '/api/v1/products/variants/var_gb_watermelon/restock-alert', [], [], $headers);
        $subResp = $this->app->handle($subReq);
        $this->assertEquals(200, $subResp->getStatusCode());
        $subData = json_decode($subResp->getBody(), true);
        $this->assertTrue($subData['data']['subscribed']);
    }
}
