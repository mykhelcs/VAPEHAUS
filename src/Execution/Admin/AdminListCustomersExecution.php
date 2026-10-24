<?php

declare(strict_types=1);

namespace Vapehaus\Execution\Admin;

use Vapehaus\Domain\Model\User;
use Vapehaus\Domain\Repository\UserRepositoryInterface;

final class AdminListCustomersExecution
{
    private UserRepositoryInterface $userRepository;

    public function __construct(UserRepositoryInterface $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    /**
     * @return array{customers: array<int, array<string, mixed>>, total: int, page: int, limit: int}
     */
    public function execute(int $page = 1, int $limit = 50): array
    {
        $page = max(1, $page);
        $limit = min(100, max(1, $limit));
        $offset = ($page - 1) * $limit;

        $users = $this->userRepository->findAll($limit, $offset);
        $total = $this->userRepository->count();

        return [
            'customers' => array_map(fn (User $u) => $u->toArray(), $users),
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
        ];
    }
}
