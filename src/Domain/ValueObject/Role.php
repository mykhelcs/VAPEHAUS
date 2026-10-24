<?php

declare(strict_types=1);

namespace Vapehaus\Domain\ValueObject;

use Vapehaus\Domain\Exception\ValidationException;

final class Role
{
    public const CUSTOMER = 'customer';
    public const ADMIN = 'admin';

    private const ALLOWED = [
        self::CUSTOMER,
        self::ADMIN,
    ];

    private string $value;

    public function __construct(string $value)
    {
        $normalized = strtolower(trim($value));
        if (!in_array($normalized, self::ALLOWED, true)) {
            throw new ValidationException("Invalid role: {$value}. Allowed: " . implode(', ', self::ALLOWED));
        }
        $this->value = $normalized;
    }

    public static function customer(): self
    {
        return new self(self::CUSTOMER);
    }

    public static function admin(): self
    {
        return new self(self::ADMIN);
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function isAdmin(): bool
    {
        return $this->value === self::ADMIN;
    }

    public function isCustomer(): bool
    {
        return $this->value === self::CUSTOMER;
    }

    public function equals(Role $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
