<?php

declare(strict_types=1);

namespace Vapehaus\Infrastructure\Repository;

use PDO;
use Vapehaus\Domain\Model\Cart;
use Vapehaus\Domain\Model\CartItem;
use Vapehaus\Domain\Repository\CartRepositoryInterface;
use Vapehaus\Domain\Repository\ProductRepositoryInterface;

final class PdoCartRepository implements CartRepositoryInterface
{
    private PDO $pdo;
    private ?ProductRepositoryInterface $productRepository;

    public function __construct(PDO $pdo, ?ProductRepositoryInterface $productRepository = null)
    {
        $this->pdo = $pdo;
        $this->productRepository = $productRepository;
    }

    public function findByUserId(string $userId): ?Cart
    {
        $stmt = $this->pdo->prepare('SELECT * FROM carts WHERE user_id = :uid');
        $stmt->execute([':uid' => $userId]);
        $row = $stmt->fetch();
        return $row ? $this->loadCartWithItems($row) : null;
    }

    public function findBySessionToken(string $sessionToken): ?Cart
    {
        $stmt = $this->pdo->prepare('SELECT * FROM carts WHERE session_token = :st');
        $stmt->execute([':st' => $sessionToken]);
        $row = $stmt->fetch();
        return $row ? $this->loadCartWithItems($row) : null;
    }

    public function findById(string $id): ?Cart
    {
        $stmt = $this->pdo->prepare('SELECT * FROM carts WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ? $this->loadCartWithItems($row) : null;
    }

    public function save(Cart $cart): void
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM carts WHERE id = :id');
        $stmt->execute([':id' => $cart->getId()]);
        $exists = (int) $stmt->fetchColumn() > 0;

        if ($exists) {
            $stmt = $this->pdo->prepare('UPDATE carts SET user_id = :uid, session_token = :st, updated_at = datetime("now") WHERE id = :id');
            $stmt->execute([
                ':id' => $cart->getId(),
                ':uid' => $cart->getUserId(),
                ':st' => $cart->getSessionToken(),
            ]);
        } else {
            $stmt = $this->pdo->prepare('INSERT INTO carts (id, user_id, session_token, updated_at) VALUES (:id, :uid, :st, datetime("now"))');
            $stmt->execute([
                ':id' => $cart->getId(),
                ':uid' => $cart->getUserId(),
                ':st' => $cart->getSessionToken(),
            ]);
        }

        // Synchronize items
        $this->pdo->prepare('DELETE FROM cart_items WHERE cart_id = :cid')->execute([':cid' => $cart->getId()]);

        $ins = $this->pdo->prepare('INSERT INTO cart_items (id, cart_id, variant_id, quantity) VALUES (:id, :cid, :vid, :qty)');
        foreach ($cart->getItems() as $item) {
            $ins->execute([
                ':id' => $item->getId(),
                ':cid' => $cart->getId(),
                ':vid' => $item->getVariantId(),
                ':qty' => $item->getQuantity(),
            ]);
        }
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM carts WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    /**
     * @param array<string, mixed> $cartRow
     */
    private function loadCartWithItems(array $cartRow): Cart
    {
        $stmt = $this->pdo->prepare('SELECT * FROM cart_items WHERE cart_id = :cid');
        $stmt->execute([':cid' => $cartRow['id']]);
        $itemRows = $stmt->fetchAll();

        $items = [];
        foreach ($itemRows as $row) {
            $variant = null;
            if ($this->productRepository !== null) {
                $variant = $this->productRepository->findVariantById((string) $row['variant_id']);
            }
            $items[] = new CartItem(
                (string) $row['id'],
                (string) $row['cart_id'],
                (string) $row['variant_id'],
                (int) $row['quantity'],
                $variant
            );
        }

        return new Cart(
            (string) $cartRow['id'],
            $cartRow['user_id'] !== null ? (string) $cartRow['user_id'] : null,
            $cartRow['session_token'] !== null ? (string) $cartRow['session_token'] : null,
            $items
        );
    }
}
