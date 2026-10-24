<?php

declare(strict_types=1);

namespace Vapehaus\Domain\Model;

use DateTimeImmutable;
use Vapehaus\Domain\Exception\ValidationException;
use Vapehaus\Domain\ValueObject\Money;

final class Coupon
{
    public const TYPE_PERCENTAGE = 'percentage';
    public const TYPE_FIXED = 'fixed';
    public const TYPE_FREE_SHIPPING = 'free_shipping';

    private string $id;
    private string $code;
    private string $type;
    private float $value;
    private Money $minSpend;
    private ?Money $maxDiscount;
    private int $usageLimit;
    private int $timesUsed;
    private ?string $expiresAt;
    private bool $isActive;
    private string $createdAt;

    public function __construct(
        string $id,
        string $code,
        string $type,
        float $value,
        Money $minSpend,
        ?Money $maxDiscount = null,
        int $usageLimit = 0,
        int $timesUsed = 0,
        ?string $expiresAt = null,
        bool $isActive = true,
        ?string $createdAt = null
    ) {
        $code = strtoupper(trim($code));
        $type = strtolower(trim($type));

        if ($code === '') {
            throw new ValidationException('Coupon code cannot be empty.');
        }
        if (!in_array($type, [self::TYPE_PERCENTAGE, self::TYPE_FIXED, self::TYPE_FREE_SHIPPING], true)) {
            throw new ValidationException("Invalid coupon type: {$type}. Allowed: percentage, fixed, free_shipping.");
        }
        if ($value <= 0) {
            throw new ValidationException('Coupon discount value must be greater than zero.');
        }

        $this->id = $id;
        $this->code = $code;
        $this->type = $type;
        $this->value = $value;
        $this->minSpend = $minSpend;
        $this->maxDiscount = $maxDiscount;
        $this->usageLimit = $usageLimit;
        $this->timesUsed = $timesUsed;
        $this->expiresAt = $expiresAt;
        $this->isActive = $isActive;
        $this->createdAt = $createdAt ?? (new DateTimeImmutable())->format('Y-m-d H:i:s');
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getValue(): float
    {
        return $this->value;
    }

    public function getMinSpend(): Money
    {
        return $this->minSpend;
    }

    public function getMaxDiscount(): ?Money
    {
        return $this->maxDiscount;
    }

    public function getUsageLimit(): int
    {
        return $this->usageLimit;
    }

    public function getTimesUsed(): int
    {
        return $this->timesUsed;
    }

    public function getExpiresAt(): ?string
    {
        return $this->expiresAt;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function isExpired(): bool
    {
        if ($this->expiresAt === null) {
            return false;
        }
        $expiry = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $this->expiresAt);
        if (!$expiry) {
            $expiry = DateTimeImmutable::createFromFormat('Y-m-d', $this->expiresAt);
        }
        return $expiry ? (new DateTimeImmutable() > $expiry) : false;
    }

    public function isUsageLimitReached(): bool
    {
        if ($this->usageLimit <= 0) {
            return false;
        }
        return $this->timesUsed >= $this->usageLimit;
    }

    public function assertCanBeApplied(Money $subtotal): void
    {
        if (!$this->isActive) {
            throw new ValidationException("Coupon '{$this->code}' is inactive.");
        }
        if ($this->isExpired()) {
            throw new ValidationException("Coupon '{$this->code}' has expired.");
        }
        if ($this->isUsageLimitReached()) {
            throw new ValidationException("Coupon '{$this->code}' has reached its maximum redemption limit.");
        }
        if ($subtotal->getAmountInCents() < $this->minSpend->getAmountInCents()) {
            throw new ValidationException(sprintf(
                "Coupon '%s' requires a minimum spend of %s %.2f. Current cart: %s %.2f.",
                $this->code,
                $this->minSpend->getCurrency(),
                $this->minSpend->toFloat(),
                $subtotal->getCurrency(),
                $subtotal->toFloat()
            ));
        }
    }

    public function calculateDiscount(Money $subtotal, Money $shippingFee): Money
    {
        $this->assertCanBeApplied($subtotal);

        if ($this->type === self::TYPE_FREE_SHIPPING) {
            return $shippingFee;
        }

        if ($this->type === self::TYPE_PERCENTAGE) {
            $discountCents = (int) round($subtotal->getAmountInCents() * ($this->value / 100));
            if ($this->maxDiscount !== null && $discountCents > $this->maxDiscount->getAmountInCents()) {
                $discountCents = $this->maxDiscount->getAmountInCents();
            }
            return new Money($discountCents, $subtotal->getCurrency());
        }

        // Fixed discount
        $discountMoney = Money::fromFloat($this->value, $subtotal->getCurrency());
        if ($discountMoney->getAmountInCents() > $subtotal->getAmountInCents()) {
            return $subtotal;
        }
        return $discountMoney;
    }

    public function incrementUsage(): void
    {
        $this->timesUsed++;
    }

    public function toggleActive(): void
    {
        $this->isActive = !$this->isActive;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'type' => $this->type,
            'value' => $this->value,
            'min_spend' => $this->minSpend->toFloat(),
            'max_discount' => $this->maxDiscount?->toFloat(),
            'usage_limit' => $this->usageLimit,
            'times_used' => $this->timesUsed,
            'expires_at' => $this->expiresAt,
            'is_active' => $this->isActive,
            'is_expired' => $this->isExpired(),
            'created_at' => $this->createdAt,
        ];
    }
}
