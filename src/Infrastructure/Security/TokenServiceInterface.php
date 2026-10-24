<?php

declare(strict_types=1);

namespace Vapehaus\Infrastructure\Security;

interface TokenServiceInterface
{
    /**
     * @param array<string, mixed> $claims
     */
    public function generateToken(array $claims, int $ttlSeconds = 86400): string;

    /**
     * @return array<string, mixed>|null Returns claims array if valid, null otherwise
     */
    public function validateToken(string $token): ?array;
}
