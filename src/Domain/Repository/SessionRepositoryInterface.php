<?php

declare(strict_types=1);

namespace Vapehaus\Domain\Repository;

use Vapehaus\Domain\Model\Session;

interface SessionRepositoryInterface
{
    public function save(Session $session): void;

    public function findByToken(string $token): ?Session;

    public function deleteByToken(string $token): void;

    public function deleteByUserId(string $userId): void;
}
