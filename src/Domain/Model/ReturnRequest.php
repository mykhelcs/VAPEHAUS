<?php

declare(strict_types=1);

namespace Vapehaus\Domain\Model;

use DateTimeImmutable;
use Vapehaus\Domain\Exception\ValidationException;

final class ReturnRequest
{
    public const STATUS_REQUESTED = 'requested';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_REPLACED = 'replaced';
    public const STATUS_REFUNDED = 'refunded';

    public const REASON_DOA = 'dead_on_arrival';
    public const REASON_LEAKING = 'leaking';
    public const REASON_BATTERY = 'faulty_battery';
    public const REASON_WRONG_ITEM = 'wrong_item';

    private string $id;
    private string $orderId;
    private string $userId;
    private string $variantId;
    private string $reason;
    private string $description;
    private ?string $photoUrl;
    private string $status;
    private ?string $adminNotes;
    private string $createdAt;

    public function __construct(
        string $id,
        string $orderId,
        string $userId,
        string $variantId,
        string $reason,
        string $description,
        ?string $photoUrl = null,
        string $status = self::STATUS_REQUESTED,
        ?string $adminNotes = null,
        ?string $createdAt = null
    ) {
        $description = trim($description);
        if ($description === '') {
            throw new ValidationException('Description of the defect is required.');
        }

        $this->id = $id;
        $this->orderId = $orderId;
        $this->userId = $userId;
        $this->variantId = $variantId;
        $this->reason = $reason;
        $this->description = $description;
        $this->photoUrl = $photoUrl ? trim($photoUrl) : null;
        $this->status = in_array($status, [
            self::STATUS_REQUESTED,
            self::STATUS_APPROVED,
            self::STATUS_REJECTED,
            self::STATUS_REPLACED,
            self::STATUS_REFUNDED,
        ], true) ? $status : self::STATUS_REQUESTED;
        $this->adminNotes = $adminNotes;
        $this->createdAt = $createdAt ?? (new DateTimeImmutable())->format('Y-m-d H:i:s');
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getOrderId(): string
    {
        return $this->orderId;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function getVariantId(): string
    {
        return $this->variantId;
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getPhotoUrl(): ?string
    {
        return $this->photoUrl;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getAdminNotes(): ?string
    {
        return $this->adminNotes;
    }

    public function getCreatedAt(): string
    {
        return $this->createdAt;
    }

    public function updateStatus(string $newStatus, ?string $notes = null): void
    {
        $newStatus = strtolower(trim($newStatus));
        if (!in_array($newStatus, [
            self::STATUS_REQUESTED,
            self::STATUS_APPROVED,
            self::STATUS_REJECTED,
            self::STATUS_REPLACED,
            self::STATUS_REFUNDED,
        ], true)) {
            throw new ValidationException("Invalid RMA status: {$newStatus}");
        }
        $this->status = $newStatus;
        if ($notes !== null) {
            $this->adminNotes = trim($notes);
        }
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->orderId,
            'user_id' => $this->userId,
            'variant_id' => $this->variantId,
            'reason' => $this->reason,
            'description' => $this->description,
            'photo_url' => $this->photoUrl,
            'status' => $this->status,
            'admin_notes' => $this->adminNotes,
            'created_at' => $this->createdAt,
        ];
    }
}
