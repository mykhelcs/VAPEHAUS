<?php

declare(strict_types=1);

namespace Vapehaus\Execution\Wishlist;

use Vapehaus\Domain\Exception\EntityNotFoundException;
use Vapehaus\Domain\Model\Product;
use Vapehaus\Domain\Repository\ProductRepositoryInterface;
use Vapehaus\Domain\Repository\WishlistRepositoryInterface;

final class WishlistExecution
{
    private WishlistRepositoryInterface $wishlistRepo;
    private ProductRepositoryInterface $productRepo;

    public function __construct(
        WishlistRepositoryInterface $wishlistRepo,
        ProductRepositoryInterface $productRepo
    ) {
        $this->wishlistRepo = $wishlistRepo;
        $this->productRepo = $productRepo;
    }

    /**
     * @return array{is_wishlisted: bool, product_id: string}
     */
    public function toggle(string $userId, string $productId): array
    {
        $product = $this->productRepo->findById($productId);
        if ($product === null) {
            throw new EntityNotFoundException('Product', $productId);
        }

        $isWishlisted = $this->wishlistRepo->isWishlisted($userId, $productId);
        if ($isWishlisted) {
            $this->wishlistRepo->remove($userId, $productId);
            return ['is_wishlisted' => false, 'product_id' => $productId];
        }

        $this->wishlistRepo->add($userId, $productId);
        return ['is_wishlisted' => true, 'product_id' => $productId];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getUserWishlist(string $userId): array
    {
        $products = $this->wishlistRepo->findProductsByUserId($userId);
        return array_map(fn (Product $p) => $p->toArray(), $products);
    }

    /**
     * @return array{subscribed: bool, variant_id: string}
     */
    public function subscribeRestock(string $userId, string $variantId): array
    {
        $variant = $this->productRepo->findVariantById($variantId);
        if ($variant === null) {
            throw new EntityNotFoundException('ProductVariant', $variantId);
        }

        $this->wishlistRepo->addRestockSubscription($userId, $variantId);
        return ['subscribed' => true, 'variant_id' => $variantId];
    }
}
