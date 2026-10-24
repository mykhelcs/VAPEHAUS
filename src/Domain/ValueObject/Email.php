<?php

declare(strict_types=1);

namespace Vapehaus\Domain\ValueObject;

use Vapehaus\Domain\Exception\ValidationException;

final class Email
{
    private string $value;

    public function __construct(string $value)
    {
        $normalized = strtolower(trim($value));
        if (!filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
            throw new ValidationException("Invalid email address: {$value}");
        }
        $this->value = $normalized;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function equals(Email $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
