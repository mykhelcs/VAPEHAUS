<?php

declare(strict_types=1);

namespace Vapehaus\Domain\Repository;

use Vapehaus\Domain\Model\ProductReview;

interface ReviewRepositoryInterface
{
    public function save(ProductReview $review): void;

    public function findById(string $id): ?ProductReview;

    /**
     * @return array<int, ProductReview>
     */
    public function findByProductId(string $productId, bool $approvedOnly = true): array;

    /**
     * @param array{status?: string, product_id?: string} $filters
     * @return array<int, ProductReview>
     */
    public function findAll(array $filters = []): array;

    /**
     * @return array{average: float, count: int}
     */
    public function calculateProductRating(string $productId): array;
}
