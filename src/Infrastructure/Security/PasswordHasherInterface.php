<?php

declare(strict_types=1);

namespace Vapehaus\Infrastructure\Security;

interface PasswordHasherInterface
{
    public function hash(string $plainPassword): string;

    public function verify(string $plainPassword, string $hash): bool;
}
