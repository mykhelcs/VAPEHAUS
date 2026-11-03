<?php

declare(strict_types=1);

namespace Vapehaus\Infrastructure\Repository;

use PDO;
use Vapehaus\Domain\Repository\OtpRepositoryInterface;

final class PdoOtpRepository implements OtpRepositoryInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function createOtp(string $email, string $otp, string $purpose = 'registration', int $ttlMinutes = 10): void
    {
        $normalizedEmail = strtolower(trim($email));

        // Invalidate previous unconsumed OTPs for same email & purpose
        $upd = $this->pdo->prepare('UPDATE otp_verifications SET is_consumed = 1 WHERE email = :email AND purpose = :purpose AND is_consumed = 0');
        $upd->execute([
            ':email' => $normalizedEmail,
            ':purpose' => $purpose,
        ]);

        $id = 'otp_' . bin2hex(random_bytes(8));
        $now = date('Y-m-d H:i:s');
        $expiresAt = date('Y-m-d H:i:s', time() + ($ttlMinutes * 60));

        $stmt = $this->pdo->prepare('INSERT INTO otp_verifications (id, email, otp_code, purpose, is_consumed, expires_at, created_at) VALUES (:id, :email, :code, :purpose, 0, :exp, :created)');
        $stmt->execute([
            ':id' => $id,
            ':email' => $normalizedEmail,
            ':code' => $otp,
            ':purpose' => $purpose,
            ':exp' => $expiresAt,
            ':created' => $now,
        ]);
    }

    public function verifyAndConsumeOtp(string $email, string $otp, string $purpose = 'registration'): bool
    {
        $normalizedEmail = strtolower(trim($email));
        $now = date('Y-m-d H:i:s');

        $stmt = $this->pdo->prepare('SELECT id FROM otp_verifications WHERE email = :email AND otp_code = :code AND purpose = :purpose AND is_consumed = 0 AND expires_at >= :now ORDER BY created_at DESC LIMIT 1');
        $stmt->execute([
            ':email' => $normalizedEmail,
            ':code' => trim($otp),
            ':purpose' => $purpose,
            ':now' => $now,
        ]);

        $row = $stmt->fetch();
        if (!$row) {
            return false;
        }

        // Consume OTP
        $upd = $this->pdo->prepare('UPDATE otp_verifications SET is_consumed = 1 WHERE id = :id');
        $upd->execute([':id' => $row['id']]);

        return true;
    }

    public function isOtpVerified(string $email, string $purpose = 'registration'): bool
    {
        // Checks if an OTP was consumed recently (e.g. within last 15 minutes)
        $normalizedEmail = strtolower(trim($email));
        $threshold = date('Y-m-d H:i:s', time() - 900);

        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM otp_verifications WHERE email = :email AND purpose = :purpose AND is_consumed = 1 AND created_at >= :thresh');
        $stmt->execute([
            ':email' => $normalizedEmail,
            ':purpose' => $purpose,
            ':thresh' => $threshold,
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }
}
