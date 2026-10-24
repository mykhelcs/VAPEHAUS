<?php

declare(strict_types=1);

namespace Vapehaus\Presentation\Controller;

use Vapehaus\Execution\Coupon\CouponExecution;
use Vapehaus\Presentation\Http\Request;
use Vapehaus\Presentation\Http\Response;
use Vapehaus\Presentation\Response\ApiResponse;

final class CouponController
{
    private CouponExecution $execution;

    public function __construct(CouponExecution $execution)
    {
        $this->execution = $execution;
    }

    public function apply(Request $request): Response
    {
        $userId = $request->getAttribute('userId');
        $sessionToken = $request->getHeader('x-session-token');
        $code = (string) $request->getBodyParam('code', '');

        $result = $this->execution->apply(
            $userId !== null ? (string) $userId : null,
            $sessionToken !== null ? (string) $sessionToken : null,
            $code
        );

        return ApiResponse::success($result);
    }

    public function remove(Request $request): Response
    {
        $userId = $request->getAttribute('userId');
        $sessionToken = $request->getHeader('x-session-token');

        $result = $this->execution->remove(
            $userId !== null ? (string) $userId : null,
            $sessionToken !== null ? (string) $sessionToken : null
        );

        return ApiResponse::success($result);
    }

    public function adminCreate(Request $request): Response
    {
        $coupon = $this->execution->adminCreate($request->getBody());
        return ApiResponse::success($coupon, [], 201);
    }

    public function adminList(Request $request): Response
    {
        $coupons = $this->execution->adminList();
        return ApiResponse::success($coupons);
    }

    /**
     * @param array{id: string} $params
     */
    public function adminToggle(Request $request, array $params): Response
    {
        $coupon = $this->execution->adminToggle($params['id']);
        return ApiResponse::success($coupon);
    }
}
