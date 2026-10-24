<?php

declare(strict_types=1);

namespace Vapehaus\Execution\Cart;

use Vapehaus\Domain\Exception\EntityNotFoundException;
use Vapehaus\Domain\Exception\InsufficientStockException;
use Vapehaus\Domain\Exception\ValidationException;
use Vapehaus\Domain\Model\Cart;
use Vapehaus\Domain\Model\CartItem;
use Vapehaus\Domain\Repository\CartRepositoryInterface;
use Vapehaus\Domain\Repository\ProductRepositoryInterface;

final class AddToCartExecution
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
    public function execute(?string $userId, ?string $sessionToken, string $variantId, int $quantity): array
    {
        if ($quantity <= 0) {
            throw new ValidationException('Quantity must be at least 1.');
        }

        $variant = $this->productRepository->findVariantById($variantId);
        if ($variant === null) {
            throw new EntityNotFoundException('ProductVariant', $variantId);
        }

        if (!$variant->isActive()) {
            throw new ValidationException("Selected vape product variant is currently unavailable.");
        }

        $cart = null;
        if ($userId !== null) {
            $cart = $this->cartRepository->findByUserId($userId);
        } elseif ($sessionToken !== null) {
            $cart = $this->cartRepository->findBySessionToken($sessionToken);
        }

        if ($cart === null) {
            $cartId = 'crt_' . bin2hex(random_bytes(8));
            $cart = new Cart($cartId, $userId, $sessionToken, []);
        }

        $existingItem = $cart->getItemByVariantId($variantId);
        $totalRequested = $quantity + ($existingItem !== null ? $existingItem->getQuantity() : 0);

        if (!$variant->hasSufficientStock($totalRequested)) {
            throw new InsufficientStockException($variant->getSku()->getValue(), $totalRequested, $variant->getStockQuantity());
        }

        if ($existingItem !== null) {
            $existingItem->setQuantity($totalRequested);
            $existingItem->attachVariant($variant);
        } else {
            $itemId = 'cit_' . bin2hex(random_bytes(8));
            $newItem = new CartItem($itemId, $cart->getId(), $variantId, $quantity, $variant);
            $cart->addItem($newItem);
        }

        $this->cartRepository->save($cart);

        return $cart->toArray();
    }
}
