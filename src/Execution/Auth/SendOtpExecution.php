<?php

declare(strict_types=1);

namespace Vapehaus\Execution\Auth;

use Vapehaus\Domain\Exception\ValidationException;
use Vapehaus\Domain\Repository\OtpRepositoryInterface;
use Vapehaus\Domain\Repository\UserRepositoryInterface;
use Vapehaus\Domain\ValueObject\Email;
use Vapehaus\Infrastructure\Mailer\SmtpMailer;
use Vapehaus\Infrastructure\Repository\HmacOtpRepository;

final class SendOtpExecution
{
    private OtpRepositoryInterface $otpRepository;
    private ?UserRepositoryInterface $userRepository;
    private SmtpMailer $mailer;

    public function __construct(
        OtpRepositoryInterface $otpRepository,
        SmtpMailer $mailer,
        ?UserRepositoryInterface $userRepository = null
    ) {
        $this->otpRepository = $otpRepository;
        $this->mailer = $mailer;
        $this->userRepository = $userRepository;
    }

    /**
     * @param array{email?: string, purpose?: string} $data
     * @return array<string, mixed>
     */
    public function execute(array $data): array
    {
        $rawEmail = trim((string) ($data['email'] ?? ''));
        if ($rawEmail === '') {
            throw new ValidationException('Email is required to request OTP.');
        }

        $email = new Email($rawEmail);
        $purpose = strtolower(trim((string) ($data['purpose'] ?? 'registration')));
        if (!in_array($purpose, ['registration', 'password_reset'], true)) {
            $purpose = 'registration';
        }

        // Check user existence depending on purpose
        if ($this->userRepository !== null) {
            $existing = $this->userRepository->findByEmail($email->getValue());
            if ($purpose === 'registration' && $existing !== null) {
                throw new ValidationException("Email '{$email->getValue()}' is already registered.");
            }
            if ($purpose === 'password_reset' && $existing === null) {
                // Return success to prevent email enumeration, but do not send
                return [
                    'sent' => true,
                    'message' => 'If this email is registered, an OTP verification code has been dispatched.',
                ];
            }
        }

        // Generate 6-digit numeric OTP
        $otp = (string) random_int(100000, 999999);
        $this->otpRepository->createOtp($email->getValue(), $otp, $purpose, 10);

        // Subject & email body
        $title = $purpose === 'registration' ? 'Verify Your Account' : 'Reset Your Password';
        $subject = "VAPEHAUS — {$otp} is your verification code";

        $htmlBody = <<<HTML
<div style="font-family: Arial, sans-serif; background-color: #0b0f19; color: #f3f4f6; padding: 32px; border-radius: 8px; max-width: 500px; margin: auto;">
    <h2 style="color: #6366f1; margin-bottom: 8px;">VAPEHAUS</h2>
    <h3 style="margin-top: 0; color: #ffffff;">{$title}</h3>
    <p style="color: #9ca3af; font-size: 14px; line-height: 1.5;">
        Use the following one-time password (OTP) to complete your {$purpose} request. This code will expire in 10 minutes.
    </p>
    <div style="background-color: #1e293b; padding: 18px; border-radius: 6px; text-align: center; margin: 24px 0;">
        <span style="font-size: 32px; font-weight: bold; letter-spacing: 6px; color: #38bdf8;">{$otp}</span>
    </div>
    <p style="color: #6b7280; font-size: 12px; margin-top: 24px; border-top: 1px solid #374151; padding-top: 16px;">
        If you did not make this request, you can safely ignore this email.
    </p>
</div>
HTML;

        $sent = $this->mailer->send($email->getValue(), $subject, $htmlBody);

        // In local development or debug mode, log OTP to error_log and return in response
        $isLocalOrDebug = (strtolower((string) (\Vapehaus\Infrastructure\Environment\Env::get('APP_ENV') ?? '')) === 'local')
            || (bool) (\Vapehaus\Infrastructure\Environment\Env::get('APP_DEBUG') ?? false);

        if ($isLocalOrDebug) {
            error_log("[VAPEHAUS LOCAL DEV OTP] Code for {$email->getValue()} ({$purpose}): {$otp}");
        }

        $result = [
            'sent' => $sent || $isLocalOrDebug,
            'message' => 'OTP has been dispatched to your email.',
            'expires_in' => 600,
        ];

        // Include the HMAC token so the client can send it back for stateless verification
        if ($this->otpRepository instanceof HmacOtpRepository) {
            $result['otp_token'] = $this->otpRepository->getLastToken();
        }

        if ($isLocalOrDebug) {
            $result['debug_otp'] = $otp;
        }

        return $result;
    }
}
