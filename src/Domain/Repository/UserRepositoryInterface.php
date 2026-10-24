<?php

declare(strict_types=1);

namespace Vapehaus\Domain\Repository;

use Vapehaus\Domain\Model\User;

interface UserRepositoryInterface
{
    public function save(User $user): void;

    public function findById(string $id): ?User;

    public function findByEmail(string $email): ?User;

    /**
     * @return array<int, User>
     */
    public function findAll(int $limit = 50, int $offset = 0): array;

    public function count(): int;
}
