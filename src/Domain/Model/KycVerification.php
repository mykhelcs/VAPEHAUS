<?php

declare(strict_types=1);

namespace Vapehaus\Domain\Model;

use DateTimeImmutable;
use Vapehaus\Domain\Exception\ValidationException;

final class KycVerification
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    public const TYPE_DRIVERS_LICENSE = 'drivers_license';
    public const TYPE_PASSPORT = 'passport';
    public const TYPE_NATIONAL_ID = 'national_id';
    public const TYPE_UMID = 'umid';
    public const TYPE_POSTAL_ID = 'postal_id';

    private string $id;
    private string $userId;
    private string $idType;
    private string $idNumber;
    private string $frontImageUrl;
    private ?string $backImageUrl;
    private string $status;
    private ?string $rejectionReason;
    private ?string $verifiedAt;
    private string $createdAt;

    public function __construct(
        string $id,
        string $userId,
        string $idType,
        string $idNumber,
        string $frontImageUrl,
        ?string $backImageUrl = null,
        string $status = self::STATUS_PENDING,
        ?string $rejectionReason = null,
        ?string $verifiedAt = null,
        ?string $createdAt = null
    ) {
        $idType = strtolower(trim($idType));
        $idNumber = trim($idNumber);
        $frontImageUrl = trim($frontImageUrl);

        if ($idNumber === '') {
            throw new ValidationException('ID Number is required.');
        }
        if ($frontImageUrl === '') {
            throw new ValidationException('Front ID photo is required.');
        }

        $this->id = $id;
        $this->userId = $userId;
        $this->idType = $idType;
        $this->idNumber = $idNumber;
        $this->frontImageUrl = $frontImageUrl;
        $this->backImageUrl = $backImageUrl ? trim($backImageUrl) : null;
        $this->status = in_array($status, [self::STATUS_PENDING, self::STATUS_APPROVED, self::STATUS_REJECTED], true)
            ? $status
            : self::STATUS_PENDING;
        $this->rejectionReason = $rejectionReason;
        $this->verifiedAt = $verifiedAt;
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

    public function getIdType(): string
    {
        return $this->idType;
    }

    public function getIdNumber(): string
    {
        return $this->idNumber;
    }

    public function getFrontImageUrl(): string
    {
        return $this->frontImageUrl;
    }

    public function getBackImageUrl(): ?string
    {
        return $this->backImageUrl;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getRejectionReason(): ?string
    {
        return $this->rejectionReason;
    }

    public function getVerifiedAt(): ?string
    {
        return $this->verifiedAt;
    }

    public function getCreatedAt(): string
    {
        return $this->createdAt;
    }

    public function approve(): void
    {
        $this->status = self::STATUS_APPROVED;
        $this->verifiedAt = (new DateTimeImmutable())->format('Y-m-d H:i:s');
        $this->rejectionReason = null;
    }

    public function reject(string $reason): void
    {
        $this->status = self::STATUS_REJECTED;
        $this->rejectionReason = trim($reason);
        $this->verifiedAt = null;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'id_type' => $this->idType,
            'id_number' => $this->idNumber,
            'front_image_url' => $this->frontImageUrl,
            'back_image_url' => $this->backImageUrl,
            'status' => $this->status,
            'rejection_reason' => $this->rejectionReason,
            'verified_at' => $this->verifiedAt,
            'created_at' => $this->createdAt,
        ];
    }
}
