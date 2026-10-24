<?php

declare(strict_types=1);

namespace Vapehaus\Domain\Exception;

class ValidationException extends DomainException
{
    protected string $errorCode = 'VALIDATION_FAILED';
    /** @var array<int, array{field: string, issue: string}> */
    private array $details;

    /**
     * @param array<int, array{field: string, issue: string}> $details
     */
    public function __construct(string $message, array $details = [])
    {
        parent::__construct($message);
        $this->details = $details;
    }

    /**
     * @return array<int, array{field: string, issue: string}>
     */
    public function getDetails(): array
    {
        return $this->details;
    }
}
