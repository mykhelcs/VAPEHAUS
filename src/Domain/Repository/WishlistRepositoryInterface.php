<?php

declare(strict_types=1);

namespace Vapehaus\Domain\Repository;

use Vapehaus\Domain\Model\Product;
use Vapehaus\Domain\Model\RestockSubscription;

interface WishlistRepositoryInterface
{
    public function add(string $userId, string $productId): void;

    public function remove(string $userId, string $productId): void;

    /**
     * @return array<int, Product>
     */
    public function findProductsByUserId(string $userId): array;

    public function isWishlisted(string $userId, string $productId): bool;

    public function addRestockSubscription(string $userId, string $variantId): void;

    /**
     * @return array<int, RestockSubscription>
     */
    public function findPendingRestockSubscriptions(string $variantId): array;

    public function markRestockNotified(string $subscriptionId): void;
}
