<?php

declare(strict_types=1);

namespace Vapehaus\Domain\Exception;

class InsufficientStockException extends DomainException
{
    protected string $errorCode = 'INSUFFICIENT_STOCK';

    public function __construct(string $sku, int $requested, int $available)
    {
        parent::__construct(sprintf("Insufficient stock for SKU '%s'. Requested: %d, Available: %d.", $sku, $requested, $available));
    }
}
