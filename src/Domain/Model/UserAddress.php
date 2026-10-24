<?php

declare(strict_types=1);

namespace Vapehaus\Domain\Model;

use DateTimeImmutable;
use Vapehaus\Domain\Exception\ValidationException;

final class UserAddress
{
    private string $id;
    private string $userId;
    private string $label;
    private string $recipientName;
    private string $phone;
    private string $addressLine1;
    private ?string $addressLine2;
    private string $city;
    private string $province;
    private string $postalCode;
    private bool $isDefault;
    private string $createdAt;

    public function __construct(
        string $id,
        string $userId,
        string $label,
        string $recipientName,
        string $phone,
        string $addressLine1,
        ?string $addressLine2,
        string $city,
        string $province,
        string $postalCode,
        bool $isDefault = false,
        ?string $createdAt = null
    ) {
        $recipientName = trim($recipientName);
        $phone = trim($phone);
        $addressLine1 = trim($addressLine1);
        $city = trim($city);
        $province = trim($province);
        $postalCode = trim($postalCode);

        if ($recipientName === '') {
            throw new ValidationException('Recipient name is required.');
        }
        if ($phone === '') {
            throw new ValidationException('Recipient phone is required.');
        }
        if ($addressLine1 === '') {
            throw new ValidationException('Address line 1 is required.');
        }
        if ($city === '') {
            throw new ValidationException('City is required.');
        }
        if ($province === '') {
            throw new ValidationException('Province is required.');
        }
        if ($postalCode === '') {
            throw new ValidationException('Postal code is required.');
        }

        $this->id = $id;
        $this->userId = $userId;
        $this->label = trim($label) !== '' ? trim($label) : 'Home';
        $this->recipientName = $recipientName;
        $this->phone = $phone;
        $this->addressLine1 = $addressLine1;
        $this->addressLine2 = $addressLine2 ? trim($addressLine2) : null;
        $this->city = $city;
        $this->province = $province;
        $this->postalCode = $postalCode;
        $this->isDefault = $isDefault;
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

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getRecipientName(): string
    {
        return $this->recipientName;
    }

    public function getPhone(): string
    {
        return $this->phone;
    }

    public function getAddressLine1(): string
    {
        return $this->addressLine1;
    }

    public function getAddressLine2(): ?string
    {
        return $this->addressLine2;
    }

    public function getCity(): string
    {
        return $this->city;
    }

    public function getProvince(): string
    {
        return $this->province;
    }

    public function getPostalCode(): string
    {
        return $this->postalCode;
    }

    public function isDefault(): bool
    {
        return $this->isDefault;
    }

    public function setDefault(bool $default): void
    {
        $this->isDefault = $default;
    }

    public function getCreatedAt(): string
    {
        return $this->createdAt;
    }

    public function getFormattedAddress(): string
    {
        $parts = array_filter([
            $this->addressLine1,
            $this->addressLine2,
            $this->city,
            $this->province,
            $this->postalCode,
        ]);
        return "{$this->recipientName} ({$this->phone}) - " . implode(', ', $parts);
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'label' => $this->label,
            'recipient_name' => $this->recipientName,
            'phone' => $this->phone,
            'address_line1' => $this->addressLine1,
            'address_line2' => $this->addressLine2,
            'city' => $this->city,
            'province' => $this->province,
            'postal_code' => $this->postalCode,
            'is_default' => $this->isDefault,
            'formatted' => $this->getFormattedAddress(),
            'created_at' => $this->createdAt,
        ];
    }
}
