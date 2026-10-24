<?php

declare(strict_types=1);

namespace Vapehaus\Domain\Repository;

use Vapehaus\Domain\Model\KycVerification;

interface KycRepositoryInterface
{
    public function save(KycVerification $kyc): void;

    public function findById(string $id): ?KycVerification;

    public function findLatestByUserId(string $userId): ?KycVerification;

    /**
     * @param array{status?: string} $filters
     * @return array<int, KycVerification>
     */
    public function findAll(array $filters = []): array;
}
