<?php

declare(strict_types=1);

namespace Vapehaus\Domain\ValueObject;

use Vapehaus\Domain\Exception\ValidationException;

final class NicotineStrength
{
    private string $value;

    public function __construct(string $value)
    {
        $normalized = strtolower(trim($value));
        if ($normalized === '') {
            throw new ValidationException("Nicotine strength cannot be empty (e.g. '0mg', '3mg', '20mg', '50mg').");
        }
        $this->value = $normalized;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function equals(NicotineStrength $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
