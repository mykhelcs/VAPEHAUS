<?php

declare(strict_types=1);

namespace Vapehaus\Infrastructure\Repository;

use PDO;
use Vapehaus\Domain\Model\InventoryAuditLog;
use Vapehaus\Domain\Repository\InventoryAuditRepositoryInterface;

final class PdoInventoryAuditRepository implements InventoryAuditRepositoryInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function log(InventoryAuditLog $auditLog): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO inventory_audit_logs (id, variant_id, previous_stock, new_stock, delta, reason, actor_id, created_at)
            VALUES (:id, :vid, :prev, :new, :delta, :reason, :actor, :created)');
        $stmt->execute([
            ':id' => $auditLog->getId(),
            ':vid' => $auditLog->getVariantId(),
            ':prev' => $auditLog->getPreviousStock(),
            ':new' => $auditLog->getNewStock(),
            ':delta' => $auditLog->getDelta(),
            ':reason' => $auditLog->getReason(),
            ':actor' => $auditLog->getActorId(),
            ':created' => $auditLog->getCreatedAt(),
        ]);
    }

    public function findByVariantId(string $variantId, int $limit = 50): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM inventory_audit_logs WHERE variant_id = :vid ORDER BY created_at DESC LIMIT :lim');
        $stmt->bindValue(':vid', $variantId);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        return array_map(fn ($r) => $this->mapRowToLog($r), $rows);
    }

    public function findAll(int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM inventory_audit_logs ORDER BY created_at DESC LIMIT :lim OFFSET :off');
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        return array_map(fn ($r) => $this->mapRowToLog($r), $rows);
    }

    public function count(): int
    {
        $stmt = $this->pdo->query('SELECT COUNT(*) FROM inventory_audit_logs');
        return (int) $stmt->fetchColumn();
    }

    /**
     * @param array<string, mixed> $row
     */
    private function mapRowToLog(array $row): InventoryAuditLog
    {
        return new InventoryAuditLog(
            (string) $row['id'],
            (string) $row['variant_id'],
            (int) $row['previous_stock'],
            (int) $row['new_stock'],
            (int) $row['delta'],
            (string) $row['reason'],
            $row['actor_id'] !== null ? (string) $row['actor_id'] : null,
            (string) $row['created_at']
        );
    }
}
