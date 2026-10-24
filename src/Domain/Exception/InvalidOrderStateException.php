<?php

declare(strict_types=1);

namespace Vapehaus\Domain\Exception;

class InvalidOrderStateException extends DomainException
{
    protected string $errorCode = 'INVALID_ORDER_STATE';

    public function __construct(string $currentStatus, string $attemptedTransition)
    {
        parent::__construct(sprintf("Cannot transition order from current status '%s' to '%s'.", $currentStatus, $attemptedTransition));
    }
}
