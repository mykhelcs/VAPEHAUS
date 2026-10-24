<?php

declare(strict_types=1);

namespace Vapehaus\Presentation\Controller;

use Vapehaus\Execution\Wishlist\WishlistExecution;
use Vapehaus\Presentation\Http\Request;
use Vapehaus\Presentation\Http\Response;
use Vapehaus\Presentation\Response\ApiResponse;

final class WishlistController
{
    private WishlistExecution $execution;

    public function __construct(WishlistExecution $execution)
    {
        $this->execution = $execution;
    }

    public function list(Request $request): Response
    {
        $userId = (string) $request->getAttribute('userId');
        $items = $this->execution->getUserWishlist($userId);
        return ApiResponse::success($items);
    }

    /**
     * @param array{productId: string} $params
     */
    public function toggle(Request $request, array $params): Response
    {
        $userId = (string) $request->getAttribute('userId');
        $result = $this->execution->toggle($userId, $params['productId']);
        return ApiResponse::success($result);
    }

    /**
     * @param array{variantId: string} $params
     */
    public function subscribeRestock(Request $request, array $params): Response
    {
        $userId = (string) $request->getAttribute('userId');
        $result = $this->execution->subscribeRestock($userId, $params['variantId']);
        return ApiResponse::success($result);
    }
}
