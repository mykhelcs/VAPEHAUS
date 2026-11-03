<?php

declare(strict_types=1);

namespace Vapehaus\Domain\Repository;

interface OtpRepositoryInterface
{
    public function createOtp(string $email, string $otp, string $purpose = 'registration', int $ttlMinutes = 10): void;

    public function verifyAndConsumeOtp(string $email, string $otp, string $purpose = 'registration'): bool;

    public function isOtpVerified(string $email, string $purpose = 'registration'): bool;
}
