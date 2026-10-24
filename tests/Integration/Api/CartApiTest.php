<?php

declare(strict_types=1);

namespace Vapehaus\Tests\Integration\Api;

use PDO;
use Vapehaus\App;
use Vapehaus\Presentation\Http\Request;
use Vapehaus\Tests\TestCase;

final class CartApiTest extends TestCase
{
    private App $app;
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->app = new App($this->pdo);
    }

    public function testGuestCartWorkflowWithSessionToken(): void
    {
        $sessionToken = 'guest_session_12345';
        $headers = ['x-session-token' => $sessionToken];

        // 1. Add item to cart
        $addReq = new Request('POST', '/api/v1/cart/items', [], [
            'variant_id' => 'var_gb_watermelon',
            'quantity' => 2,
        ], $headers);

        $addResp = $this->app->handle($addReq);
        $this->assertEquals(201, $addResp->getStatusCode());
        $addData = json_decode($addResp->getBody(), true);
        $this->assertTrue($addData['success']);
        $this->assertEquals(2, $addData['data']['item_count']);
        $this->assertEquals(1300.0, $addData['data']['subtotal']);

        // 2. Fetch cart
        $getReq = new Request('GET', '/api/v1/cart', [], [], $headers);
        $getResp = $this->app->handle($getReq);
        $this->assertEquals(200, $getResp->getStatusCode());
        $getData = json_decode($getResp->getBody(), true);
        $this->assertEquals(2, $getData['data']['item_count']);

        // 3. Clear cart
        $clearReq = new Request('DELETE', '/api/v1/cart', [], [], $headers);
        $clearResp = $this->app->handle($clearReq);
        $this->assertEquals(200, $clearResp->getStatusCode());
        $clearData = json_decode($clearResp->getBody(), true);
        $this->assertEquals(0, $clearData['data']['item_count']);
    }
}
