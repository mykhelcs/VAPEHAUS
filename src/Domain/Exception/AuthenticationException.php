<?php

declare(strict_types=1);

namespace Vapehaus\Domain\Exception;

class AuthenticationException extends DomainException
{
    protected string $errorCode = 'AUTHENTICATION_REQUIRED';
}
