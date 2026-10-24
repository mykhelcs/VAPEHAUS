<?php

declare(strict_types=1);

namespace Vapehaus\Domain\Model;

use Vapehaus\Domain\Exception\InsufficientStockException;
use Vapehaus\Domain\Exception\ValidationException;
use Vapehaus\Domain\ValueObject\Money;
use Vapehaus\Domain\ValueObject\NicotineStrength;
use Vapehaus\Domain\ValueObject\Sku;

final class ProductVariant
{
    private string $id;
    private string $productId;
    private Sku $sku;
    private string $flavor;
    private NicotineStrength $nicotineStrength;
    private Money $price;
    private int $stockQuantity;
    private bool $isActive;

    public function __construct(
        string $id,
        string $productId,
        Sku $sku,
        string $flavor,
        NicotineStrength $nicotineStrength,
        Money $price,
        int $stockQuantity = 0,
        bool $isActive = true
    ) {
        $flavor = trim($flavor);
        if ($flavor === '') {
            throw new ValidationException("Variant flavor cannot be empty.");
        }
        if ($stockQuantity < 0) {
            throw new ValidationException("Stock quantity cannot be negative.");
        }

        $this->id = $id;
        $this->productId = $productId;
        $this->sku = $sku;
        $this->flavor = $flavor;
        $this->nicotineStrength = $nicotineStrength;
        $this->price = $price;
        $this->stockQuantity = $stockQuantity;
        $this->isActive = $isActive;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getProductId(): string
    {
        return $this->productId;
    }

    public function getSku(): Sku
    {
        return $this->sku;
    }

    public function getFlavor(): string
    {
        return $this->flavor;
    }

    public function getNicotineStrength(): NicotineStrength
    {
        return $this->nicotineStrength;
    }

    public function getPrice(): Money
    {
        return $this->price;
    }

    public function getStockQuantity(): int
    {
        return $this->stockQuantity;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function hasSufficientStock(int $requested): bool
    {
        return $this->stockQuantity >= $requested;
    }

    public function decrementStock(int $quantity): void
    {
        if ($quantity <= 0) {
            throw new ValidationException("Deduction quantity must be greater than zero.");
        }
        if (!$this->hasSufficientStock($quantity)) {
            throw new InsufficientStockException($this->sku->getValue(), $quantity, $this->stockQuantity);
        }
        $this->stockQuantity -= $quantity;
    }

    public function incrementStock(int $quantity): void
    {
        if ($quantity <= 0) {
            throw new ValidationException("Increment quantity must be greater than zero.");
        }
        $this->stockQuantity += $quantity;
    }

    public function updatePrice(Money $newPrice): void
    {
        $this->price = $newPrice;
    }

    public function updateDetails(string $flavor, NicotineStrength $nicotine, Money $price, int $stock, bool $isActive): void
    {
        $flavor = trim($flavor);
        if ($flavor === '') {
            throw new ValidationException("Variant flavor cannot be empty.");
        }
        if ($stock < 0) {
            throw new ValidationException("Stock cannot be negative.");
        }
        $this->flavor = $flavor;
        $this->nicotineStrength = $nicotine;
        $this->price = $price;
        $this->stockQuantity = $stock;
        $this->isActive = $isActive;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->productId,
            'sku' => $this->sku->getValue(),
            'flavor' => $this->flavor,
            'nicotine_strength' => $this->nicotineStrength->getValue(),
            'price' => $this->price->toFloat(),
            'price_formatted' => sprintf('%s %.2f', $this->price->getCurrency(), $this->price->toFloat()),
            'stock_quantity' => $this->stockQuantity,
            'in_stock' => $this->stockQuantity > 0,
            'is_active' => $this->isActive,
        ];
    }
}
