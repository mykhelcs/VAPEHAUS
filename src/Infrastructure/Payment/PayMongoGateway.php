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

        if (!isset($parts['t'], $parts['te'])) {
            return false;
        }

        $message  = $parts['t'] . '.' . $rawPayload;
        $expected = hash_hmac('sha256', $message, $webhookSecret);

        return hash_equals($expected, $parts['te']);
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
        ]);

        if (!empty($body)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $raw    = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false || $status >= 500) {
            throw new ValidationException(
                "PayMongo API call failed (HTTP {$status})."
            );
        }

        $decoded = json_decode((string) $raw, true);
        return is_array($decoded) ? $decoded : [];
    }
}
