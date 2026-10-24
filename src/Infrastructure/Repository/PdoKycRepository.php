<?php

declare(strict_types=1);

namespace Vapehaus\Infrastructure\Repository;

use PDO;
use Vapehaus\Domain\Model\KycVerification;
use Vapehaus\Domain\Repository\KycRepositoryInterface;

final class PdoKycRepository implements KycRepositoryInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function save(KycVerification $kyc): void
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM kyc_verifications WHERE id = :id');
        $stmt->execute([':id' => $kyc->getId()]);
        $exists = (int) $stmt->fetchColumn() > 0;

        if ($exists) {
            $stmt = $this->pdo->prepare('UPDATE kyc_verifications SET
                id_type = :type,
                id_number = :num,
                front_image_url = :f,
                back_image_url = :b,
                status = :status,
                rejection_reason = :reason,
                verified_at = :vat
                WHERE id = :id');
            $stmt->execute([
                ':id' => $kyc->getId(),
                ':type' => $kyc->getIdType(),
                ':num' => $kyc->getIdNumber(),
                ':f' => $kyc->getFrontImageUrl(),
                ':b' => $kyc->getBackImageUrl(),
                ':status' => $kyc->getStatus(),
                ':reason' => $kyc->getRejectionReason(),
                ':vat' => $kyc->getVerifiedAt(),
            ]);
        } else {
            $stmt = $this->pdo->prepare('INSERT INTO kyc_verifications (id, user_id, id_type, id_number, front_image_url, back_image_url, status, rejection_reason, verified_at, created_at)
                VALUES (:id, :uid, :type, :num, :f, :b, :status, :reason, :vat, :created)');
            $stmt->execute([
                ':id' => $kyc->getId(),
                ':uid' => $kyc->getUserId(),
                ':type' => $kyc->getIdType(),
                ':num' => $kyc->getIdNumber(),
                ':f' => $kyc->getFrontImageUrl(),
                ':b' => $kyc->getBackImageUrl(),
                ':status' => $kyc->getStatus(),
                ':reason' => $kyc->getRejectionReason(),
                ':vat' => $kyc->getVerifiedAt(),
                ':created' => $kyc->getCreatedAt(),
            ]);
        }
    }

    public function findById(string $id): ?KycVerification
    {
        $stmt = $this->pdo->prepare('SELECT * FROM kyc_verifications WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ? $this->mapRowToKyc($row) : null;
    }

    public function findLatestByUserId(string $userId): ?KycVerification
    {
        $stmt = $this->pdo->prepare('SELECT * FROM kyc_verifications WHERE user_id = :uid ORDER BY created_at DESC LIMIT 1');
        $stmt->execute([':uid' => $userId]);
        $row = $stmt->fetch();
        return $row ? $this->mapRowToKyc($row) : null;
    }

    public function findAll(array $filters = []): array
    {
        $sql = 'SELECT * FROM kyc_verifications';
        $where = [];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = 'status = :status';
            $params[':status'] = $filters['status'];
        }

        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY created_at DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        return array_map(fn ($r) => $this->mapRowToKyc($r), $rows);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function mapRowToKyc(array $row): KycVerification
    {
        return new KycVerification(
            (string) $row['id'],
            (string) $row['user_id'],
            (string) $row['id_type'],
            (string) $row['id_number'],
            (string) $row['front_image_url'],
            $row['back_image_url'] !== null ? (string) $row['back_image_url'] : null,
            (string) $row['status'],
            $row['rejection_reason'] !== null ? (string) $row['rejection_reason'] : null,
            $row['verified_at'] !== null ? (string) $row['verified_at'] : null,
            (string) $row['created_at']
        );
    }
}
