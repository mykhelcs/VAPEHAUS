<?php

declare(strict_types=1);

namespace Vapehaus\Domain\ValueObject;

use Vapehaus\Domain\Exception\ValidationException;

final class OrderStatus
{
    public const PENDING = 'pending';
    public const PAID = 'paid';
    public const PROCESSING = 'processing';
    public const SHIPPED = 'shipped';
    public const DELIVERED = 'delivered';
    public const CANCELLED = 'cancelled';

    private const ALLOWED = [
        self::PENDING,
        self::PAID,
        self::PROCESSING,
        self::SHIPPED,
        self::DELIVERED,
        self::CANCELLED,
    ];

    private string $value;

    public function __construct(string $value)
    {
        $normalized = strtolower(trim($value));
        if (!in_array($normalized, self::ALLOWED, true)) {
            throw new ValidationException("Invalid order status: {$value}");
        }
        $this->value = $normalized;
    }

    public static function pending(): self
    {
        return new self(self::PENDING);
    }

    public static function paid(): self
    {
        return new self(self::PAID);
    }

    public static function processing(): self
    {
        return new self(self::PROCESSING);
    }

    public static function shipped(): self
    {
        return new self(self::SHIPPED);
    }

    public static function delivered(): self
    {
        return new self(self::DELIVERED);
    }

    public static function cancelled(): self
    {
        return new self(self::CANCELLED);
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function canCancel(): bool
    {
        return in_array($this->value, [self::PENDING, self::PAID], true);
    }

    public function canTransitionTo(string $target): bool
    {
        $target = strtolower(trim($target));
        if ($this->value === $target) {
            return true;
        }

        return match ($this->value) {
            self::PENDING => in_array($target, [self::PAID, self::CANCELLED], true),
            self::PAID => in_array($target, [self::PROCESSING, self::CANCELLED], true),
            self::PROCESSING => in_array($target, [self::SHIPPED, self::CANCELLED], true),
            self::SHIPPED => in_array($target, [self::DELIVERED], true),
            self::DELIVERED, self::CANCELLED => false,
            default => false,
        };
    }

    public function equals(OrderStatus $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
