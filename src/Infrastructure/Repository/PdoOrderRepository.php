<?php

declare(strict_types=1);

namespace Vapehaus\Infrastructure\Repository;

use PDO;
use Vapehaus\Domain\Model\Order;
use Vapehaus\Domain\Model\OrderItem;
use Vapehaus\Domain\Repository\OrderRepositoryInterface;
use Vapehaus\Domain\ValueObject\Money;
use Vapehaus\Domain\ValueObject\OrderStatus;

final class PdoOrderRepository implements OrderRepositoryInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function save(Order $order): void
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM orders WHERE id = :id');
        $stmt->execute([':id' => $order->getId()]);
        $exists = (int) $stmt->fetchColumn() > 0;

        if ($exists) {
            $stmt = $this->pdo->prepare('UPDATE orders SET
                status = :status,
                payment_status = :pstatus,
                shipping_address = :addr
                WHERE id = :id');
            $stmt->execute([
                ':id' => $order->getId(),
                ':status' => $order->getStatus()->getValue(),
                ':pstatus' => $order->getPaymentStatus(),
                ':addr' => $order->getShippingAddress(),
            ]);
        } else {
            $stmt = $this->pdo->prepare('INSERT INTO orders (id, order_number, user_id, status, subtotal, shipping_fee, total, shipping_address, payment_method, payment_status, created_at)
                VALUES (:id, :num, :uid, :status, :sub, :ship, :total, :addr, :pm, :ps, :created)');
            $stmt->execute([
                ':id' => $order->getId(),
                ':num' => $order->getOrderNumber(),
                ':uid' => $order->getUserId(),
                ':status' => $order->getStatus()->getValue(),
                ':sub' => $order->getSubtotal()->toFloat(),
                ':ship' => $order->getShippingFee()->toFloat(),
                ':total' => $order->getTotal()->toFloat(),
                ':addr' => $order->getShippingAddress(),
                ':pm' => $order->getPaymentMethod(),
                ':ps' => $order->getPaymentStatus(),
                ':created' => $order->getCreatedAt(),
            ]);

            // Insert order items
            $itemStmt = $this->pdo->prepare('INSERT INTO order_items (id, order_id, variant_id, snapshotted_name, snapshotted_sku, snapshotted_flavor, snapshotted_nicotine, unit_price, quantity, total_price)
                VALUES (:id, :oid, :vid, :name, :sku, :flavor, :nic, :up, :qty, :tp)');
            foreach ($order->getItems() as $item) {
                $itemStmt->execute([
                    ':id' => $item->getId(),
                    ':oid' => $order->getId(),
                    ':vid' => $item->getVariantId(),
                    ':name' => $item->getSnapshottedName(),
                    ':sku' => $item->getSnapshottedSku(),
                    ':flavor' => $item->getSnapshottedFlavor(),
                    ':nic' => $item->getSnapshottedNicotine(),
                    ':up' => $item->getUnitPrice()->toFloat(),
                    ':qty' => $item->getQuantity(),
                    ':tp' => $item->getTotalPrice()->toFloat(),
                ]);
            }
        }
    }

    public function findById(string $id): ?Order
    {
        $stmt = $this->pdo->prepare('SELECT * FROM orders WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ? $this->loadOrderWithItems($row) : null;
    }

    public function findByOrderNumber(string $orderNumber): ?Order
    {
        $stmt = $this->pdo->prepare('SELECT * FROM orders WHERE order_number = :num');
        $stmt->execute([':num' => trim($orderNumber)]);
        $row = $stmt->fetch();
        return $row ? $this->loadOrderWithItems($row) : null;
    }

    public function findByUserId(string $userId, int $limit = 20, int $offset = 0): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM orders WHERE user_id = :uid ORDER BY created_at DESC LIMIT :lim OFFSET :off');
        $stmt->bindValue(':uid', $userId);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        return array_map(fn ($r) => $this->loadOrderWithItems($r), $rows);
    }

    public function findAll(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $sql = 'SELECT * FROM orders';
        $where = [];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = 'status = :status';
            $params[':status'] = strtolower(trim($filters['status']));
        }
        if (!empty($filters['user_id'])) {
            $where[] = 'user_id = :uid';
            $params[':uid'] = $filters['user_id'];
        }

        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY created_at DESC LIMIT :lim OFFSET :off';

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        return array_map(fn ($r) => $this->loadOrderWithItems($r), $rows);
    }

    public function countAll(array $filters = []): int
    {
        $sql = 'SELECT COUNT(*) FROM orders';
        $where = [];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = 'status = :status';
            $params[':status'] = strtolower(trim($filters['status']));
        }
        if (!empty($filters['user_id'])) {
            $where[] = 'user_id = :uid';
            $params[':uid'] = $filters['user_id'];
        }

        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    public function getMetrics(): array
    {
        $revStmt = $this->pdo->query("SELECT COALESCE(SUM(total), 0) FROM orders WHERE status != 'cancelled'");
        $totalRevenue = (float) $revStmt->fetchColumn();

        $totStmt = $this->pdo->query("SELECT COUNT(*) FROM orders");
        $totalOrders = (int) $totStmt->fetchColumn();

        $pendStmt = $this->pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'");
        $pendingOrders = (int) $pendStmt->fetchColumn();

        $delivStmt = $this->pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'delivered'");
        $deliveredOrders = (int) $delivStmt->fetchColumn();

        return [
            'total_revenue' => $totalRevenue,
            'total_orders' => $totalOrders,
            'pending_orders' => $pendingOrders,
            'delivered_orders' => $deliveredOrders,
        ];
    }

    /**
     * @param array<string, mixed> $orderRow
     */
    private function loadOrderWithItems(array $orderRow): Order
    {
        $stmt = $this->pdo->prepare('SELECT * FROM order_items WHERE order_id = :oid');
        $stmt->execute([':oid' => $orderRow['id']]);
        $itemRows = $stmt->fetchAll();

        $items = [];
        foreach ($itemRows as $row) {
            $items[] = new OrderItem(
                (string) $row['id'],
                (string) $row['order_id'],
                (string) $row['variant_id'],
                (string) $row['snapshotted_name'],
                (string) $row['snapshotted_sku'],
                (string) $row['snapshotted_flavor'],
                (string) $row['snapshotted_nicotine'],
                Money::fromFloat((float) $row['unit_price']),
                (int) $row['quantity'],
                Money::fromFloat((float) $row['total_price'])
            );
        }

        return new Order(
            (string) $orderRow['id'],
            (string) $orderRow['order_number'],
            (string) $orderRow['user_id'],
            new OrderStatus((string) $orderRow['status']),
            Money::fromFloat((float) $orderRow['subtotal']),
            Money::fromFloat((float) $orderRow['shipping_fee']),
            Money::fromFloat((float) $orderRow['total']),
            (string) $orderRow['shipping_address'],
            (string) $orderRow['payment_method'],
            (string) $orderRow['payment_status'],
            (string) $orderRow['created_at'],
            $items
        );
    }
}
