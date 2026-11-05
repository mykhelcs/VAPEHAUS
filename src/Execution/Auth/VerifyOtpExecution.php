<?php

declare(strict_types=1);

namespace Vapehaus\Execution\Auth;

use Vapehaus\Domain\Exception\ValidationException;
use Vapehaus\Domain\Repository\OtpRepositoryInterface;
use Vapehaus\Infrastructure\Repository\HmacOtpRepository;

final class VerifyOtpExecution
{
    private OtpRepositoryInterface $otpRepository;

    public function __construct(OtpRepositoryInterface $otpRepository)
    {
        $this->otpRepository = $otpRepository;
    }

    /**
     * @param array{email?: string, otp?: string, code?: string, purpose?: string, otp_token?: string} $data
     * @return array<string, mixed>
     */
    public function execute(array $data): array
    {
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $otp = trim((string) ($data['otp'] ?? $data['code'] ?? ''));
        $purpose = strtolower(trim((string) ($data['purpose'] ?? 'registration')));
        $otpToken = trim((string) ($data['otp_token'] ?? ''));

        if ($email === '' || $otp === '') {
            throw new ValidationException('Email and OTP code are required.');
        }

        // For HMAC-based verification, set the token from the request
        if ($this->otpRepository instanceof HmacOtpRepository && $otpToken !== '') {
            $this->otpRepository->setProofToken($otpToken);
        }

        $valid = $this->otpRepository->verifyAndConsumeOtp($email, $otp, $purpose);
        if (!$valid) {
            throw new ValidationException('Invalid or expired OTP code.');
        }

        $result = [
            'verified' => true,
            'email' => $email,
            'purpose' => $purpose,
            'message' => 'OTP verified successfully.',
        ];

        // Return a proof token so the client can prove verification during registration
        if ($this->otpRepository instanceof HmacOtpRepository) {
            $result['otp_proof'] = $this->otpRepository->buildProofToken($email, $purpose);
        }

        return $result;
    }
}
