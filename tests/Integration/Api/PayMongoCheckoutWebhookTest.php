<?php

declare(strict_types=1);

namespace Vapehaus\Tests\Integration\Api;

use PDO;
use Vapehaus\App;
use Vapehaus\Infrastructure\Database\SchemaManager;
use Vapehaus\Infrastructure\Security\JwtTokenService;
use Vapehaus\Presentation\Http\Request;
use Vapehaus\Tests\TestCase;

final class PayMongoCheckoutWebhookTest extends TestCase
{
    private App $app;
    private PDO $pdo;
    private string $customerToken;
    private string $adminToken;
    private string $webhookSecret = 'whsk_test_demo_secret_998877';

    protected function setUp(): void
    {
        parent::setUp();
        \Vapehaus\Presentation\Middleware\RateLimiterMiddleware::reset();

        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        putenv("PAYMONGO_WEBHOOK_SECRET={$this->webhookSecret}");
        $_ENV['PAYMONGO_WEBHOOK_SECRET'] = $this->webhookSecret;

        $this->app = new App($this->pdo);
        (new SchemaManager($this->pdo))->seedSampleProducts();

        // Login real customer session
        $custLoginReq = new Request('POST', '/api/v1/auth/login', [], [
            'email' => 'customer@vapehaus.ph',
            'password' => 'VapePass123!',
        ]);
        $custLoginResp = $this->app->handle($custLoginReq);
        $custData = json_decode($custLoginResp->getBody(), true);
        $this->customerToken = $custData['data']['token'];

        // Login real admin session
        $adminLoginReq = new Request('POST', '/api/v1/auth/login', [], [
            'email' => 'admin@vapehaus.ph',
            'password' => 'AdminPass123!',
        ]);
        $adminLoginResp = $this->app->handle($adminLoginReq);
        $adminData = json_decode($adminLoginResp->getBody(), true);
        $this->adminToken = $adminData['data']['token'];
    }

    public function testPayMongoTargetDemoFlow(): void
    {
        $headers = [
            'authorization' => "Bearer {$this->customerToken}",
            'content-type' => 'application/json',
        ];
        $adminHeaders = [
            'authorization' => "Bearer {$this->adminToken}",
            'content-type' => 'application/json',
        ];

        // 1. Add item to cart & Checkout
        $addReq = new Request('POST', '/api/v1/cart/items', [], [
            'variant_id' => 'var_gb_watermelon',
            'quantity' => 2,
        ], $headers);
        $addResp = $this->app->handle($addReq);
        $this->assertEquals(201, $addResp->getStatusCode());

        $checkoutReq = new Request('POST', '/api/v1/checkout', [], [
            'shipping_address' => 'Unit 1001, Cyberpark Tower, Cubao, Quezon City',
            'payment_method' => 'gcash',
            'is_age_attested' => true,
        ], $headers);
        $checkoutResp = $this->app->handle($checkoutReq);
        $this->assertEquals(201, $checkoutResp->getStatusCode());

        $orderData = json_decode($checkoutResp->getBody(), true)['data'];
        $orderId = $orderData['id'];
        $orderNumber = $orderData['order_number'];

        $this->assertEquals('pending', $orderData['status']);
        $this->assertEquals('unpaid', $orderData['payment_status']);
        $this->assertTrue($orderData['total'] > 0);

        // 2. Request PayMongo Checkout Session
        $sessionReq = new Request('POST', '/api/v1/payments/checkout-session', [], [
            'order_id' => $orderId,
            'payment_methods' => ['gcash', 'paymaya', 'card'],
        ], $headers);
        $sessionResp = $this->app->handle($sessionReq);
        $this->assertEquals(201, $sessionResp->getStatusCode(), 'PayMongo checkout session creation failed: ' . $sessionResp->getBody());

        $sessionData = json_decode($sessionResp->getBody(), true)['data'];
        $this->assertEquals($orderId, $sessionData['order_id']);
        $this->assertTrue(!empty($sessionData['checkout_url']));
        $this->assertTrue(!empty($sessionData['checkout_session_id']));

        // 3. Customer Cannot Mutate Payment or Status directly
        $customerPatchReq = new Request('PATCH', "/api/v1/admin/orders/{$orderId}/status", [], [
            'status' => 'paid',
        ], $headers);
        $customerPatchResp = $this->app->handle($customerPatchReq);
        $this->assertEquals(403, $customerPatchResp->getStatusCode()); // Forbidden

        // 4. Webhook with Invalid Signature is Rejected (HTTP 401)
        $invalidWhReq = new Request('POST', '/api/v1/webhooks/paymongo', [], [
            'data' => ['id' => 'evt_fake'],
        ], [
            'paymongo-signature' => 't=123456,te=invalid_signature_hash',
        ]);
        $invalidWhResp = $this->app->handle($invalidWhReq);
        $this->assertEquals(401, $invalidWhResp->getStatusCode());

        // 5. Official PayMongo Webhook Event: checkout_session.payment.paid
        $paymentId = 'pay_gcash_' . bin2hex(random_bytes(6));
        $paymongoPayload = [
            'data' => [
                'id' => 'evt_' . bin2hex(random_bytes(8)),
                'type' => 'event',
                'attributes' => [
                    'type' => 'checkout_session.payment.paid',
                    'livemode' => false,
                    'data' => [
                        'id' => $sessionData['checkout_session_id'],
                        'type' => 'checkout_session',
                        'attributes' => [
                            'amount' => (int) round($orderData['total'] * 100),
                            'payment_intent' => ['id' => 'pi_' . bin2hex(random_bytes(6))],
                            'payments' => [
                                [
                                    'id' => $paymentId,
                                    'type' => 'payment',
                                    'attributes' => [
                                        'amount' => (int) round($orderData['total'] * 100),
                                        'status' => 'paid',
                                    ],
                                ],
                            ],
                            'metadata' => [
                                'order_id' => $orderId,
                                'order_number' => $orderNumber,
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $rawBody = json_encode($paymongoPayload);
        $timestamp = (string) time();
        $validHmac = hash_hmac('sha256', "{$timestamp}.{$rawBody}", $this->webhookSecret);

        $webhookReq = new Request(
            'POST',
            '/api/v1/webhooks/paymongo',
            [],
            $paymongoPayload,
            [
                'paymongo-signature' => "t={$timestamp},te={$validHmac}",
                'content-type' => 'application/json',
            ],
            $rawBody
        );

        $webhookResp = $this->app->handle($webhookReq);
        $this->assertEquals(200, $webhookResp->getStatusCode());
        $webhookData = json_decode($webhookResp->getBody(), true)['data'];
        $this->assertEquals('success', $webhookData['status']);
        $this->assertEquals('paid', $webhookData['order']['status']);
        $this->assertEquals('paid', $webhookData['order']['payment_status']);

        // 6. Duplicate Webhook is Idempotent
        $dupResp = $this->app->handle($webhookReq);
        $this->assertEquals(200, $dupResp->getStatusCode());
        $dupData = json_decode($dupResp->getBody(), true)['data'];
        $this->assertEquals('already_processed', $dupData['status']);

        // 7. Customer Fetches Order via GET /api/v1/orders/{id} -> status is paid
        $getReq = new Request('GET', "/api/v1/orders/{$orderId}", [], [], $headers);
        $getResp = $this->app->handle($getReq);
        $this->assertEquals(200, $getResp->getStatusCode());
        $orderUpdated = json_decode($getResp->getBody(), true)['data'];
        $this->assertEquals('paid', $orderUpdated['status']);
        $this->assertEquals('paid', $orderUpdated['payment_status']);

        // 8. Admin Status Flow: paid -> processing (Preparing)
        $prepReq = new Request('PATCH', "/api/v1/admin/orders/{$orderId}/status", [], [
            'status' => 'processing',
        ], $adminHeaders);
        $prepResp = $this->app->handle($prepReq);
        $this->assertEquals(200, $prepResp->getStatusCode());
        $prepData = json_decode($prepResp->getBody(), true)['data'];
        $this->assertEquals('processing', $prepData['status']);

        // 9. Admin Status Flow: processing -> shipped with courier & tracking (Ready)
        $shipReq = new Request('PATCH', "/api/v1/admin/orders/{$orderId}/ship", [], [
            'courier_name' => 'J&T Express',
            'tracking_number' => 'JT-9876543210PH',
        ], $adminHeaders);
        $shipResp = $this->app->handle($shipReq);
        $this->assertEquals(200, $shipResp->getStatusCode());
        $shipData = json_decode($shipResp->getBody(), true)['data'];
        $this->assertEquals('shipped', $shipData['status']);
        $this->assertEquals('J&T Express', $shipData['courier_name']);
        $this->assertEquals('JT-9876543210PH', $shipData['tracking_number']);

        // 10. Admin Status Flow: shipped -> delivered (Completed)
        $delivReq = new Request('PATCH', "/api/v1/admin/orders/{$orderId}/status", [], [
            'status' => 'delivered',
        ], $adminHeaders);
        $delivResp = $this->app->handle($delivReq);
        $this->assertEquals(200, $delivResp->getStatusCode());
        $delivData = json_decode($delivResp->getBody(), true)['data'];
        $this->assertEquals('delivered', $delivData['status']);
    }
}
