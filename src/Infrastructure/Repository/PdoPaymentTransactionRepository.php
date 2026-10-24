<?php

declare(strict_types=1);

namespace Vapehaus\Infrastructure\Repository;

use PDO;
use Vapehaus\Domain\Model\PaymentTransaction;
use Vapehaus\Domain\Repository\PaymentTransactionRepositoryInterface;
use Vapehaus\Domain\ValueObject\Money;

final class PdoPaymentTransactionRepository implements PaymentTransactionRepositoryInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function save(PaymentTransaction $transaction): void
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM payment_transactions WHERE id = :id');
        $stmt->execute([':id' => $transaction->getId()]);
        $exists = (int) $stmt->fetchColumn() > 0;

        if ($exists) {
            $stmt = $this->pdo->prepare('UPDATE payment_transactions SET status = :status WHERE id = :id');
            $stmt->execute([
                ':id' => $transaction->getId(),
                ':status' => $transaction->getStatus(),
            ]);
        } else {
            $stmt = $this->pdo->prepare('INSERT INTO payment_transactions (id, order_id, gateway, transaction_reference, amount, status, raw_payload, created_at)
                VALUES (:id, :oid, :gw, :ref, :amt, :status, :payload, :created)');
            $stmt->execute([
                ':id' => $transaction->getId(),
                ':oid' => $transaction->getOrderId(),
                ':gw' => $transaction->getGateway(),
                ':ref' => $transaction->getTransactionReference(),
                ':amt' => $transaction->getAmount()->toFloat(),
                ':status' => $transaction->getStatus(),
                ':payload' => json_encode($transaction->getRawPayload()),
                ':created' => $transaction->getCreatedAt(),
            ]);
        }
    }

    public function findByReference(string $reference): ?PaymentTransaction
    {
        $stmt = $this->pdo->prepare('SELECT * FROM payment_transactions WHERE transaction_reference = :ref');
        $stmt->execute([':ref' => trim($reference)]);
        $row = $stmt->fetch();
        return $row ? $this->mapRowToTransaction($row) : null;
    }

    public function findByOrderId(string $orderId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM payment_transactions WHERE order_id = :oid ORDER BY created_at DESC');
        $stmt->execute([':oid' => $orderId]);
        $rows = $stmt->fetchAll();
        return array_map(fn ($r) => $this->mapRowToTransaction($r), $rows);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function mapRowToTransaction(array $row): PaymentTransaction
    {
        $payload = [];
        if (!empty($row['raw_payload'])) {
            $decoded = json_decode((string) $row['raw_payload'], true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }

        return new PaymentTransaction(
            (string) $row['id'],
            (string) $row['order_id'],
            (string) $row['gateway'],
            (string) $row['transaction_reference'],
            Money::fromFloat((float) $row['amount']),
            (string) $row['status'],
            $payload,
            (string) $row['created_at']
        );
    }
}
