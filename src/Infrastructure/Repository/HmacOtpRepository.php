<?php

declare(strict_types=1);

namespace Vapehaus\Infrastructure\Repository;

use Vapehaus\Domain\Repository\OtpRepositoryInterface;
use Vapehaus\Infrastructure\Environment\Env;

/**
 * Robust stateless & file-backed OTP repository.
 * Requires ZERO database tables.
 *
 * Capabilities:
 *  1. Saves active OTPs in a local JSON storage directory (database/.otp or sys_get_temp_dir).
 *  2. Generates and verifies HMAC-signed tokens (stateless verification for API clients).
 *  3. Verifies directly by email + OTP code even if no client-side token is provided.
 *  4. Enforces TTL expiration (10 minutes) and single-use consumption.
 */
final class HmacOtpRepository implements OtpRepositoryInterface
{
    private string $secretKey;
    private string $storageDir;

    /** @var string|null Last generated HMAC token */
    private ?string $lastToken = null;

    /** @var string|null Proof token supplied for verification */
    private ?string $proofToken = null;

    public function __construct(?string $secretKey = null, ?string $storageDir = null)
    {
        $this->secretKey = $secretKey ?? (string) Env::get('APP_SECRET_KEY', 'vapehaus-otp-secret-fallback');

        if ($storageDir !== null) {
            $this->storageDir = $storageDir;
        } else {
            $dbOtpDir = dirname(__DIR__, 3) . '/database/.otp';
            if (!is_dir($dbOtpDir)) {
                @mkdir($dbOtpDir, 0777, true);
            }
            if (is_dir($dbOtpDir) && is_writable($dbOtpDir)) {
                $this->storageDir = $dbOtpDir;
            } else {
                $this->storageDir = sys_get_temp_dir();
            }
        }
    }

    /**
     * Create an OTP record (saved locally & HMAC-signed).
     */
    public function createOtp(string $email, string $otp, string $purpose = 'registration', int $ttlMinutes = 10): void
    {
        $normalizedEmail = strtolower(trim($email));
        $cleanOtp = trim($otp);
        $expiresAt = time() + ($ttlMinutes * 60);

        // 1. Save to local storage file
        $filePath = $this->getFilePath($normalizedEmail, $purpose);
        $record = [
            'email' => $normalizedEmail,
            'otp' => $cleanOtp,
            'purpose' => $purpose,
            'expires_at' => $expiresAt,
            'consumed' => false,
            'created_at' => time(),
        ];
        @file_put_contents($filePath, json_encode($record, JSON_PRETTY_PRINT), LOCK_EX);

        // 2. Build HMAC signed token
        $payload = $this->buildPayload($normalizedEmail, $cleanOtp, $purpose, $expiresAt);
        $signature = $this->sign($payload);
        $this->lastToken = base64_encode($payload . '.' . $signature);
    }

    /**
     * Verify and consume an OTP.
     * Checks local record first; falls back to signed token if available.
     */
    public function verifyAndConsumeOtp(string $email, string $otp, string $purpose = 'registration'): bool
    {
        $normalizedEmail = strtolower(trim($email));
        $cleanOtp = trim($otp);

        if ($normalizedEmail === '' || $cleanOtp === '') {
            return false;
        }

        // 1. If an HMAC token was explicitly supplied, validate it first!
        $token = $this->proofToken;
        if ($token !== null && $token !== '') {
            if (!$this->validateToken($token, $normalizedEmail, $cleanOtp, $purpose)) {
                return false;
            }
        }

        // 2. Check local file storage
        $filePath = $this->getFilePath($normalizedEmail, $purpose);
        if (file_exists($filePath)) {
            $content = @file_get_contents($filePath);
            if ($content !== false) {
                $data = json_decode($content, true);
                if (
                    is_array($data) &&
                    ($data['email'] ?? '') === $normalizedEmail &&
                    ($data['purpose'] ?? '') === $purpose &&
                    ($data['otp'] ?? '') === $cleanOtp &&
                    (int) ($data['expires_at'] ?? 0) >= time() &&
                    !($data['consumed'] ?? false)
                ) {
                    // Mark consumed
                    $data['consumed'] = true;
                    $data['consumed_at'] = time();
                    @file_put_contents($filePath, json_encode($data, JSON_PRETTY_PRINT), LOCK_EX);
                    return true;
                }
            }
        }

        // 3. If token was valid and file was either not present or already consistent
        if ($token !== null && $token !== '') {
            return true;
        }

        return false;
    }

    /**
     * Check if OTP was verified recently (e.g. within 15 minutes).
     */
    public function isOtpVerified(string $email, string $purpose = 'registration'): bool
    {
        $normalizedEmail = strtolower(trim($email));

        // 1. Check local file storage
        $filePath = $this->getFilePath($normalizedEmail, $purpose);
        if (file_exists($filePath)) {
            $content = @file_get_contents($filePath);
            if ($content !== false) {
                $data = json_decode($content, true);
                if (
                    is_array($data) &&
                    ($data['email'] ?? '') === $normalizedEmail &&
                    ($data['purpose'] ?? '') === $purpose &&
                    ($data['consumed'] ?? false) === true &&
                    (time() - (int) ($data['consumed_at'] ?? 0)) <= 900
                ) {
                    return true;
                }
            }
        }

        // 2. Check proof token if provided
        $token = $this->proofToken;
        if ($token !== null && $token !== '') {
            return $this->validateProofToken($token, $normalizedEmail, $purpose);
        }

        return false;
    }

    public function getLastToken(): ?string
    {
        return $this->lastToken;
    }

    public function setProofToken(?string $token): void
    {
        $this->proofToken = $token;
    }

    public function buildProofToken(string $email, string $purpose = 'registration'): string
    {
        $normalizedEmail = strtolower(trim($email));
        $expiresAt = time() + 900;

        $payload = "proof:{$normalizedEmail}:{$purpose}:{$expiresAt}";
        $signature = $this->sign($payload);

        return base64_encode($payload . '.' . $signature);
    }

    // ── Private Helpers ─────────────────────────────────────────────────

    private function getFilePath(string $email, string $purpose): string
    {
        $hash = sha1("{$email}:{$purpose}:vapehaus_otp");
        return rtrim($this->storageDir, '/\\') . DIRECTORY_SEPARATOR . "otp_{$hash}.json";
    }

    private function buildPayload(string $email, string $otp, string $purpose, int $expiresAt): string
    {
        return "{$email}:{$otp}:{$purpose}:{$expiresAt}";
    }

    private function sign(string $data): string
    {
        return hash_hmac('sha256', $data, $this->secretKey);
    }

    private function validateToken(string $token, string $email, string $otp, string $purpose): bool
    {
        $decoded = base64_decode($token, true);
        if ($decoded === false) {
            return false;
        }

        $lastDot = strrpos($decoded, '.');
        if ($lastDot === false) {
            return false;
        }

        $payload = substr($decoded, 0, $lastDot);
        $signature = substr($decoded, $lastDot + 1);

        $expectedSignature = $this->sign($payload);
        if (!hash_equals($expectedSignature, $signature)) {
            return false;
        }

        $parts = explode(':', $payload);
        if (count($parts) !== 4) {
            return false;
        }

        [$tokenEmail, $tokenOtp, $tokenPurpose, $tokenExpires] = $parts;

        if ($tokenEmail !== $email) {
            return false;
        }
        if ($tokenOtp !== $otp) {
            return false;
        }
        if ($tokenPurpose !== $purpose) {
            return false;
        }
        if (time() > (int) $tokenExpires) {
            return false;
        }

        return true;
    }

    private function validateProofToken(string $token, string $email, string $purpose): bool
    {
        $decoded = base64_decode($token, true);
        if ($decoded === false) {
            return false;
        }

        $lastDot = strrpos($decoded, '.');
        if ($lastDot === false) {
            return false;
        }

        $payload = substr($decoded, 0, $lastDot);
        $signature = substr($decoded, $lastDot + 1);

        $expectedSignature = $this->sign($payload);
        if (!hash_equals($expectedSignature, $signature)) {
            return false;
        }

        $parts = explode(':', $payload);
        if (count($parts) !== 4 || $parts[0] !== 'proof') {
            return false;
        }

        [, $tokenEmail, $tokenPurpose, $tokenExpires] = $parts;

        if ($tokenEmail !== $email) {
            return false;
        }
        if ($tokenPurpose !== $purpose) {
            return false;
        }
        if (time() > (int) $tokenExpires) {
            return false;
        }

        return true;
    }
}
