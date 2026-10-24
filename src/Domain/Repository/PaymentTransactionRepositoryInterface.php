<?php

declare(strict_types=1);

namespace Vapehaus\Domain\Repository;

use Vapehaus\Domain\Model\PaymentTransaction;

interface PaymentTransactionRepositoryInterface
{
    public function save(PaymentTransaction $transaction): void;

    public function findByReference(string $reference): ?PaymentTransaction;

    /**
     * @return array<int, PaymentTransaction>
     */
    public function findByOrderId(string $orderId): array;
}
