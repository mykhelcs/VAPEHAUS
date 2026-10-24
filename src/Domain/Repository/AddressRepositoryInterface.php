<?php

declare(strict_types=1);

namespace Vapehaus\Domain\Repository;

use Vapehaus\Domain\Model\UserAddress;

interface AddressRepositoryInterface
{
    public function save(UserAddress $address): void;

    public function findById(string $id): ?UserAddress;

    /**
     * @return array<int, UserAddress>
     */
    public function findByUserId(string $userId): array;

    public function findDefaultByUserId(string $userId): ?UserAddress;

    public function clearDefault(string $userId): void;

    public function delete(string $id): void;
}
