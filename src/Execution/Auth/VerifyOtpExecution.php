<?php

declare(strict_types=1);

namespace Vapehaus\Execution\Auth;

use Vapehaus\Domain\Exception\ValidationException;
use Vapehaus\Domain\Repository\OtpRepositoryInterface;

final class VerifyOtpExecution
{
    private OtpRepositoryInterface $otpRepository;

    public function __construct(OtpRepositoryInterface $otpRepository)
    {
        $this->otpRepository = $otpRepository;
    }

    /**
     * @param array{email?: string, otp?: string, code?: string, purpose?: string} $data
     * @return array<string, mixed>
     */
    public function execute(array $data): array
    {
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $otp = trim((string) ($data['otp'] ?? $data['code'] ?? ''));
        $purpose = strtolower(trim((string) ($data['purpose'] ?? 'registration')));

        if ($email === '' || $otp === '') {
            throw new ValidationException('Email and OTP code are required.');
        }

        $valid = $this->otpRepository->verifyAndConsumeOtp($email, $otp, $purpose);
        if (!$valid) {
            throw new ValidationException('Invalid or expired OTP code.');
        }

        return [
            'verified' => true,
            'email' => $email,
            'purpose' => $purpose,
            'message' => 'OTP verified successfully.',
        ];
    }
}
