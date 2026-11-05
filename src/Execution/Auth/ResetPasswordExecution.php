<?php

declare(strict_types=1);

namespace Vapehaus\Execution\Auth;

use Vapehaus\Domain\Exception\EntityNotFoundException;
use Vapehaus\Domain\Exception\ValidationException;
use Vapehaus\Domain\Repository\OtpRepositoryInterface;
use Vapehaus\Domain\Repository\UserRepositoryInterface;
use Vapehaus\Domain\ValueObject\Email;
use Vapehaus\Infrastructure\Repository\HmacOtpRepository;
use Vapehaus\Infrastructure\Security\PasswordHasherInterface;

final class ResetPasswordExecution
{
    private UserRepositoryInterface $userRepository;
    private OtpRepositoryInterface $otpRepository;
    private PasswordHasherInterface $passwordHasher;

    public function __construct(
        UserRepositoryInterface $userRepository,
        OtpRepositoryInterface $otpRepository,
        PasswordHasherInterface $passwordHasher
    ) {
        $this->userRepository = $userRepository;
        $this->otpRepository = $otpRepository;
        $this->passwordHasher = $passwordHasher;
    }

    /**
     * @param array{email?: string, otp?: string, code?: string, password?: string} $data
     * @return array<string, mixed>
     */
    public function execute(array $data): array
    {
        $rawEmail = strtolower(trim((string) ($data['email'] ?? '')));
        $otp = trim((string) ($data['otp'] ?? $data['code'] ?? ''));
        $otpToken = trim((string) ($data['otp_token'] ?? ''));
        $otpProof = trim((string) ($data['otp_proof'] ?? ''));
        $newPassword = (string) ($data['password'] ?? $data['new_password'] ?? '');

        if ($rawEmail === '') {
            throw new ValidationException('Email is required.');
        }
        if (strlen($newPassword) < 6) {
            throw new ValidationException('Password must be at least 6 characters long.');
        }

        $email = new Email($rawEmail);
        $user = $this->userRepository->findByEmail($email->getValue());
        if ($user === null) {
            throw new EntityNotFoundException('User', $email->getValue());
        }

        // Verify OTP: either passed directly with the request, or verified recently
        $verified = false;

        // 1. If an OTP code was provided directly, verify it
        if ($otp !== '') {
            if ($this->otpRepository instanceof HmacOtpRepository && $otpToken !== '') {
                $this->otpRepository->setProofToken($otpToken);
            }
            $verified = $this->otpRepository->verifyAndConsumeOtp($email->getValue(), $otp, 'password_reset');
        }
        // 2. Or if a proof token was provided
        elseif ($otpProof !== '') {
            if ($this->otpRepository instanceof HmacOtpRepository) {
                $this->otpRepository->setProofToken($otpProof);
            }
            $verified = $this->otpRepository->isOtpVerified($email->getValue(), 'password_reset');
        }
        // 3. Fallback to check if already verified recently
        else {
            $verified = $this->otpRepository->isOtpVerified($email->getValue(), 'password_reset');
        }

        if (!$verified) {
            throw new ValidationException('Valid OTP is required to reset password.');
        }

        // Hash new password & update user
        $newHash = $this->passwordHasher->hash($newPassword);
        $updatedUser = new \Vapehaus\Domain\Model\User(
            $user->getId(),
            $user->getEmail(),
            $newHash,
            $user->getFirstName(),
            $user->getLastName(),
            $user->getPhone(),
            $user->getRole(),
            $user->getBirthDate(),
            $user->isAgeVerified(),
            $user->getCreatedAt()
        );

        $this->userRepository->save($updatedUser);

        return [
            'success' => true,
            'message' => 'Password has been successfully updated. You can now log in with your new password.',
        ];
    }
}
