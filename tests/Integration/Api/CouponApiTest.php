<?php

declare(strict_types=1);

namespace Vapehaus\Tests\Integration\Api;

use PDO;
use Vapehaus\App;
use Vapehaus\Infrastructure\Security\JwtTokenService;
use Vapehaus\Presentation\Http\Request;
use Vapehaus\Tests\TestCase;

final class CouponApiTest extends TestCase
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

    public function testApplyCouponAndCheckoutWithDiscount(): void
    {
        $headers = ['authorization' => "Bearer {$this->customerToken}"];

        // 1. Add Geek Bar Pulse (650 PHP)
        $addReq = new Request('POST', '/api/v1/cart/items', [], [
            'variant_id' => 'var_gb_watermelon',
            'quantity' => 1,
        ], $headers);
        $this->app->handle($addReq);

        // 2. Apply WELCOME10 coupon (10% off on min 500 PHP)
        $couponReq = new Request('POST', '/api/v1/cart/coupon', [], [
            'code' => 'WELCOME10',
        ], $headers);
        $couponResp = $this->app->handle($couponReq);

        $this->assertEquals(200, $couponResp->getStatusCode());
        $couponData = json_decode($couponResp->getBody(), true);
        $this->assertTrue($couponData['success']);
        $this->assertEquals(65.0, $couponData['data']['discount_amount']); // 10% of 650 = 65

        // 3. Checkout with coupon
        $checkoutReq = new Request('POST', '/api/v1/checkout', [], [
            'shipping_address' => 'Sample Address',
            'payment_method' => 'cod',
        ], $headers);
        $checkoutResp = $this->app->handle($checkoutReq);

        $this->assertEquals(201, $checkoutResp->getStatusCode());
        $orderData = json_decode($checkoutResp->getBody(), true);
        $this->assertEquals('WELCOME10', $orderData['data']['coupon_code']);
        $this->assertEquals(65.0, $orderData['data']['discount_amount']);
        $this->assertEquals(635.0, $orderData['data']['total']); // 650 - 65 + 50 = 635
    }
}
