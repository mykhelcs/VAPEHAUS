<?php

declare(strict_types=1);

namespace Vapehaus\Presentation\Controller;

use Vapehaus\Execution\Auth\GetProfileExecution;
use Vapehaus\Execution\Order\CancelOrderExecution;
use Vapehaus\Execution\Order\GetOrderDetailExecution;
use Vapehaus\Execution\Order\GetUserOrdersExecution;
use Vapehaus\Presentation\Http\Request;
use Vapehaus\Presentation\Http\Response;
use Vapehaus\Presentation\Response\ApiResponse;

final class UserController
{
    private GetProfileExecution $profileExecution;
    private GetUserOrdersExecution $userOrdersExecution;
    private GetOrderDetailExecution $orderDetailExecution;
    private CancelOrderExecution $cancelOrderExecution;

    public function __construct(
        GetProfileExecution $profileExecution,
        GetUserOrdersExecution $userOrdersExecution,
        GetOrderDetailExecution $orderDetailExecution,
        CancelOrderExecution $cancelOrderExecution
    ) {
        $this->profileExecution = $profileExecution;
        $this->userOrdersExecution = $userOrdersExecution;
        $this->orderDetailExecution = $orderDetailExecution;
        $this->cancelOrderExecution = $cancelOrderExecution;
    }

    public function getProfile(Request $request): Response
    {
        $userId = (string) $request->getAttribute('userId');
        $profile = $this->profileExecution->execute($userId);
        return ApiResponse::success($profile);
    }

    public function getOrders(Request $request): Response
    {
        $userId = (string) $request->getAttribute('userId');
        $page = (int) $request->getQueryParam('page', 1);
        $limit = (int) $request->getQueryParam('limit', 20);

        $orders = $this->userOrdersExecution->execute($userId, $page, $limit);
        return ApiResponse::success($orders, ['page' => $page, 'limit' => $limit]);
    }

    /**
     * @param array{id: string} $params
     */
    public function getOrderDetail(Request $request, array $params): Response
    {
        $userId = (string) $request->getAttribute('userId');
        $order = $this->orderDetailExecution->execute($params['id'], $userId, false);
        return ApiResponse::success($order);
    }

    /**
     * @param array{id: string} $params
     */
    public function cancelOrder(Request $request, array $params): Response
    {
        $userId = (string) $request->getAttribute('userId');
        $order = $this->cancelOrderExecution->execute($params['id'], $userId, false);
        return ApiResponse::success($order);
    }
}
