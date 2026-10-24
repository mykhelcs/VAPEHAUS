<?php

declare(strict_types=1);

namespace Vapehaus\Execution\Cart;

use Vapehaus\Domain\Repository\CartRepositoryInterface;

final class ClearCartExecution
{
    private CartRepositoryInterface $cartRepository;

    public function __construct(CartRepositoryInterface $cartRepository)
    {
        $this->cartRepository = $cartRepository;
    }

    /**
     * @return array<string, mixed>
     */
    public function execute(?string $userId, ?string $sessionToken): array
    {
        $cart = null;
        if ($userId !== null) {
            $cart = $this->cartRepository->findByUserId($userId);
        } elseif ($sessionToken !== null) {
            $cart = $this->cartRepository->findBySessionToken($sessionToken);
        }

        if ($cart !== null) {
            $cart->clear();
            $this->cartRepository->save($cart);
            return $cart->toArray();
        }

        return ['item_count' => 0, 'items' => [], 'subtotal' => 0.00];
    }
}
