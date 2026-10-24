<?php

declare(strict_types=1);

namespace Vapehaus\Domain\Model;

use DateTimeImmutable;

final class RestockSubscription
{
    private string $id;
    private string $userId;
    private string $variantId;
    private ?string $notifiedAt;
    private string $createdAt;

    public function __construct(string $id, string $userId, string $variantId, ?string $notifiedAt = null, ?string $createdAt = null)
    {
        $this->id = $id;
        $this->userId = $userId;
        $this->variantId = $variantId;
        $this->notifiedAt = $notifiedAt;
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

    public function getVariantId(): string
    {
        return $this->variantId;
    }

    public function getNotifiedAt(): ?string
    {
        return $this->notifiedAt;
    }

    public function markNotified(): void
    {
        $this->notifiedAt = (new DateTimeImmutable())->format('Y-m-d H:i:s');
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'variant_id' => $this->variantId,
            'notified_at' => $this->notifiedAt,
            'created_at' => $this->createdAt,
        ];
    }
}
