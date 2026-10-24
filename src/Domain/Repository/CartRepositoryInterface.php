<?php

declare(strict_types=1);

namespace Vapehaus\Domain\Repository;

use Vapehaus\Domain\Model\Cart;

interface CartRepositoryInterface
{
    public function findByUserId(string $userId): ?Cart;

    public function findBySessionToken(string $sessionToken): ?Cart;

    public function findById(string $id): ?Cart;

    public function save(Cart $cart): void;

    public function delete(string $id): void;
}
