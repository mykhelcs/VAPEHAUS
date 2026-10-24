<?php

declare(strict_types=1);

namespace Vapehaus\Domain\Model;

use DateTimeImmutable;

final class InventoryAuditLog
{
    private string $id;
    private string $variantId;
    private int $previousStock;
    private int $newStock;
    private int $delta;
    private string $reason;
    private ?string $actorId;
    private string $createdAt;

    public function __construct(
        string $id,
        string $variantId,
        int $previousStock,
        int $newStock,
        int $delta,
        string $reason,
        ?string $actorId = null,
        ?string $createdAt = null
    ) {
        $this->id = $id;
        $this->variantId = $variantId;
        $this->previousStock = $previousStock;
        $this->newStock = $newStock;
        $this->delta = $delta;
        $this->reason = $reason;
        $this->actorId = $actorId;
        $this->createdAt = $createdAt ?? (new DateTimeImmutable())->format('Y-m-d H:i:s');
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getVariantId(): string
    {
        return $this->variantId;
    }

    public function getPreviousStock(): int
    {
        return $this->previousStock;
    }

    public function getNewStock(): int
    {
        return $this->newStock;
    }

    public function getDelta(): int
    {
        return $this->delta;
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function getActorId(): ?string
    {
        return $this->actorId;
    }

    public function getCreatedAt(): string
    {
        return $this->createdAt;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'variant_id' => $this->variantId,
            'previous_stock' => $this->previousStock,
            'new_stock' => $this->newStock,
            'delta' => $this->delta,
            'reason' => $this->reason,
            'actor_id' => $this->actorId,
            'created_at' => $this->createdAt,
        ];
    }
}
