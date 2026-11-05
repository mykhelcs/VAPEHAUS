<?php

declare(strict_types=1);

namespace Vapehaus\Tests\Unit;

use Vapehaus\Domain\Exception\ValidationException;
use Vapehaus\Infrastructure\Payment\PayMongoGateway;
use Vapehaus\Tests\TestCase;

final class PayMongoGatewayTest extends TestCase
{
    private PayMongoGateway $gateway;
    private string $secret = 'whsk_test_mock_secret_12345';

    protected function setUp(): void
    {
        parent::setUp();
        $this->gateway = new PayMongoGateway('sk_test_mock');
    }

    public function testVerifyWebhookSignatureTestMode(): void
    {
        $rawPayload = json_encode(['data' => ['id' => 'evt_123']]);
        $timestamp = (string) time();
        $expectedHmac = hash_hmac('sha256', "{$timestamp}.{$rawPayload}", $this->secret);

        $header = "t={$timestamp},te={$expectedHmac}";
        $this->assertTrue($this->gateway->verifyWebhookSignature($rawPayload, $header, $this->secret));
    }

    public function testVerifyWebhookSignatureLiveMode(): void
    {
        $rawPayload = json_encode(['data' => ['id' => 'evt_live_456']]);
        $timestamp = (string) time();
        $expectedHmac = hash_hmac('sha256', "{$timestamp}.{$rawPayload}", $this->secret);

        $header = "t={$timestamp},li={$expectedHmac}";
        $this->assertTrue($this->gateway->verifyWebhookSignature($rawPayload, $header, $this->secret));
    }

    public function testVerifyWebhookSignatureRejectsTamperedPayload(): void
    {
        $rawPayload = json_encode(['data' => ['id' => 'evt_123']]);
        $timestamp = (string) time();
        $expectedHmac = hash_hmac('sha256', "{$timestamp}.{$rawPayload}", $this->secret);

        $tamperedPayload = json_encode(['data' => ['id' => 'evt_tampered']]);
        $header = "t={$timestamp},te={$expectedHmac}";

        $this->assertFalse($this->gateway->verifyWebhookSignature($tamperedPayload, $header, $this->secret));
    }

    public function testVerifyWebhookSignatureRejectsMissingHeaderOrSecret(): void
    {
        $this->assertFalse($this->gateway->verifyWebhookSignature('{}', '', $this->secret));
        $this->assertFalse($this->gateway->verifyWebhookSignature('{}', 't=123', ''));
    }

    public function testCreateCheckoutSessionRejectsNonPositiveAmount(): void
    {
        $this->expectException(ValidationException::class);
        $this->gateway->createCheckoutSession(
            0,
            'Test Order',
            [],
            [],
            'https://example.com/success',
            'https://example.com/cancel'
        );
    }
}
