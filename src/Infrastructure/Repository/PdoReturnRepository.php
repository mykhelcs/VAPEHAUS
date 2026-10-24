<?php

declare(strict_types=1);

namespace Vapehaus\Infrastructure\Repository;

use PDO;
use Vapehaus\Domain\Model\ReturnRequest;
use Vapehaus\Domain\Repository\ReturnRepositoryInterface;

final class PdoReturnRepository implements ReturnRepositoryInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function save(ReturnRequest $return): void
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM return_requests WHERE id = :id');
        $stmt->execute([':id' => $return->getId()]);
        $exists = (int) $stmt->fetchColumn() > 0;

        if ($exists) {
            $stmt = $this->pdo->prepare('UPDATE return_requests SET
                status = :status,
                admin_notes = :notes
                WHERE id = :id');
            $stmt->execute([
                ':id' => $return->getId(),
                ':status' => $return->getStatus(),
                ':notes' => $return->getAdminNotes(),
            ]);
        } else {
            $stmt = $this->pdo->prepare('INSERT INTO return_requests (id, order_id, user_id, variant_id, reason, description, photo_url, status, admin_notes, created_at)
                VALUES (:id, :oid, :uid, :vid, :reason, :desc, :photo, :status, :notes, :created)');
            $stmt->execute([
                ':id' => $return->getId(),
                ':oid' => $return->getOrderId(),
                ':uid' => $return->getUserId(),
                ':vid' => $return->getVariantId(),
                ':reason' => $return->getReason(),
                ':desc' => $return->getDescription(),
                ':photo' => $return->getPhotoUrl(),
                ':status' => $return->getStatus(),
                ':notes' => $return->getAdminNotes(),
                ':created' => $return->getCreatedAt(),
            ]);
        }
    }

    public function findById(string $id): ?ReturnRequest
    {
        $stmt = $this->pdo->prepare('SELECT * FROM return_requests WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ? $this->mapRowToReturn($row) : null;
    }

    public function findByUserId(string $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM return_requests WHERE user_id = :uid ORDER BY created_at DESC');
        $stmt->execute([':uid' => $userId]);
        $rows = $stmt->fetchAll();
        return array_map(fn ($r) => $this->mapRowToReturn($r), $rows);
    }

    public function findAll(array $filters = []): array
    {
        $sql = 'SELECT * FROM return_requests';
        $where = [];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = 'status = :status';
            $params[':status'] = $filters['status'];
        }
        if (!empty($filters['order_id'])) {
            $where[] = 'order_id = :oid';
            $params[':oid'] = $filters['order_id'];
        }

        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY created_at DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        return array_map(fn ($r) => $this->mapRowToReturn($r), $rows);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function mapRowToReturn(array $row): ReturnRequest
    {
        return new ReturnRequest(
            (string) $row['id'],
            (string) $row['order_id'],
            (string) $row['user_id'],
            (string) $row['variant_id'],
            (string) $row['reason'],
            (string) $row['description'],
            $row['photo_url'] !== null ? (string) $row['photo_url'] : null,
            (string) $row['status'],
            $row['admin_notes'] !== null ? (string) $row['admin_notes'] : null,
            (string) $row['created_at']
        );
    }
}
