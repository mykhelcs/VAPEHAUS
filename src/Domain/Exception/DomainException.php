<?php

declare(strict_types=1);

namespace Vapehaus\Domain\Exception;

use Exception;

class DomainException extends Exception
{
    protected string $errorCode = 'DOMAIN_ERROR';

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }
}
