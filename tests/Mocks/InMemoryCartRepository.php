<?php

declare(strict_types=1);

namespace Vapehaus\Tests\Mocks;

use Vapehaus\Domain\Model\Cart;
use Vapehaus\Domain\Repository\CartRepositoryInterface;

final class InMemoryCartRepository implements CartRepositoryInterface
{
    /** @var array<string, Cart> */
    private array $carts = [];

    public function findByUserId(string $userId): ?Cart
    {
        foreach ($this->carts as $cart) {
            if ($cart->getUserId() === $userId) {
                return $cart;
            }
        }
        return null;
    }

    public function findBySessionToken(string $sessionToken): ?Cart
    {
        foreach ($this->carts as $cart) {
            if ($cart->getSessionToken() === $sessionToken) {
                return $cart;
            }
        }
        return null;
    }

    public function findById(string $id): ?Cart
    {
        return $this->carts[$id] ?? null;
    }

    public function save(Cart $cart): void
    {
        $this->carts[$cart->getId()] = $cart;
    }

    public function delete(string $id): void
    {
        unset($this->carts[$id]);
    }
}
