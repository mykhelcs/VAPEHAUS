<?php

declare(strict_types=1);

namespace Vapehaus\Presentation\Controller;

use Vapehaus\Execution\Payment\PaymentExecution;
use Vapehaus\Infrastructure\Environment\Env;
use Vapehaus\Infrastructure\Payment\PayMongoGateway;
use Vapehaus\Presentation\Http\Request;
use Vapehaus\Presentation\Http\Response;
use Vapehaus\Presentation\Response\ApiResponse;

final class PaymentWebhookController
{
    private PaymentExecution $execution;
    private ?PayMongoGateway $gateway;

    public function __construct(PaymentExecution $execution, ?PayMongoGateway $gateway = null)
    {
        $this->execution = $execution;
        $this->gateway = $gateway;
    }

    public function handleWebhook(Request $request): Response
    {
        $rawBody = $request->getRawBody();
        $paymongoSignature = $request->getHeader('paymongo-signature');
        $webhookSecret = (string) Env::get('PAYMONGO_WEBHOOK_SECRET', '');

        // If PayMongo signature header is provided and secret is configured, verify HMAC-SHA256 signature
        if (!empty($paymongoSignature) && !empty($webhookSecret) && !empty($rawBody)) {
            $gateway = $this->gateway ?? new PayMongoGateway((string) Env::get('PAYMONGO_SECRET_KEY', ''));
            if (!$gateway->verifyWebhookSignature($rawBody, $paymongoSignature, $webhookSecret)) {
                return ApiResponse::error('INVALID_SIGNATURE', 'PayMongo webhook signature verification failed.', [], 401);
            }
        }

        $signature = $paymongoSignature ?? $request->getHeader('x-signature') ?? $request->getHeader('stripe-signature');
        $body = $request->getBody();
        if (empty($body) && !empty($rawBody)) {
            $decoded = json_decode($rawBody, true);
            if (is_array($decoded)) {
                $body = $decoded;
            }
        }

        $result = $this->execution->handleWebhook($body, $signature);
        return ApiResponse::success($result);
    }

    /**
     * @param array{id: string} $params
     */
    public function adminRefund(Request $request, array $params): Response
    {
        $reason = $request->getBodyParam('reason');
        $actorId = (string) $request->getAttribute('userId');
        $result = $this->execution->adminRefund($params['id'], $reason ? (string) $reason : null, $actorId);
        return ApiResponse::success($result);
    }
}
