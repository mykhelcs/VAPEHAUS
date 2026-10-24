<?php

declare(strict_types=1);

namespace Vapehaus\Domain\Model;

use DateTimeImmutable;

final class WishlistItem
{
    private string $id;
    private string $userId;
    private string $productId;
    private string $createdAt;

    public function __construct(string $id, string $userId, string $productId, ?string $createdAt = null)
    {
        $this->id = $id;
        $this->userId = $userId;
        $this->productId = $productId;
        $this->createdAt = $createdAt ?? (new DateTimeImmutable())->format('Y-m-d H:i:s');
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function getProductId(): string
    {
        return $this->productId;
    }

    public function getCreatedAt(): string
    {
        return $this->createdAt;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'product_id' => $this->productId,
            'created_at' => $this->createdAt,
        ];
    }
}
