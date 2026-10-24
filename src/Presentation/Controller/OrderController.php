<?php

declare(strict_types=1);

namespace Vapehaus\Presentation\Controller;

use Vapehaus\Execution\Order\PlaceOrderExecution;
use Vapehaus\Presentation\Http\Request;
use Vapehaus\Presentation\Http\Response;
use Vapehaus\Presentation\Response\ApiResponse;

final class OrderController
{
    private PlaceOrderExecution $placeOrderExecution;

    public function __construct(PlaceOrderExecution $placeOrderExecution)
    {
        $this->placeOrderExecution = $placeOrderExecution;
    }

    public function checkout(Request $request): Response
    {
        $userId = (string) $request->getAttribute('userId');
        $order = $this->placeOrderExecution->execute($userId, $request->getBody());

        return ApiResponse::success($order, [], 201);
    }
}
