<?php

declare(strict_types=1);

namespace Vapehaus\Domain\Model;

use Vapehaus\Domain\Exception\ValidationException;
use Vapehaus\Domain\ValueObject\Money;

final class CartItem
{
    private string $id;
    private string $cartId;
    private string $variantId;
    private int $quantity;
    private ?ProductVariant $variant;

    public function __construct(
        string $id,
        string $cartId,
        string $variantId,
        int $quantity,
        ?ProductVariant $variant = null
    ) {
        if ($quantity <= 0) {
            throw new ValidationException("Cart item quantity must be greater than zero.");
        }

        $this->id = $id;
        $this->cartId = $cartId;
        $this->variantId = $variantId;
        $this->quantity = $quantity;
        $this->variant = $variant;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getCartId(): string
    {
        return $this->cartId;
    }

    public function getVariantId(): string
    {
        return $this->variantId;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): void
    {
        if ($quantity <= 0) {
            throw new ValidationException("Cart item quantity must be greater than zero.");
        }
        $this->quantity = $quantity;
    }

    public function getVariant(): ?ProductVariant
    {
        return $this->variant;
    }

    public function attachVariant(ProductVariant $variant): void
    {
        $this->variant = $variant;
    }

    public function getSubtotal(): Money
    {
        if ($this->variant === null) {
            return Money::zero();
        }
        return $this->variant->getPrice()->multiply($this->quantity);
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'cart_id' => $this->cartId,
            'variant_id' => $this->variantId,
            'quantity' => $this->quantity,
            'variant' => $this->variant?->toArray(),
            'subtotal' => $this->getSubtotal()->toFloat(),
            'subtotal_formatted' => sprintf('%s %.2f', $this->getSubtotal()->getCurrency(), $this->getSubtotal()->toFloat()),
        ];
    }
}
