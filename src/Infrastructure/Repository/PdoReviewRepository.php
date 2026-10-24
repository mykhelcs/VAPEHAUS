<?php

declare(strict_types=1);

namespace Vapehaus\Infrastructure\Repository;

use PDO;
use Vapehaus\Domain\Model\ProductReview;
use Vapehaus\Domain\Repository\ReviewRepositoryInterface;

final class PdoReviewRepository implements ReviewRepositoryInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function save(ProductReview $review): void
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM product_reviews WHERE id = :id');
        $stmt->execute([':id' => $review->getId()]);
        $exists = (int) $stmt->fetchColumn() > 0;

        if ($exists) {
            $stmt = $this->pdo->prepare('UPDATE product_reviews SET
                rating = :rating,
                title = :title,
                comment = :comment,
                is_verified_buyer = :vb,
                status = :status
                WHERE id = :id');
            $stmt->execute([
                ':id' => $review->getId(),
                ':rating' => $review->getRating(),
                ':title' => $review->getTitle(),
                ':comment' => $review->getComment(),
                ':vb' => $review->isVerifiedBuyer() ? 1 : 0,
                ':status' => $review->getStatus(),
            ]);
        } else {
            $stmt = $this->pdo->prepare('INSERT INTO product_reviews (id, product_id, user_id, rating, title, comment, is_verified_buyer, status, created_at)
                VALUES (:id, :pid, :uid, :rating, :title, :comment, :vb, :status, :created)');
            $stmt->execute([
                ':id' => $review->getId(),
                ':pid' => $review->getProductId(),
                ':uid' => $review->getUserId(),
                ':rating' => $review->getRating(),
                ':title' => $review->getTitle(),
                ':comment' => $review->getComment(),
                ':vb' => $review->isVerifiedBuyer() ? 1 : 0,
                ':status' => $review->getStatus(),
                ':created' => $review->getCreatedAt(),
            ]);
        }

        // Recalculate and update aggregate rating on products table
        $ratingData = $this->calculateProductRating($review->getProductId());
        $upd = $this->pdo->prepare('UPDATE products SET average_rating = :avg, review_count = :cnt WHERE id = :id');
        $upd->execute([
            ':id' => $review->getProductId(),
            ':avg' => $ratingData['average'],
            ':cnt' => $ratingData['count'],
        ]);
    }

    public function findById(string $id): ?ProductReview
    {
        $stmt = $this->pdo->prepare('SELECT * FROM product_reviews WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ? $this->mapRowToReview($row) : null;
    }

    public function findByProductId(string $productId, bool $approvedOnly = true): array
    {
        $sql = 'SELECT * FROM product_reviews WHERE product_id = :pid';
        if ($approvedOnly) {
            $sql .= " AND status = 'approved'";
        }
        $sql .= ' ORDER BY created_at DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':pid' => $productId]);
        $rows = $stmt->fetchAll();
        return array_map(fn ($r) => $this->mapRowToReview($r), $rows);
    }

    public function findAll(array $filters = []): array
    {
        $sql = 'SELECT * FROM product_reviews';
        $where = [];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = 'status = :status';
            $params[':status'] = $filters['status'];
        }
        if (!empty($filters['product_id'])) {
            $where[] = 'product_id = :pid';
            $params[':pid'] = $filters['product_id'];
        }

        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY created_at DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        return array_map(fn ($r) => $this->mapRowToReview($r), $rows);
    }

    public function calculateProductRating(string $productId): array
    {
        $stmt = $this->pdo->prepare("SELECT COALESCE(AVG(rating), 0) as avg_rating, COUNT(*) as cnt FROM product_reviews WHERE product_id = :pid AND status = 'approved'");
        $stmt->execute([':pid' => $productId]);
        $data = $stmt->fetch();

        return [
            'average' => round((float) ($data['avg_rating'] ?? 0), 2),
            'count' => (int) ($data['cnt'] ?? 0),
        ];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function mapRowToReview(array $row): ProductReview
    {
        return new ProductReview(
            (string) $row['id'],
            (string) $row['product_id'],
            (string) $row['user_id'],
            (int) $row['rating'],
            (string) $row['title'],
            (string) $row['comment'],
            (bool) $row['is_verified_buyer'],
            (string) $row['status'],
            (string) $row['created_at']
        );
    }
}
