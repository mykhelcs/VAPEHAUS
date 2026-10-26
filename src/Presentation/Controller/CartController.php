<?php

declare(strict_types=1);

namespace Vapehaus\Presentation\Controller;

use Vapehaus\Execution\Cart\AddToCartExecution;
use Vapehaus\Execution\Cart\ClearCartExecution;
use Vapehaus\Execution\Cart\GetCartExecution;
use Vapehaus\Execution\Cart\RemoveFromCartExecution;
use Vapehaus\Execution\Cart\UpdateCartItemExecution;
use Vapehaus\Presentation\Http\Request;
use Vapehaus\Presentation\Http\Response;
use Vapehaus\Presentation\Response\ApiResponse;

final class CartController
{
    private GetCartExecution $getCartExecution;
    private AddToCartExecution $addToCartExecution;
    private UpdateCartItemExecution $updateCartItemExecution;
    private RemoveFromCartExecution $removeFromCartExecution;
    private ClearCartExecution $clearCartExecution;

    public function __construct(
        GetCartExecution $getCartExecution,
        AddToCartExecution $addToCartExecution,
        UpdateCartItemExecution $updateCartItemExecution,
        RemoveFromCartExecution $removeFromCartExecution,
        ClearCartExecution $clearCartExecution
    ) {
        $this->getCartExecution = $getCartExecution;
        $this->addToCartExecution = $addToCartExecution;
        $this->updateCartItemExecution = $updateCartItemExecution;
        $this->removeFromCartExecution = $removeFromCartExecution;
        $this->clearCartExecution = $clearCartExecution;
    }

    public function getCart(Request $request): Response
    {
        $userId = $request->getAttribute('userId');
        $sessionToken = $request->getHeader('x-session-token') ?? $request->getHeader('x-cart-token');

        $cart = $this->getCartExecution->execute(
            $userId !== null ? (string) $userId : null,
            $sessionToken !== null ? (string) $sessionToken : 'guest_session'
        );

        return ApiResponse::success($cart);
    }

    public function addItem(Request $request): Response
    {
        $userId = $request->getAttribute('userId');
        $sessionToken = $request->getHeader('x-session-token') ?? $request->getHeader('x-cart-token');

        $variantId = (string) ($request->getBodyParam('variant_id') ?? $request->getBodyParam('variantId') ?? '');
        $quantity = (int) ($request->getBodyParam('quantity') ?? 1);

        $cart = $this->addToCartExecution->execute(
            $userId !== null ? (string) $userId : null,
            $sessionToken !== null ? (string) $sessionToken : 'guest_session',
            $variantId,
            $quantity
        );

        return ApiResponse::success($cart, [], 201);
    }

    /**
     * @param array{itemId: string} $params
     */
    public function updateItem(Request $request, array $params): Response
    {
        $userId = $request->getAttribute('userId');
        $sessionToken = $request->getHeader('x-session-token') ?? $request->getHeader('x-cart-token');
        $quantity = (int) ($request->getBodyParam('quantity') ?? 1);

        $cart = $this->updateCartItemExecution->execute(
            $userId !== null ? (string) $userId : null,
            $sessionToken !== null ? (string) $sessionToken : 'guest_session',
            $params['itemId'],
            $quantity
        );

        return ApiResponse::success($cart);
    }

    /**
     * @param array{itemId: string} $params
     */
    public function removeItem(Request $request, array $params): Response
    {
        $userId = $request->getAttribute('userId');
        $sessionToken = $request->getHeader('x-session-token') ?? $request->getHeader('x-cart-token');

        $cart = $this->removeFromCartExecution->execute(
            $userId !== null ? (string) $userId : null,
            $sessionToken !== null ? (string) $sessionToken : 'guest_session',
            $params['itemId']
        );

        return ApiResponse::success($cart);
    }

    public function clear(Request $request): Response
    {
        $userId = $request->getAttribute('userId');
        $sessionToken = $request->getHeader('x-session-token') ?? $request->getHeader('x-cart-token');

        $cart = $this->clearCartExecution->execute(
            $userId !== null ? (string) $userId : null,
            $sessionToken !== null ? (string) $sessionToken : 'guest_session'
        );

        return ApiResponse::success($cart);
    }
}
