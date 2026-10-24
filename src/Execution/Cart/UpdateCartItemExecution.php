<?php

declare(strict_types=1);

namespace Vapehaus\Execution\Cart;

use Vapehaus\Domain\Exception\EntityNotFoundException;
use Vapehaus\Domain\Exception\InsufficientStockException;
use Vapehaus\Domain\Repository\CartRepositoryInterface;
use Vapehaus\Domain\Repository\ProductRepositoryInterface;

final class UpdateCartItemExecution
{
    private CartRepositoryInterface $cartRepository;
    private ProductRepositoryInterface $productRepository;

    public function __construct(
        CartRepositoryInterface $cartRepository,
        ProductRepositoryInterface $productRepository
    ) {
        $this->cartRepository = $cartRepository;
        $this->productRepository = $productRepository;
    }

    /**
     * @return array<string, mixed>
     */
    public function execute(?string $userId, ?string $sessionToken, string $itemId, int $quantity): array
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

        $item = $cart->getItemById($itemId);
        if ($item === null) {
            throw new EntityNotFoundException('CartItem', $itemId);
        }

        if ($quantity <= 0) {
            $cart->removeItem($itemId);
        } else {
            $variant = $this->productRepository->findVariantById($item->getVariantId());
            if ($variant === null) {
                throw new EntityNotFoundException('ProductVariant', $item->getVariantId());
            }

            if (!$variant->hasSufficientStock($quantity)) {
                throw new InsufficientStockException($variant->getSku()->getValue(), $quantity, $variant->getStockQuantity());
            }

            $item->setQuantity($quantity);
            $item->attachVariant($variant);
        }

        $this->cartRepository->save($cart);

        return $cart->toArray();
    }
}
