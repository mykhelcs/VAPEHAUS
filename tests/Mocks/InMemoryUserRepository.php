<?php

declare(strict_types=1);

namespace Vapehaus\Tests\Mocks;

use Vapehaus\Domain\Model\User;
use Vapehaus\Domain\Repository\UserRepositoryInterface;

final class InMemoryUserRepository implements UserRepositoryInterface
{
    /** @var array<string, User> */
    private array $users = [];

    public function save(User $user): void
    {
        $this->users[$user->getId()] = $user;
    }

    public function findById(string $id): ?User
    {
        return $this->users[$id] ?? null;
    }

    public function findByEmail(string $email): ?User
    {
        $email = strtolower(trim($email));
        foreach ($this->users as $user) {
            if ($user->getEmail()->getValue() === $email) {
                return $user;
            }
        }
        return null;
    }

    public function findAll(int $limit = 50, int $offset = 0): array
    {
        return array_slice(array_values($this->users), $offset, $limit);
    }

    public function count(): int
    {
        return count($this->users);
    }
}
