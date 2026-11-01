<?php

declare(strict_types=1);

namespace Vapehaus\Domain\Exception;

final class InvalidEmailException extends ValidationException
{
    public function __construct(string $email)
    {
        parent::__construct("Invalid email address: {$email}", ['email' => $email]);
    }

    public function getErrorCode(): string
    {
        return 'INVALID_EMAIL';
    }
}
