<?php

declare(strict_types=1);

namespace Vapehaus\Domain\ValueObject;

use Vapehaus\Domain\Exception\ValidationException;

final class Sku
{
    private string $value;

    public function __construct(string $value)
    {
        $normalized = strtoupper(trim($value));
        if ($normalized === '' || strlen($normalized) < 3) {
            throw new ValidationException("SKU must be at least 3 characters long.");
        }
        $this->value = $normalized;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function equals(Sku $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
