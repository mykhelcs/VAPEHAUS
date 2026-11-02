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

        $data = $request->getBody();

        // Cart operations use X-Session-Token / X-Cart-Token headers for session
        // identification. Inject the header value into $data so PlaceOrderExecution
        // can locate the correct guest/session cart when the body omits session_token.
        if (!isset($data['session_token']) && !isset($data['sessionToken'])) {
            $sessionToken = $request->getHeader('x-session-token') ?? $request->getHeader('x-cart-token');
            if ($sessionToken !== null) {
                $data['session_token'] = $sessionToken;
            }
        }

        $order = $this->placeOrderExecution->execute($userId, $data);

        return ApiResponse::success($order, [], 201);
    }
}
