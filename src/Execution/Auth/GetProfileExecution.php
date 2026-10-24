<?php

declare(strict_types=1);

namespace Vapehaus\Execution\Auth;

use Vapehaus\Domain\Exception\EntityNotFoundException;
use Vapehaus\Domain\Repository\UserRepositoryInterface;

final class GetProfileExecution
{
    private UserRepositoryInterface $userRepository;

    public function __construct(UserRepositoryInterface $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    /**
     * @return array<string, mixed>
     */
    public function execute(string $userId): array
    {
        $user = $this->userRepository->findById($userId);
        if ($user === null) {
            throw new EntityNotFoundException('User', $userId);
        }

        return $user->toArray();
    }
}
