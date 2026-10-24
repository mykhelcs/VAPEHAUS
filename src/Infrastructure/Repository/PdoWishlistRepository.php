<?php

declare(strict_types=1);

namespace Vapehaus\Infrastructure\Repository;

use PDO;
use Vapehaus\Domain\Model\Product;
use Vapehaus\Domain\Model\ProductVariant;
use Vapehaus\Domain\Model\RestockSubscription;
use Vapehaus\Domain\Repository\WishlistRepositoryInterface;
use Vapehaus\Domain\ValueObject\Money;
use Vapehaus\Domain\ValueObject\NicotineStrength;
use Vapehaus\Domain\ValueObject\Sku;

final class PdoWishlistRepository implements WishlistRepositoryInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function add(string $userId, string $productId): void
    {
        if ($this->isWishlisted($userId, $productId)) {
            return;
        }
        $stmt = $this->pdo->prepare('INSERT INTO wishlists (id, user_id, product_id, created_at) VALUES (:id, :uid, :pid, datetime("now"))');
        $stmt->execute([
            ':id' => 'wsh_' . bin2hex(random_bytes(6)),
            ':uid' => $userId,
            ':pid' => $productId,
        ]);
    }

    public function remove(string $userId, string $productId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM wishlists WHERE user_id = :uid AND product_id = :pid');
        $stmt->execute([':uid' => $userId, ':pid' => $productId]);
    }

    public function findProductsByUserId(string $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT p.* FROM products p JOIN wishlists w ON w.product_id = p.id WHERE w.user_id = :uid ORDER BY w.created_at DESC');
        $stmt->execute([':uid' => $userId]);
        $rows = $stmt->fetchAll();

        $products = [];
        foreach ($rows as $row) {
            $vStmt = $this->pdo->prepare('SELECT * FROM product_variants WHERE product_id = :pid AND is_active = 1');
            $vStmt->execute([':pid' => $row['id']]);
            $variants = array_map(function ($vr) {
                return new ProductVariant(
                    (string) $vr['id'],
                    (string) $vr['product_id'],
                    new Sku((string) $vr['sku']),
                    (string) $vr['flavor'],
                    new NicotineStrength((string) $vr['nicotine_strength']),
                    Money::fromFloat((float) $vr['price']),
                    (int) $vr['stock_quantity'],
                    (bool) $vr['is_active']
                );
            }, $vStmt->fetchAll());

            $products[] = new Product(
                (string) $row['id'],
                (string) $row['category_id'],
                (string) $row['name'],
                (string) $row['slug'],
                (string) $row['brand'],
                $row['description'] !== null ? (string) $row['description'] : null,
                (bool) $row['is_active'],
                (string) $row['created_at'],
                $variants
            );
        }

        return $products;
    }

    public function isWishlisted(string $userId, string $productId): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM wishlists WHERE user_id = :uid AND product_id = :pid');
        $stmt->execute([':uid' => $userId, ':pid' => $productId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function addRestockSubscription(string $userId, string $variantId): void
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM restock_subscriptions WHERE user_id = :uid AND variant_id = :vid AND notified_at IS NULL');
        $stmt->execute([':uid' => $userId, ':vid' => $variantId]);
        if ((int) $stmt->fetchColumn() > 0) {
            return;
        }

        $stmt = $this->pdo->prepare('INSERT INTO restock_subscriptions (id, user_id, variant_id, created_at) VALUES (:id, :uid, :vid, datetime("now"))');
        $stmt->execute([
            ':id' => 'rstk_' . bin2hex(random_bytes(6)),
            ':uid' => $userId,
            ':vid' => $variantId,
        ]);
    }

    public function findPendingRestockSubscriptions(string $variantId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM restock_subscriptions WHERE variant_id = :vid AND notified_at IS NULL');
        $stmt->execute([':vid' => $variantId]);
        $rows = $stmt->fetchAll();

        return array_map(function ($r) {
            return new RestockSubscription(
                (string) $r['id'],
                (string) $r['user_id'],
                (string) $r['variant_id'],
                $r['notified_at'] !== null ? (string) $r['notified_at'] : null,
                (string) $r['created_at']
            );
        }, $rows);
    }

    public function markRestockNotified(string $subscriptionId): void
    {
        $stmt = $this->pdo->prepare('UPDATE restock_subscriptions SET notified_at = datetime("now") WHERE id = :id');
        $stmt->execute([':id' => $subscriptionId]);
    }
}
