<?php

declare(strict_types=1);

namespace Vapehaus\Presentation\Controller;

use Vapehaus\Execution\Payment\CreateCheckoutSessionExecution;
use Vapehaus\Presentation\Http\Request;
use Vapehaus\Presentation\Http\Response;
use Vapehaus\Presentation\Response\ApiResponse;

final class PaymentCheckoutController
{
    private CreateCheckoutSessionExecution $execution;

    public function __construct(CreateCheckoutSessionExecution $execution)
    {
        $this->execution = $execution;
    }

    public function createSession(Request $request): Response
    {
        $orderId = (string) ($request->getBodyParam('order_id') ?? $request->getBodyParam('orderId') ?? '');
        $successUrl = $request->getBodyParam('success_url') ?? $request->getBodyParam('successUrl');
        $cancelUrl = $request->getBodyParam('cancel_url') ?? $request->getBodyParam('cancelUrl');
        $paymentMethods = $request->getBodyParam('payment_methods') ?? ['gcash', 'paymaya', 'card'];

        if (!is_array($paymentMethods) || empty($paymentMethods)) {
            $paymentMethods = ['gcash', 'paymaya', 'card'];
        }

        $result = $this->execution->execute(
            $orderId,
            $successUrl ? (string) $successUrl : null,
            $cancelUrl ? (string) $cancelUrl : null,
            $paymentMethods
        );

        return ApiResponse::success($result, [], 201);
    }
}
