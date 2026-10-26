<?php

declare(strict_types=1);

namespace Vapehaus\Execution\Auth;

use Vapehaus\Domain\Repository\SessionRepositoryInterface;

final class LogoutUserExecution
{
    private ?SessionRepositoryInterface $sessionRepository;

    public function __construct(?SessionRepositoryInterface $sessionRepository = null)
    {
        $this->sessionRepository = $sessionRepository;
    }

    /**
     * @return array<string, mixed>
     */
    public function execute(?string $token, ?string $userId = null): array
    {
        if ($this->sessionRepository !== null && $token !== null && $token !== '') {
            $this->sessionRepository->deleteByToken($token);
        }

        return ['logged_out' => true];
    }
}
