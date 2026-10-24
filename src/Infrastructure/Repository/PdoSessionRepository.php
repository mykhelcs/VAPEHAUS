<?php

declare(strict_types=1);

namespace Vapehaus\Infrastructure\Repository;

use PDO;
use Vapehaus\Domain\Model\Session;
use Vapehaus\Domain\Repository\SessionRepositoryInterface;

final class PdoSessionRepository implements SessionRepositoryInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function save(Session $session): void
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM sessions WHERE token = :token');
        $stmt->execute([':token' => $session->getToken()]);
        $exists = (int) $stmt->fetchColumn() > 0;

        if ($exists) {
            $stmt = $this->pdo->prepare('UPDATE sessions SET
                user_id = :uid,
                ip_address = :ip,
                user_agent = :ua,
                expires_at = :exp
                WHERE token = :token');
            $stmt->execute([
                ':token' => $session->getToken(),
                ':uid' => $session->getUserId(),
                ':ip' => $session->getIpAddress(),
                ':ua' => $session->getUserAgent(),
                ':exp' => $session->getExpiresAt(),
            ]);
        } else {
            $stmt = $this->pdo->prepare('INSERT INTO sessions (token, user_id, ip_address, user_agent, expires_at, created_at)
                VALUES (:token, :uid, :ip, :ua, :exp, :created)');
            $stmt->execute([
                ':token' => $session->getToken(),
                ':uid' => $session->getUserId(),
                ':ip' => $session->getIpAddress(),
                ':ua' => $session->getUserAgent(),
                ':exp' => $session->getExpiresAt(),
                ':created' => $session->getCreatedAt(),
            ]);
        }
    }

    public function findByToken(string $token): ?Session
    {
        $stmt = $this->pdo->prepare('SELECT * FROM sessions WHERE token = :token');
        $stmt->execute([':token' => $token]);
        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        return new Session(
            $row['token'],
            $row['user_id'],
            $row['ip_address'],
            $row['user_agent'],
            $row['expires_at'],
            $row['created_at']
        );
    }

    public function deleteByToken(string $token): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM sessions WHERE token = :token');
        $stmt->execute([':token' => $token]);
    }

    public function deleteByUserId(string $userId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM sessions WHERE user_id = :uid');
        $stmt->execute([':uid' => $userId]);
    }
}
