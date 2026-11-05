<?php

declare(strict_types=1);

namespace Vapehaus\Infrastructure\Payment;

use Vapehaus\Domain\Exception\ValidationException;

/**
 * PayMongo Payment Intent Gateway (server-side only, secret key).
 * Docs: https://developers.paymongo.com/reference/payment-intent-resource
 */
class PayMongoGateway
{
    protected string $secretKey;
    protected string $baseUrl;

    public function __construct(string $secretKey, string $baseUrl = 'https://api.paymongo.com/v1')
    {
        $this->secretKey = $secretKey;
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    /**
     * Create a PayMongo Payment Intent.
     * Returns client_key (safe to send to browser) and payment_intent_id.
     *
     * @param int                 $amountCentavos  e.g. 65000 = ₱650.00
     * @param string              $description
     * @param array<string,mixed> $metadata        e.g. ['order_id' => 'ord_xxx']
     * @return array{payment_intent_id: string, client_key: string}
     */
    public function createPaymentIntent(
        int $amountCentavos,
        string $description,
        array $metadata = []
    ): array {
        if ($amountCentavos <= 0) {
            throw new ValidationException('Payment amount must be greater than zero.');
        }

        $payload = [
            'data' => [
                'attributes' => [
                    'amount'               => $amountCentavos,
                    'payment_method_allowed' => ['gcash', 'paymaya', 'card'],
                    'payment_method_options' => [
                        'card' => ['request_three_d_secure' => 'any'],
                    ],
                    'currency'             => 'PHP',
                    'capture_type'         => 'automatic',
                    'description'          => $description,
                    'metadata'             => $metadata,
                ],
            ],
        ];

        $response  = $this->request('POST', '/payment_intents', $payload);
        $id        = $response['data']['id'] ?? null;
        $clientKey = $response['data']['attributes']['client_key'] ?? null;

        if ($id === null || $clientKey === null) {
            throw new ValidationException(
                'PayMongo returned an invalid Payment Intent response.'
            );
        }

        return ['payment_intent_id' => (string) $id, 'client_key' => (string) $clientKey];
    }

    /**
     * Create a PayMongo Hosted Checkout Session.
     * Official docs: https://developers.paymongo.com/reference/create-a-checkout-session
     * Returns checkout_session_id and hosted checkout_url.
     *
     * @param int                 $amountCentavos
     * @param string              $description
     * @param array<int, array{name: string, amount: int, currency: string, quantity: int, images?: array<string>}> $lineItems
     * @param array<string,mixed> $metadata
     * @param string              $successUrl
     * @param string              $cancelUrl
     * @param array<int, string>  $paymentMethods   e.g. ['gcash', 'paymaya', 'card']
     * @return array{checkout_session_id: string, checkout_url: string}
     */
    public function createCheckoutSession(
        int $amountCentavos,
        string $description,
        array $lineItems,
        array $metadata,
        string $successUrl,
        string $cancelUrl,
        array $paymentMethods = ['gcash', 'paymaya', 'card']
    ): array {
        if ($amountCentavos <= 0) {
            throw new ValidationException('Payment amount must be greater than zero.');
        }

        // If no explicit line items provided, create a single item representing the total order
        if (empty($lineItems)) {
            $lineItems = [
                [
                    'currency' => 'PHP',
                    'amount'   => $amountCentavos,
                    'name'     => $description,
                    'quantity' => 1,
                ],
            ];
        }

        $payload = [
            'data' => [
                'attributes' => [
                    'billing'              => null,
                    'send_email_receipt'   => false,
                    'show_description'     => true,
                    'show_line_items'      => true,
                    'payment_method_types' => $paymentMethods,
                    'line_items'           => $lineItems,
                    'description'          => $description,
                    'success_url'          => $successUrl,
                    'cancel_url'           => $cancelUrl,
                    'metadata'             => $metadata,
                ],
            ],
        ];

        $response = $this->request('POST', '/checkout_sessions', $payload);
        $id = $response['data']['id'] ?? null;
        $checkoutUrl = $response['data']['attributes']['checkout_url'] ?? null;

        if ($id === null || $checkoutUrl === null) {
            throw new ValidationException('PayMongo returned an invalid Checkout Session response.');
        }

        return [
            'checkout_session_id' => (string) $id,
            'checkout_url'        => (string) $checkoutUrl,
        ];
    }

    /**
     * Retrieve an existing PayMongo Checkout Session.
     *
     * @return array<string, mixed>
     */
    public function getCheckoutSession(string $sessionId): array
    {
        if (trim($sessionId) === '') {
            throw new ValidationException('Checkout session ID is required.');
        }

        return $this->request('GET', '/checkout_sessions/' . urlencode($sessionId));
    }

    /**
     * Verify a PayMongo webhook signature.
     * Header format: "t=<timestamp>,te=<hmac_sha256>,li=<hmac_sha256>"
     */
    public function verifyWebhookSignature(
        string $rawPayload,
        string $signatureHeader,
        string $webhookSecret
    ): bool {
        if (empty($rawPayload) || empty($signatureHeader) || empty($webhookSecret)) {
            return false;
        }

        $parts = [];
        foreach (explode(',', $signatureHeader) as $part) {
            if (str_contains($part, '=')) {
                [$k, $v] = explode('=', trim($part), 2);
                $parts[trim($k)] = trim($v);
            }
        }

        if (!isset($parts['t'])) {
            return false;
        }

        $signature = $parts['te'] ?? $parts['li'] ?? null;
        if ($signature === null || trim($signature) === '') {
            return false;
        }

        $message = $parts['t'] . '.' . $rawPayload;
        $expected = hash_hmac('sha256', $message, $webhookSecret);

        return hash_equals($expected, $signature);
    }

    /**
     * @param array<string,mixed> $body
     * @return array<string,mixed>
     */
    protected function request(string $method, string $path, array $body = []): array
    {
        $ch = curl_init($this->baseUrl . $path);

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Basic ' . base64_encode($this->secretKey . ':'),
        ];

        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ]);

        if (!empty($body)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $raw    = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            throw new ValidationException(
                "PayMongo connection failed: " . ($curlErr !== '' ? $curlErr : "HTTP {$status}")
            );
        }

        $decoded = json_decode((string) $raw, true);
        $decoded = is_array($decoded) ? $decoded : [];

        if (isset($decoded['errors']) && is_array($decoded['errors']) && count($decoded['errors']) > 0) {
            $errDetail = $decoded['errors'][0]['detail'] ?? $decoded['errors'][0]['message'] ?? 'Payment gateway error';
            throw new ValidationException("PayMongo: {$errDetail}");
        }

        if ($status >= 400) {
            throw new ValidationException("PayMongo API returned HTTP {$status}.");
        }

        return $decoded;
    }
}
