<?php

declare(strict_types=1);

namespace Vapehaus\Domain\Exception;

class UnderageException extends DomainException
{
    protected string $errorCode = 'UNDERAGE_RESTRICTION';

    public function __construct(int $minAge, int $actualAge)
    {
        parent::__construct(sprintf("Customer must be at least %d years old to purchase vape products. Calculated age: %d.", $minAge, $actualAge));
    }
}
