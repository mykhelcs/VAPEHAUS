<?php

declare(strict_types=1);

namespace Vapehaus\Domain\Model;

use DateTimeImmutable;
use Vapehaus\Domain\Exception\ValidationException;

final class ProductReview
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    private string $id;
    private string $productId;
    private string $userId;
    private int $rating;
    private string $title;
    private string $comment;
    private bool $isVerifiedBuyer;
    private string $status;
    private string $createdAt;

    public function __construct(
        string $id,
        string $productId,
        string $userId,
        int $rating,
        string $title,
        string $comment,
        bool $isVerifiedBuyer = false,
        string $status = self::STATUS_APPROVED,
        ?string $createdAt = null
    ) {
        if ($rating < 1 || $rating > 5) {
            throw new ValidationException('Review rating must be between 1 and 5 stars.');
        }

        $title = trim($title);
        $comment = trim($comment);

        if ($title === '') {
            throw new ValidationException('Review title cannot be empty.');
        }
        if ($comment === '') {
            throw new ValidationException('Review comment cannot be empty.');
        }

        $this->id = $id;
        $this->productId = $productId;
        $this->userId = $userId;
        $this->rating = $rating;
        $this->title = $title;
        $this->comment = $comment;
        $this->isVerifiedBuyer = $isVerifiedBuyer;
        $this->status = in_array($status, [self::STATUS_PENDING, self::STATUS_APPROVED, self::STATUS_REJECTED], true)
            ? $status
            : self::STATUS_APPROVED;
        $this->createdAt = $createdAt ?? (new DateTimeImmutable())->format('Y-m-d H:i:s');
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getProductId(): string
    {
        return $this->productId;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function getRating(): int
    {
        return $this->rating;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getComment(): string
    {
        return $this->comment;
    }

    public function isVerifiedBuyer(): bool
    {
        return $this->isVerifiedBuyer;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getCreatedAt(): string
    {
        return $this->createdAt;
    }

    public function approve(): void
    {
        $this->status = self::STATUS_APPROVED;
    }

    public function reject(): void
    {
        $this->status = self::STATUS_REJECTED;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->productId,
            'user_id' => $this->userId,
            'rating' => $this->rating,
            'title' => $this->title,
            'comment' => $this->comment,
            'is_verified_buyer' => $this->isVerifiedBuyer,
            'status' => $this->status,
            'created_at' => $this->createdAt,
        ];
    }
}
