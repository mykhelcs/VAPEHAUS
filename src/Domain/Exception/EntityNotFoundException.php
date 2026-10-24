<?php

declare(strict_types=1);

namespace Vapehaus\Domain\Exception;

class EntityNotFoundException extends DomainException
{
    protected string $errorCode = 'NOT_FOUND';

    public function __construct(string $entity, string $identifier)
    {
        parent::__construct(sprintf("%s with identifier '%s' was not found.", $entity, $identifier));
    }
}
