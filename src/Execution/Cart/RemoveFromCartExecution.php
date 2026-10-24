<?php

declare(strict_types=1);

namespace Vapehaus\Execution\Cart;

use Vapehaus\Domain\Exception\EntityNotFoundException;
use Vapehaus\Domain\Repository\CartRepositoryInterface;

final class RemoveFromCartExecution
{
    private CartRepositoryInterface $cartRepository;

    public function __construct(CartRepositoryInterface $cartRepository)
    {
        $this->cartRepository = $cartRepository;
    }

    /**
     * @return array<string, mixed>
     */
    public function execute(?string $userId, ?string $sessionToken, string $itemId): array
    {
        $cart = null;
        if ($userId !== null) {
            $cart = $this->cartRepository->findByUserId($userId);
        } elseif ($sessionToken !== null) {
            $cart = $this->cartRepository->findBySessionToken($sessionToken);
        }

        if ($cart === null) {
            throw new EntityNotFoundException('Cart', 'active');
        }

        $cart->removeItem($itemId);
        $this->cartRepository->save($cart);

        return $cart->toArray();
    }
}
