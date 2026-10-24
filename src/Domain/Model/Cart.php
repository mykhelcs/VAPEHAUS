<?php

declare(strict_types=1);

namespace Vapehaus\Domain\Model;

use Vapehaus\Domain\ValueObject\Money;

final class Cart
{
    private string $id;
    private ?string $userId;
    private ?string $sessionToken;
    private ?string $appliedCouponCode;
    /** @var array<string, CartItem> */
    private array $items = [];

    /**
     * @param array<int, CartItem> $items
     */
    public function __construct(
        string $id,
        ?string $userId = null,
        ?string $sessionToken = null,
        array $items = [],
        ?string $appliedCouponCode = null
    ) {
        $this->id = $id;
        $this->userId = $userId;
        $this->sessionToken = $sessionToken;
        $this->appliedCouponCode = $appliedCouponCode ? strtoupper(trim($appliedCouponCode)) : null;

        foreach ($items as $item) {
            $this->items[$item->getId()] = $item;
        }
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getUserId(): ?string
    {
        return $this->userId;
    }

    public function getSessionToken(): ?string
    {
        return $this->sessionToken;
    }

    public function getAppliedCouponCode(): ?string
    {
        return $this->appliedCouponCode;
    }

    public function applyCoupon(?string $code): void
    {
        $this->appliedCouponCode = $code ? strtoupper(trim($code)) : null;
    }

    public function removeCoupon(): void
    {
        $this->appliedCouponCode = null;
    }

    public function assignToUser(string $userId): void
    {
        $this->userId = $userId;
    }

    /**
     * @return array<int, CartItem>
     */
    public function getItems(): array
    {
        return array_values($this->items);
    }

    public function getItemByVariantId(string $variantId): ?CartItem
    {
        foreach ($this->items as $item) {
            if ($item->getVariantId() === $variantId) {
                return $item;
            }
        }
        return null;
    }

    public function getItemById(string $itemId): ?CartItem
    {
        return $this->items[$itemId] ?? null;
    }

    public function addItem(CartItem $item): void
    {
        $existing = $this->getItemByVariantId($item->getVariantId());
        if ($existing !== null) {
            $existing->setQuantity($existing->getQuantity() + $item->getQuantity());
        } else {
            $this->items[$item->getId()] = $item;
        }
    }

    public function removeItem(string $itemId): void
    {
        unset($this->items[$itemId]);
    }

    public function clear(): void
    {
        $this->items = [];
        $this->appliedCouponCode = null;
    }

    public function calculateSubtotal(): Money
    {
        $total = Money::zero();
        foreach ($this->items as $item) {
            $total = $total->add($item->getSubtotal());
        }
        return $total;
    }

    public function getTotalQuantity(): int
    {
        $count = 0;
        foreach ($this->items as $item) {
            $count += $item->getQuantity();
        }
        return $count;
    }

    public function toArray(): array
    {
        $subtotal = $this->calculateSubtotal();
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'item_count' => $this->getTotalQuantity(),
            'applied_coupon' => $this->appliedCouponCode,
            'subtotal' => $subtotal->toFloat(),
            'subtotal_formatted' => sprintf('%s %.2f', $subtotal->getCurrency(), $subtotal->toFloat()),
            'items' => array_map(fn (CartItem $i) => $i->toArray(), array_values($this->items)),
        ];
    }
}
