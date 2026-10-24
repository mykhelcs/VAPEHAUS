<?php

declare(strict_types=1);

namespace Vapehaus\Execution\Review;

use Vapehaus\Domain\Exception\EntityNotFoundException;
use Vapehaus\Domain\Exception\ValidationException;
use Vapehaus\Domain\Model\ProductReview;
use Vapehaus\Domain\Repository\OrderRepositoryInterface;
use Vapehaus\Domain\Repository\ProductRepositoryInterface;
use Vapehaus\Domain\Repository\ReviewRepositoryInterface;

final class ReviewExecution
{
    private ReviewRepositoryInterface $reviewRepo;
    private ProductRepositoryInterface $productRepo;
    private OrderRepositoryInterface $orderRepo;

    public function __construct(
        ReviewRepositoryInterface $reviewRepo,
        ProductRepositoryInterface $productRepo,
        OrderRepositoryInterface $orderRepo
    ) {
        $this->reviewRepo = $reviewRepo;
        $this->productRepo = $productRepo;
        $this->orderRepo = $orderRepo;
    }

    /**
     * @param array{rating: int, title: string, comment: string} $data
     * @return array<string, mixed>
     */
    public function create(string $userId, string $productId, array $data): array
    {
        $product = $this->productRepo->findById($productId);
        if ($product === null) {
            throw new EntityNotFoundException('Product', $productId);
        }

        // Check verified buyer: Has this user bought any variant of this product in a delivered/paid order?
        $userOrders = $this->orderRepo->findByUserId($userId, 100, 0);
        $isVerifiedBuyer = false;
        foreach ($userOrders as $order) {
            foreach ($order->getItems() as $item) {
                $variant = $this->productRepo->findVariantById($item->getVariantId());
                if ($variant !== null && $variant->getProductId() === $productId) {
                    $isVerifiedBuyer = true;
                    break 2;
                }
            }
        }

        $reviewId = 'rev_' . bin2hex(random_bytes(6));
        $review = new ProductReview(
            $reviewId,
            $productId,
            $userId,
            (int) ($data['rating'] ?? 5),
            $data['title'] ?? '',
            $data['comment'] ?? '',
            $isVerifiedBuyer,
            ProductReview::STATUS_APPROVED
        );

        $this->reviewRepo->save($review);
        return $review->toArray();
    }

    /**
     * @return array{reviews: array<int, array<string, mixed>>, rating_summary: array{average: float, count: int}}
     */
    public function listForProduct(string $productId): array
    {
        $reviews = $this->reviewRepo->findByProductId($productId, true);
        $summary = $this->reviewRepo->calculateProductRating($productId);

        return [
            'reviews' => array_map(fn (ProductReview $r) => $r->toArray(), $reviews),
            'rating_summary' => $summary,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function adminModerate(string $reviewId, string $status): array
    {
        $review = $this->reviewRepo->findById($reviewId);
        if ($review === null) {
            throw new EntityNotFoundException('ProductReview', $reviewId);
        }

        $status = strtolower(trim($status));
        if ($status === 'approved') {
            $review->approve();
        } elseif ($status === 'rejected') {
            $review->reject();
        } else {
            throw new ValidationException("Invalid review status: {$status}");
        }

        $this->reviewRepo->save($review);
        return $review->toArray();
    }

    /**
     * @param array{status?: string, product_id?: string} $filters
     * @return array<int, array<string, mixed>>
     */
    public function adminList(array $filters = []): array
    {
        $reviews = $this->reviewRepo->findAll($filters);
        return array_map(fn (ProductReview $r) => $r->toArray(), $reviews);
    }
}
