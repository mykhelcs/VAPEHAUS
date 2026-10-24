<?php

declare(strict_types=1);

namespace Vapehaus\Domain\Model;

use Vapehaus\Domain\Exception\ValidationException;
use Vapehaus\Domain\ValueObject\Money;

final class OrderItem
{
    private string $id;
    private string $orderId;
    private string $variantId;
    private string $snapshottedName;
    private string $snapshottedSku;
    private string $snapshottedFlavor;
    private string $snapshottedNicotine;
    private Money $unitPrice;
    private int $quantity;
    private Money $totalPrice;

    public function __construct(
        string $id,
        string $orderId,
        string $variantId,
        string $snapshottedName,
        string $snapshottedSku,
        string $snapshottedFlavor,
        string $snapshottedNicotine,
        Money $unitPrice,
        int $quantity,
        ?Money $totalPrice = null
    ) {
        if ($quantity <= 0) {
            throw new ValidationException("Order item quantity must be greater than zero.");
        }

        $this->id = $id;
        $this->orderId = $orderId;
        $this->variantId = $variantId;
        $this->snapshottedName = trim($snapshottedName);
        $this->snapshottedSku = trim($snapshottedSku);
        $this->snapshottedFlavor = trim($snapshottedFlavor);
        $this->snapshottedNicotine = trim($snapshottedNicotine);
        $this->unitPrice = $unitPrice;
        $this->quantity = $quantity;
        $this->totalPrice = $totalPrice ?? $unitPrice->multiply($quantity);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getOrderId(): string
    {
        return $this->orderId;
    }

    public function getVariantId(): string
    {
        return $this->variantId;
    }

    public function getSnapshottedName(): string
    {
        return $this->snapshottedName;
    }

    public function getSnapshottedSku(): string
    {
        return $this->snapshottedSku;
    }

    public function getSnapshottedFlavor(): string
    {
        return $this->snapshottedFlavor;
    }

    public function getSnapshottedNicotine(): string
    {
        return $this->snapshottedNicotine;
    }

    public function getUnitPrice(): Money
    {
        return $this->unitPrice;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getTotalPrice(): Money
    {
        return $this->totalPrice;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'variant_id' => $this->variantId,
            'name' => $this->snapshottedName,
            'sku' => $this->snapshottedSku,
            'flavor' => $this->snapshottedFlavor,
            'nicotine' => $this->snapshottedNicotine,
            'unit_price' => $this->unitPrice->toFloat(),
            'quantity' => $this->quantity,
            'total_price' => $this->totalPrice->toFloat(),
            'total_price_formatted' => sprintf('%s %.2f', $this->totalPrice->getCurrency(), $this->totalPrice->toFloat()),
        ];
    }
}
