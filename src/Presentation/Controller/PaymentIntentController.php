<?php

declare(strict_types=1);

namespace Vapehaus\Presentation\Controller;

use Vapehaus\Execution\Payment\CreatePaymentIntentExecution;
use Vapehaus\Presentation\Http\Request;
use Vapehaus\Presentation\Http\Response;
use Vapehaus\Presentation\Response\ApiResponse;

final class PaymentIntentController
{
    private CreatePaymentIntentExecution $execution;

    public function __construct(CreatePaymentIntentExecution $execution)
    {
        $this->execution = $execution;
    }

    public function create(Request $request): Response
    {
        $orderId = (string) ($request->getBodyParam('order_id') ?? '');
        $result = $this->execution->execute($orderId);
        return ApiResponse::success($result);
    }
}
