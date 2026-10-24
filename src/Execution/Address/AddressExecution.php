<?php

declare(strict_types=1);

namespace Vapehaus\Execution\Address;

use Vapehaus\Domain\Exception\EntityNotFoundException;
use Vapehaus\Domain\Model\UserAddress;
use Vapehaus\Domain\Repository\AddressRepositoryInterface;

final class AddressExecution
{
    private AddressRepositoryInterface $addressRepo;

    public function __construct(AddressRepositoryInterface $addressRepo)
    {
        $this->addressRepo = $addressRepo;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function list(string $userId): array
    {
        $addresses = $this->addressRepo->findByUserId($userId);
        return array_map(fn (UserAddress $a) => $a->toArray(), $addresses);
    }

    /**
     * @param array{
     *     label?: string,
     *     recipient_name: string,
     *     phone: string,
     *     address_line1: string,
     *     address_line2?: ?string,
     *     city: string,
     *     province: string,
     *     postal_code: string,
     *     is_default?: bool
     * } $data
     * @return array<string, mixed>
     */
    public function create(string $userId, array $data): array
    {
        $isDefault = (bool) ($data['is_default'] ?? false);
        if ($isDefault) {
            $this->addressRepo->clearDefault($userId);
        }

        $existing = $this->addressRepo->findByUserId($userId);
        if (empty($existing)) {
            $isDefault = true; // First address is automatically default
        }

        $id = 'adr_' . bin2hex(random_bytes(6));
        $address = new UserAddress(
            $id,
            $userId,
            $data['label'] ?? 'Home',
            $data['recipient_name'] ?? '',
            $data['phone'] ?? '',
            $data['address_line1'] ?? '',
            $data['address_line2'] ?? null,
            $data['city'] ?? '',
            $data['province'] ?? '',
            $data['postal_code'] ?? '',
            $isDefault
        );

        $this->addressRepo->save($address);
        return $address->toArray();
    }

    /**
     * @return array<string, mixed>
     */
    public function setDefault(string $userId, string $addressId): array
    {
        $address = $this->addressRepo->findById($addressId);
        if ($address === null || $address->getUserId() !== $userId) {
            throw new EntityNotFoundException('UserAddress', $addressId);
        }

        $this->addressRepo->clearDefault($userId);
        $address->setDefault(true);
        $this->addressRepo->save($address);

        return $address->toArray();
    }

    public function delete(string $userId, string $addressId): void
    {
        $address = $this->addressRepo->findById($addressId);
        if ($address === null || $address->getUserId() !== $userId) {
            throw new EntityNotFoundException('UserAddress', $addressId);
        }

        $this->addressRepo->delete($addressId);
    }
}
