<?php

declare(strict_types=1);

namespace Vapehaus\Presentation\Controller;

use Vapehaus\Execution\Payment\PaymentExecution;
use Vapehaus\Presentation\Http\Request;
use Vapehaus\Presentation\Http\Response;
use Vapehaus\Presentation\Response\ApiResponse;

final class PaymentWebhookController
{
    private PaymentExecution $execution;

    public function __construct(PaymentExecution $execution)
    {
        $this->execution = $execution;
    }

    public function handleWebhook(Request $request): Response
    {
        $signature = $request->getHeader('x-signature') ?? $request->getHeader('stripe-signature');
        $result = $this->execution->handleWebhook($request->getBody(), $signature);
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
